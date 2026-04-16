<?php
/**
 * Orchestrates a single review: cache → rate-limit → normalise → AI call → cache.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer\AI;

use StyleGuideReviewer\Admin\SettingsPage;
use StyleGuideReviewer\Support\RateLimiter;
use StyleGuideReviewer\Support\ResultCache;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The ability's `callback` points here. Accepts input validated against
 * `ReviewSchema::input_schema()` and returns either a structured review
 * payload (schema-valid) or a `WP_Error`.
 */
final class ReviewRunner {

	public const AI_TIMEOUT_SECONDS = 30;
	public const MAX_OUTPUT_TOKENS  = 2000;

	/**
	 * Entry point, registered as the ability callback.
	 *
	 * @param array<string,mixed> $input Validated ability input.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function run( array $input ) {
		$post_id = isset( $input['postId'] ) ? (int) $input['postId'] : 0;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_Error(
				'sgr_post_not_found',
				__( 'Post not found.', 'style-guide-reviewer' ),
				[ 'status' => 404 ]
			);
		}

		$guide = (string) get_option( SettingsPage::GUIDE_OPTION, '' );
		if ( '' === trim( $guide ) ) {
			return new WP_Error(
				'sgr_no_guide',
				__( 'No style guide has been configured. Set one in Settings → Style Guide Reviewer.', 'style-guide-reviewer' ),
				[ 'status' => 409 ]
			);
		}

		$normalized = ContentNormalizer::normalize( (string) $post->post_content );
		if ( '' === $normalized['text'] ) {
			return new WP_Error(
				'sgr_empty_content',
				__( 'Post content is empty.', 'style-guide-reviewer' ),
				[ 'status' => 422 ]
			);
		}

		$hash = ResultCache::hash( $guide, $normalized['text'] );

		// Cache check. A hit short-circuits without charging the AI provider.
		$cached = ResultCache::get( $post_id, $hash );
		if ( null !== $cached ) {
			self::mark_cache( true );
			return self::decorate( $cached, $normalized['truncated'] );
		}
		self::mark_cache( false );

		$user_id = get_current_user_id();
		if ( ! RateLimiter::check( $user_id ) ) {
			return new WP_Error(
				'sgr_rate_limited',
				__( 'You are reviewing posts too frequently. Please wait a minute and try again.', 'style-guide-reviewer' ),
				[ 'status' => 429 ]
			);
		}

		$generated = self::generate( $guide, $normalized['text'] );
		if ( is_wp_error( $generated ) ) {
			self::log_error( $generated );
			// Never expose provider internals to the client; return a
			// sanitised message but preserve the wp_error code so callers
			// can differentiate.
			return new WP_Error(
				'sgr_ai_failed',
				__( 'The AI provider could not complete the review. Please try again shortly.', 'style-guide-reviewer' ),
				[ 'status' => 502 ]
			);
		}

		$validated = self::validate_payload( $generated );
		if ( is_wp_error( $validated ) ) {
			self::log_error( $validated );
			return new WP_Error(
				'sgr_invalid_ai_output',
				__( 'The AI provider returned an unexpected response.', 'style-guide-reviewer' ),
				[ 'status' => 502 ]
			);
		}

		ResultCache::set( $post_id, $hash, $validated );

		return self::decorate( $validated, $normalized['truncated'] );
	}

	/**
	 * Generate a response from the AI Client, or bail to the stub filter.
	 *
	 * Tests replace this path via the `sgr_pre_ai_generate` filter so no
	 * real AI provider is called in CI.
	 *
	 * @return array<string,mixed>|WP_Error Decoded payload or an error.
	 */
	private static function generate( string $guide, string $content ) {
		$prompt = self::build_prompt( $guide, $content );
		$schema = ReviewSchema::get();

		/**
		 * Short-circuit AI generation. If the filter returns a non-null
		 * value, the AI Client is not called. Used by the Playground AI
		 * stub and by unit tests.
		 *
		 * @param mixed                $pre     Pass-through value. `null` = no override.
		 * @param string               $prompt  The full prompt text.
		 * @param array<string,mixed>  $schema  The output JSON Schema.
		 */
		$pre = apply_filters( 'sgr_pre_ai_generate', null, $prompt, $schema );
		if ( null !== $pre ) {
			if ( is_wp_error( $pre ) ) {
				return $pre;
			}
			if ( is_array( $pre ) ) {
				return $pre;
			}
			if ( is_string( $pre ) ) {
				$decoded = json_decode( $pre, true );
				return is_array( $decoded ) ? $decoded : new WP_Error( 'sgr_bad_stub', 'stub returned non-JSON' );
			}
			return new WP_Error( 'sgr_bad_stub', 'unexpected stub value' );
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'sgr_no_ai_client', 'wp_ai_client_prompt is unavailable' );
		}

