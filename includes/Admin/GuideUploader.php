<?php
/**
 * Hardened .txt/.md file upload for the style guide.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the optional file-upload field on the settings page.
 */
final class GuideUploader {

	public const NONCE_ACTION = 'sgr_upload_guide';
	public const NONCE_NAME   = 'sgr_upload_guide_nonce';
	public const MAX_BYTES    = 1048576; // 1 MiB.
	public const FIELD_NAME   = 'sgr_guide_file';

	/**
	 * No-op for symmetry with other register() methods. Upload handling runs
	 * inline during settings-page rendering, not through an action hook.
	 */
	public static function register(): void {
		// Intentionally empty.
	}

	/**
	 * Validate and ingest an uploaded guide file, populating $_POST so the
	 * subsequent options.php handler persists its contents.
	 */
	public static function maybe_handle_request(): void {
		// 1) Capability check before touching $_FILES.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 2) Must be a POST to the options.php form *with* a chosen file.
		if ( empty( $_FILES[ self::FIELD_NAME ] ) || ! is_array( $_FILES[ self::FIELD_NAME ] ) ) {
			return;
		}

		// 3) Verify the upload nonce — rendered by SettingsPage::render_guide_field.
		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			add_settings_error( SettingsPage::GUIDE_OPTION, 'sgr_upload_nonce', __( 'Upload rejected: security check failed.', 'style-guide-reviewer' ) );
			return;
		}

		$file = array_map(
			static function ( $v ) {
				return is_scalar( $v ) ? sanitize_text_field( (string) $v ) : $v;
			},
			$_FILES[ self::FIELD_NAME ]
		);

		// Empty upload slot — user didn't pick a file. Silent no-op.
		if ( UPLOAD_ERR_NO_FILE === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return;
		}

		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 ) ) {
			add_settings_error( SettingsPage::GUIDE_OPTION, 'sgr_upload_error', __( 'File upload failed.', 'style-guide-reviewer' ) );
			return;
		}

		// 4) Size cap.
		$size = (int) ( $file['size'] ?? 0 );
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			add_settings_error( SettingsPage::GUIDE_OPTION, 'sgr_upload_size', __( 'Guide files must be 1 MB or smaller.', 'style-guide-reviewer' ) );
			return;
		}

		// 5) Extension + MIME validation using WordPress helpers — never
		// trust $file['type'], which is client-supplied.
		$tmp_name = (string) ( $file['tmp_name'] ?? '' );
		$name     = (string) ( $file['name'] ?? '' );
		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			add_settings_error( SettingsPage::GUIDE_OPTION, 'sgr_upload_tmp', __( 'Upload temp file missing.', 'style-guide-reviewer' ) );
			return;
		}

		$check = wp_check_filetype_and_ext(
			$tmp_name,
			$name,
			[
				'txt' => 'text/plain',
				'md'  => 'text/markdown',
			]
		);

		$ext = is_array( $check ) ? (string) ( $check['ext'] ?? '' ) : '';
		if ( ! in_array( $ext, [ 'txt', 'md' ], true ) ) {
			add_settings_error( SettingsPage::GUIDE_OPTION, 'sgr_upload_type', __( 'Only .txt or .md files are accepted.', 'style-guide-reviewer' ) );
			return;
		}

		// 6) Read via WP_Filesystem where available, fall back to a direct
		// read of the validated temp file.
		$contents = self::read_file_contents( $tmp_name );
		if ( null === $contents ) {
			add_settings_error( SettingsPage::GUIDE_OPTION, 'sgr_upload_read', __( 'Could not read uploaded file.', 'style-guide-reviewer' ) );
			return;
		}

		// Hand off to options.php. The register_setting sanitize_callback will
		// scrub it before persistence.
		$_POST[ SettingsPage::GUIDE_OPTION ] = $contents;
	}

	/**
	 * Read a file's text contents, preferring WP_Filesystem where initialised.
	 */
	private static function read_file_contents( string $path ): ?string {
		global $wp_filesystem;

		if ( function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( WP_Filesystem() && $wp_filesystem instanceof \WP_Filesystem_Base ) {
				$data = $wp_filesystem->get_contents( $path );
				if ( is_string( $data ) ) {
					return $data;
				}
			}
		}

		// Fallback: the path is already validated as an uploaded file above.
		$data = @file_get_contents( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false === $data ? null : $data;
	}
}
