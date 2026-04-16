<?php
/**
 * AI Client stub for Playground-based tests and demos.
 *
 * Short-circuits the Style Guide Reviewer's AI call via the
 * `sgr_pre_ai_generate` filter so reviewers and CI never need real AI
 * credentials. The scenario is chosen per-request via the
 * X-SGR-STUB-SCENARIO header; default is `default`.
 *
 * Ships only in the Playground blueprints — excluded from the release zip.
 *
 * @package StyleGuideReviewer\Tests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pretend an AI provider is configured so the sidebar renders enabled.
 * Core `wp_ai_client_prompt()` feature-detection is emulated by the stub.
 */
if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	/**
	 * @param string $prompt Prompt text (ignored by the stub).
	 */
	function wp_ai_client_prompt( $prompt ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return new \Sgr_Stub_Prompt_Builder( (string) $prompt );
	}
}

if ( ! class_exists( 'Sgr_Stub_Prompt_Builder' ) ) {
	/**
	 * Minimal fluent builder used only when the real AI Client isn't loaded
	 * (i.e., Playground running the reviewer blueprint without a connector).
	 */
	class Sgr_Stub_Prompt_Builder { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace

		private string $prompt;

		public function __construct( string $prompt ) {
			$this->prompt = $prompt;
		}

		public function using_temperature( float $value ): self { return $this; }
		public function using_max_tokens( int $value ): self { return $this; }
		public function as_json_response( array $schema ): self { return $this; }

		public function is_supported_for_text_generation(): bool {
			return 'unsupported' !== sgr_stub_scenario();
		}

		public function generate_text() {
			// The Runner short-circuits via sgr_pre_ai_generate before this
			// point when the stub is installed. If we got here, it means the
			// filter wasn't applied — return the stub's canned payload anyway.
			return wp_json_encode( sgr_stub_canned_payload() );
		}
	}
}

add_filter(
	'sgr_pre_ai_generate',
	/**
	 * @param mixed $pre    Pass-through (null = no override).
	 * @param string $prompt Full prompt text (unused).
	 * @param array  $schema JSON Schema the runner requested (unused).
	 * @return mixed
	 */
	static function ( $pre, $prompt, $schema ) {
		$scenario = sgr_stub_scenario();

		if ( 'error' === $scenario ) {
			return new \WP_Error( 'ai_provider_unavailable', 'Stubbed: provider unavailable' );
		}

		return sgr_stub_canned_payload( $scenario );
	},
	10,
	3
);

// Test-only REST endpoints under sgr-test/v1. Provide a way for Playwright
// specs to tweak runtime state (e.g. cap the rate limit to 2/min) without
// needing WP-CLI or an extra PHP helper file.
add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'sgr-test/v1',
			'/set-rate-limit',
			[
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function ( \WP_REST_Request $req ) {
					$limit = (int) $req->get_param( 'limit' );
					update_option( 'sgr_test_rate_limit', $limit, false );
					return [ 'limit' => $limit ];
				},
			]
		);

		register_rest_route(
			'sgr-test/v1',
			'/clear-post-cache',
			[
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function ( \WP_REST_Request $req ) {
					$post_id = (int) $req->get_param( 'postId' );
					if ( $post_id > 0 ) {
						delete_post_meta( $post_id, '_sgr_review_cache' );
					}
					return [ 'cleared' => $post_id ];
				},
			]
		);

		register_rest_route(
			'sgr-test/v1',
			'/legacy-options',
			[
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function ( \WP_REST_Request $req ) {
					$guide = (string) $req->get_param( 'guide' );
					$api   = (string) $req->get_param( 'apiKey' );
					update_option( 'sgr_poc_guide_text', $guide, false );
					update_option( 'sgr_poc_openai', [ 'apiKey' => $api ], false );
					delete_option( 'sgr_plugin_version' );
					return [ 'seeded' => true ];
				},
			]
		);

		register_rest_route(
			'sgr-test/v1',
			'/option',
			[
				'methods'             => 'GET',
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function ( \WP_REST_Request $req ) {
					$name = (string) $req->get_param( 'name' );
					$allowed = [ 'sgr_guide_text', 'sgr_poc_guide_text', 'sgr_poc_openai', 'sgr_plugin_version' ];
					if ( ! in_array( $name, $allowed, true ) ) {
						return new \WP_Error( 'disallowed', 'option not allowed', [ 'status' => 400 ] );
					}
					return [ 'value' => get_option( $name, null ) ];
				},
			]
		);

		register_rest_route(
			'sgr-test/v1',
			'/run-uninstall',
			[
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'delete_plugins' );
				},
				'callback'            => static function () {
					if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
						define( 'WP_UNINSTALL_PLUGIN', 'style-guide-reviewer/style-guide-reviewer.php' );
					}
					$path = WP_PLUGIN_DIR . '/style-guide-reviewer/uninstall.php';
					if ( ! is_readable( $path ) ) {
						return new \WP_Error( 'missing', 'uninstall.php not readable at ' . $path, [ 'status' => 500 ] );
					}
					include $path;
					return [ 'ran' => true ];
				},
			]
		);

		register_rest_route(
			'sgr-test/v1',
			'/reset-rate-limit',
			[
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => static function () {
					delete_option( 'sgr_test_rate_limit' );
					$user_id = get_current_user_id();
					if ( class_exists( '\\StyleGuideReviewer\\Support\\RateLimiter' ) ) {
						\StyleGuideReviewer\Support\RateLimiter::reset( $user_id );
					}
					return [ 'ok' => true ];
				},
			]
		);
	}
);

add_filter(
	'sgr_rate_limit_per_minute',
	static function ( $default ) {
		$override = get_option( 'sgr_test_rate_limit', null );
		return null === $override ? $default : (int) $override;
	}
);

/**
 * Resolve the active stub scenario from request headers.
 */
function sgr_stub_scenario(): string { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	$header = isset( $_SERVER['HTTP_X_SGR_STUB_SCENARIO'] )
		? strtolower( sanitize_key( (string) wp_unslash( $_SERVER['HTTP_X_SGR_STUB_SCENARIO'] ) ) )
		: '';
	$allowed = [ 'default', 'no_issues', 'error', 'unsupported', 'rate_limited' ];
	return in_array( $header, $allowed, true ) ? $header : 'default';
}

/**
 * Canned review payload for a given scenario. Valid against ReviewSchema.
 *
 * @return array<string,mixed>
 */
function sgr_stub_canned_payload( ?string $scenario = null ): array { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	$scenario = $scenario ?? sgr_stub_scenario();

	if ( 'no_issues' === $scenario ) {
		return [
			'verdict' => 'pass',
			'issues'  => [],
		];
	}

	return [
		'verdict' => 'fail',
		'issues'  => [
			[
				'ruleId'        => 'BANNED_WORDS',
				'severity'      => 'critical',
				'message'       => 'The word "synergy" is banned by the style guide.',
				'suggestion'    => 'Remove "synergy" and use "collaboration" or a concrete verb.',
				'offendingText' => 'synergy',
			],
			[
				'ruleId'        => 'NUMBER_SPELLING',
				'severity'      => 'minor',
				'message'       => 'Numbers below 10 should be spelled out.',
				'suggestion'    => 'Replace "3 teams" with "three teams".',
				'offendingText' => '3 teams',
			],
			[
				'ruleId'        => 'TONE',
				'severity'      => 'minor',
				'message'       => 'Avoid unnecessary jargon when plainer words work.',
				'suggestion'    => 'Say "use" instead of "leverage".',
				'offendingText' => 'leverage',
			],
		],
	];
}
