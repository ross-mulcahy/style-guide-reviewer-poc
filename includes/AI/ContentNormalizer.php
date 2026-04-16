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
		$stripped = wp_strip_all_tags( strip_shortcodes( $raw ) );
		$stripped = (string) preg_replace( "/[\r\n]{3,}/", "\n\n", $stripped );
		$stripped = trim( $stripped );

		if ( $max_chars <= 0 || strlen( $stripped ) <= $max_chars ) {
			return [
				'text'      => $stripped,
				'truncated' => false,
			];
		}

		// Trim to the last sentence boundary within the max window.
		$window = substr( $stripped, 0, $max_chars );

		$boundary = max(
			(int) strrpos( $window, '. ' ),
			(int) strrpos( $window, "\n" ),
			(int) strrpos( $window, '! ' ),
			(int) strrpos( $window, '? ' )
		);

		if ( $boundary > (int) ( $max_chars * 0.5 ) ) {
			$window = substr( $window, 0, $boundary + 1 );
		}

		return [
			'text'      => rtrim( $window ),
			'truncated' => true,
		];
	}
}
