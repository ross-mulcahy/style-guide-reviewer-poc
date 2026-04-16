<?php
/**
 * Plugin Name:       Style Guide Reviewer
 * Plugin URI:        https://github.com/example/style-guide-reviewer
 * Description:       Review post content against a brand style guide using the WordPress AI Client and Abilities API.
 * Version:           2.0.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Style Guide Reviewer Contributors
 * Author URI:        https://github.com/example/style-guide-reviewer
 * Update URI:        false
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       style-guide-reviewer
 * Domain Path:       /languages
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'SGR_VERSION', '2.0.0' );
define( 'SGR_FILE', __FILE__ );
define( 'SGR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SGR_URL', plugin_dir_url( __FILE__ ) );

// Composer-generated autoloader is preferred; fall back to a simple PSR-4 loader
// so the plugin still works when shipped to wp.org (where composer install is not run).
if ( file_exists( SGR_PATH . 'vendor/autoload.php' ) ) {
	require_once SGR_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			if ( 0 !== strpos( $class_name, 'StyleGuideReviewer\\' ) ) {
				return;
			}
			$relative = substr( $class_name, strlen( 'StyleGuideReviewer\\' ) );
			$file     = SGR_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

\StyleGuideReviewer\Plugin::register();