		try {
			$builder = \wp_ai_client_prompt( $prompt );

			if ( method_exists( $builder, 'using_temperature' ) ) {
				$builder = $builder->using_temperature( 0.2 );
			}
			if ( method_exists( $builder, 'using_max_tokens' ) ) {
				$builder = $builder->using_max_tokens( self::MAX_OUTPUT_TOKENS );
			}
			if ( method_exists( $builder, 'as_json_response' ) ) {
				$builder = $builder->as_json_response( $schema );
			}

			$raw = $builder->generate_text();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'sgr_ai_exception', $e->getMessage() );
		}

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		if ( ! is_string( $raw ) ) {
			return new WP_Error( 'sgr_ai_non_string', 'AI Client returned a non-string response' );
		}

		$decoded = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			return new WP_Error( 'sgr_ai_bad_json', 'AI Client returned invalid JSON' );
		}

		return $decoded;
	}

	/**
	 * Assemble the prompt. The AI Client builder we use doesn't expose a
	 * separate system-instruction method across all providers, so we embed
	 * the system role in the prompt text.
	 */
	private static function build_prompt( string $guide, string $content ): string {
		return "You are a strict style-guide linter. Find violations of the provided GUIDE within the CONTENT."
			. "\n- Analyse ONLY the CONTENT. Do not report rules that are not violated."
			. "\n- For each issue, populate offendingText with the exact substring from CONTENT that violates the rule."
			. "\n- If no violations are found, return an empty issues array and verdict=pass."
			. "\n- Respond with JSON that strictly matches the provided schema. No prose, no markdown."
			. "\n\nGUIDE:\n" . $guide
			. "\n\nCONTENT:\n" . $content;
	}

	/**
	 * Ensure a payload has the shape the sidebar expects before we hand it
	 * back (or cache it). Not a full JSON-Schema validator — defensive.
	 *
	 * @param array<string,mixed> $payload
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private static function validate_payload( array $payload ) {
		$verdict = $payload['verdict'] ?? null;
		if ( ! in_array( $verdict, [ 'pass', 'pass_warnings', 'fail' ], true ) ) {
			return new WP_Error( 'sgr_bad_verdict', 'missing or invalid verdict' );
		}

		$issues = $payload['issues'] ?? null;
		if ( ! is_array( $issues ) ) {
			return new WP_Error( 'sgr_bad_issues', 'issues is not an array' );
		}

		$clean = [];
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$severity = (string) ( $issue['severity'] ?? 'suggestion' );
			if ( ! in_array( $severity, [ 'critical', 'major', 'minor', 'suggestion' ], true ) ) {
				$severity = 'suggestion';
			}
			$clean[] = [
				'ruleId'        => (string) ( $issue['ruleId'] ?? '' ),
				'severity'      => $severity,
				'message'       => (string) ( $issue['message'] ?? '' ),
				'suggestion'    => (string) ( $issue['suggestion'] ?? '' ),
				'offendingText' => (string) ( $issue['offendingText'] ?? '' ),
			];
		}

		return [
			'verdict' => $verdict,
			'issues'  => $clean,
		];
	}

	/**
	 * Attach metadata for the sidebar (truncation flag, cache hit) without
	 * polluting the cached payload.
	 *
	 * @param array<string,mixed> $payload
	 */
	private static function decorate( array $payload, bool $truncated ): array {
		$payload['truncated'] = $truncated;
		return $payload;
	}

	/**
	 * Attach a response header so Playwright can assert cache behaviour.
	 * Guarded against headers-already-sent for defensive reasons.
	 */
	private static function mark_cache( bool $hit ): void {
		if ( ! headers_sent() ) {
			header( 'X-Sgr-Cache: ' . ( $hit ? 'hit' : 'miss' ) );
		}
	}

	/**
	 * Server-side logging hook. Providers' error bodies are sensitive;
	 * never surface them to the REST client.
	 */
	private static function log_error( WP_Error $error ): void {
		/**
		 * Fires when the review runner encounters an error worth logging.
		 *
		 * @param string   $code    WP_Error code.
		 * @param string   $message Full error message (may include provider details).
		 * @param WP_Error $error   The full error object.
		 */
		do_action( 'sgr_log_error', $error->get_error_code(), $error->get_error_message(), $error );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf( '[style-guide-reviewer] %s: %s', $error->get_error_code(), $error->get_error_message() )
			);
		}
	}
}
