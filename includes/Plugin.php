<?php
/**
 * Plugin bootstrap.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer;

use StyleGuideReviewer\Abilities\ReviewPostAbility;
use StyleGuideReviewer\Admin\GuideUploader;
use StyleGuideReviewer\Admin\SettingsPage;
use StyleGuideReviewer\Support\ResultCache;

defined( 'ABSPATH' ) || exit;

/**
 * Central wire-up. The main plugin file is only responsible for defining
 * constants, loading the autoloader, and calling self::register().
 */
final class Plugin {

	/**
	 * Option key that stores the installed plugin version. Used to run
	 * one-shot upgrade routines when the version bumps.
	 */
	public const VERSION_OPTION = 'sgr_plugin_version';

	/**
	 * Text domain for translation functions.
	 */
	public const TEXT_DOMAIN = 'style-guide-reviewer';

	/**
	 * Editor script handle (also used for wp_set_script_translations).
	 */
	public const EDITOR_HANDLE = 'sgr-editor';

	/**
	 * Wire up all plugin hooks.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'on_init' ] );
		add_action( 'plugins_loaded', [ self::class, 'maybe_upgrade' ] );
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_editor_assets' ] );
		add_action( 'save_post', [ ResultCache::class, 'invalidate_for_post' ], 10, 1 );

		SettingsPage::register();
		GuideUploader::register();
		ReviewPostAbility::register();
	}

	/**
	 * Handle init-time tasks (textdomain).
	 */
	public static function on_init(): void {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( SGR_FILE ) ) . '/languages'
		);
	}

	/**
	 * Run one-shot migrations when the installed version changes.
	 *
	 * Triggered on plugins_loaded so it runs after activation and on normal
	 * page loads. Idempotent: compares stored version to the current constant.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::VERSION_OPTION, '' );
		if ( SGR_VERSION === $installed ) {
			return;
		}

		// 1.x (POC) → 2.0 migration.
		if ( '' === $installed || version_compare( $installed, '2.0.0', '<' ) ) {
			self::upgrade_from_poc();
		}

		update_option( self::VERSION_OPTION, SGR_VERSION, false );
	}

	/**
	 * Rename POC options to their 2.0 equivalents and drop secrets.
	 */
	private static function upgrade_from_poc(): void {
		$legacy_guide = get_option( 'sgr_poc_guide_text', null );
		if ( null !== $legacy_guide && false !== $legacy_guide ) {
			$current = get_option( SettingsPage::GUIDE_OPTION, '' );
			if ( '' === $current ) {
				update_option( SettingsPage::GUIDE_OPTION, $legacy_guide, false );
			}
			delete_option( 'sgr_poc_guide_text' );
		}

		// 1.x stored a bare API key here. Drop it — 2.0 consumes the site's
		// configured AI provider via wp_ai_client_prompt() instead.
		delete_option( 'sgr_poc_openai' );
	}

	/**
	 * Register and enqueue editor assets, gated on AI availability.
	 */
	public static function enqueue_editor_assets(): void {
		$asset_file = SGR_PATH . 'build/editor.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		/** @var array{dependencies:string[],version:string} $asset */
		$asset = require $asset_file;

		wp_register_script(
			self::EDITOR_HANDLE,
			SGR_URL . 'build/editor.js',
			$asset['dependencies'],
			$asset['version'],
			[
				'in_footer' => true,
			]
		);

		wp_register_style(
			self::EDITOR_HANDLE,
			SGR_URL . 'build/editor.css',
			[],
			$asset['version']
		);

		wp_set_script_translations( self::EDITOR_HANDLE, self::TEXT_DOMAIN );

		wp_add_inline_script(
			self::EDITOR_HANDLE,
			'window.sgrEditor = ' . wp_json_encode(
				[
					'aiAvailable'  => self::is_ai_available(),
					'abilityId'    => 'sgr/review-post',
					'settingsUrl'  => admin_url( 'options-general.php?page=sgr-settings' ),
				]
			) . ';',
			'before'
		);

		wp_enqueue_script( self::EDITOR_HANDLE );
		wp_enqueue_style( self::EDITOR_HANDLE );
	}

	/**
	 * Determine whether the site has a working AI provider for text generation.
	 *
	 * Tolerates the AI Client not being installed (e.g., during activation on
	 * a not-yet-7.0 site or a stubbed test environment).
	 */
	public static function is_ai_available(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		try {
			$prompt = \wp_ai_client_prompt( '' );
			if ( ! is_object( $prompt ) || ! method_exists( $prompt, 'is_supported_for_text_generation' ) ) {
				return false;
			}
			return (bool) $prompt->is_supported_for_text_generation();
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
