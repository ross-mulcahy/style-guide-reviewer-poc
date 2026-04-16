<?php
/**
 * Post-content normalisation and safe length-capping.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Strips HTML/shortcodes and trims oversize content at a sentence boundary.
 */
final class ContentNormalizer {

	/**
	 * Default max length passed to the AI model. Large enough for a ~6-8k
	 * word essay, small enough to keep a single request well under common
	 * model context windows.
	 */
	public const DEFAULT_MAX_CHARS = 40000;

	/**
	 * @param string $raw       Raw post_content (may include blocks/shortcodes).
	 * @param int    $max_chars Maximum characters to return.
	 *
	 * @return array{text:string,truncated:bool}
	 */
	public static function normalize( string $raw, int $max_chars = self::DEFAULT_MAX_CHARS ): array {
		$max_chars = (int) apply_filters( 'sgr_max_review_content_chars', $max_chars, $raw );

		$stripped = wp_strip_all_tags( strip_shortcodes( $raw ) );
		$stripped = (string) preg_replace( "/[\r\n]{3,}/", "\n\n", $stripped );
		$stripped = trim( $stripped );
		$stripped = (string) apply_filters( 'sgr_normalized_review_content', $stripped, $raw, $max_chars );

		if ( $max_chars <= 0 || self::length( $stripped ) <= $max_chars ) {
			return [
				'text'      => $stripped,
				'truncated' => false,
			];
		}

		// Trim to the last sentence boundary within the max window.
		$window = self::slice( $stripped, 0, $max_chars );

		$boundary = max(
			(int) self::last_position( $window, '. ' ),
			(int) self::last_position( $window, "\n" ),
			(int) self::last_position( $window, '! ' ),
			(int) self::last_position( $window, '? ' )
		);

		if ( $boundary > (int) ( $max_chars * 0.5 ) ) {
			$window = self::slice( $window, 0, $boundary + 1 );
		}

		return [
			'text'      => rtrim( $window ),
			'truncated' => true,
		];
	}

	/**
	 * Measure string length with UTF-8 support when available.
	 */
	private static function length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}

		return strlen( $value );
	}

	/**
	 * Slice a string with UTF-8 support when available.
	 */
	private static function slice( string $value, int $start, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $value, $start, $length, 'UTF-8' );
		}

		return (string) substr( $value, $start, $length );
	}

	/**
	 * Find the last position of a needle with UTF-8 support when available.
	 */
	private static function last_position( string $haystack, string $needle ): int|false {
		if ( function_exists( 'mb_strrpos' ) ) {
			return mb_strrpos( $haystack, $needle, 0, 'UTF-8' );
		}

		return strrpos( $haystack, $needle );
	}
}
