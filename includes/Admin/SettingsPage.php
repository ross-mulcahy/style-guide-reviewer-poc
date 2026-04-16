<?php
/**
 * Settings page — brand style guide text only. The AI provider is managed
 * by the site admin through the WordPress AI Client, not this plugin.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer\Admin;

use StyleGuideReviewer\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Settings → Style Guide Reviewer.
 */
final class SettingsPage {

	/**
	 * Option key for the brand style guide text.
	 */
	public const GUIDE_OPTION = 'sgr_guide_text';

	/**
	 * Slug for the options page and settings group.
	 */
	public const PAGE_SLUG = 'sgr-settings';

	/**
	 * Hook all actions needed for the Settings page.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	/**
	 * Add the options-general menu entry.
	 */
	public static function add_menu(): void {
		add_options_page(
			__( 'Style Guide Reviewer', 'style-guide-reviewer' ),
			__( 'Style Guide Reviewer', 'style-guide-reviewer' ),
			'manage_options',
			self::PAGE_SLUG,
			[ self::class, 'render' ]
		);
	}

	/**
	 * Register settings, fields, and sections.
	 */
	public static function register_settings(): void {
		register_setting(
			self::PAGE_SLUG . '-group',
			self::GUIDE_OPTION,
			[
				'type'              => 'string',
				'sanitize_callback' => [ self::class, 'sanitize_guide' ],
				'show_in_rest'      => false,
				'default'           => '',
				'autoload'          => false,
			]
		);

		add_settings_section(
			'sgr-main-section',
			__( 'Brand style guide', 'style-guide-reviewer' ),
			[ self::class, 'render_section_intro' ],
			self::PAGE_SLUG
		);

		add_settings_field(
			self::GUIDE_OPTION,
			__( 'Style guide', 'style-guide-reviewer' ),
			[ self::class, 'render_guide_field' ],
			self::PAGE_SLUG,
			'sgr-main-section'
		);
	}

	/**
	 * Sanitize guide text — free-form prose with basic HTML stripped.
	 *
	 * @param mixed $value Raw input from the settings form.
	 */
	public static function sanitize_guide( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		// wp_kses_post permits markup an editor might paste from a Word doc but
		// strips script/style/unsafe tags. The guide is never rendered to the
		// front-end, so this is generous by design.
		return wp_kses_post( wp_unslash( $value ) );
	}

	/**
	 * Section intro copy.
	 */
	public static function render_section_intro(): void {
		echo '<p>' . esc_html__(
			'Paste your brand style guide below, or upload a .txt or .md file. Reviews use whichever AI provider is configured for your site via the WordPress AI Client.',
			'style-guide-reviewer'
		) . '</p>';

		if ( ! Plugin::is_ai_available() ) {
			echo '<div class="notice notice-warning inline"><p>';
			echo esc_html__( 'No AI provider is currently configured. Install and configure an AI connector plugin to enable reviews.', 'style-guide-reviewer' );
			echo '</p></div>';
		}
	}

	/**
	 * Render the guide textarea + file upload input.
	 */
	public static function render_guide_field(): void {
		$guide = (string) get_option( self::GUIDE_OPTION, '' );

		printf(
			'<textarea name="%1$s" id="%1$s" rows="15" class="large-text code">%2$s</textarea>',
			esc_attr( self::GUIDE_OPTION ),
			esc_textarea( $guide )
		);

		echo '<p><label for="sgr_guide_file">';
		esc_html_e( 'Upload a .txt or .md file (replaces text above on save):', 'style-guide-reviewer' );
		echo '</label> ';
		echo '<input type="file" name="sgr_guide_file" id="sgr_guide_file" accept=".txt,.md,text/plain,text/markdown">';
		echo '</p>';

		// Nonce specific to the upload handler. register_setting's own nonce
		// covers option saves; this one protects the file handoff.
		wp_nonce_field( GuideUploader::NONCE_ACTION, GuideUploader::NONCE_NAME );
	}

	/**
	 * Render the page wrapper.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Run the file upload handler *before* do_settings_sections, so an
		// uploaded file pre-populates $_POST ahead of options.php processing.
		GuideUploader::maybe_handle_request();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Style Guide Reviewer', 'style-guide-reviewer' ); ?></h1>
			<form method="post" action="options.php" enctype="multipart/form-data">
				<?php
				settings_fields( self::PAGE_SLUG . '-group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
