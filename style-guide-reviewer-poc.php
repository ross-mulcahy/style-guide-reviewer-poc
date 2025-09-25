<?php
/**
 * Plugin Name:       Style Guide Reviewer (POC)
 * Description:       A proof-of-concept plugin to review post content against a style guide using the OpenAI API.
 * Version:           1.0.0
 * Author:            AI Engineer
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sgr-poc
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'SGR_POC_VERSION', '1.0.0' );
define( 'SGR_POC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SGR_POC_URL', plugin_dir_url( __FILE__ ) );

// Include required files.
require_once SGR_POC_PATH . 'includes/Admin.php';
require_once SGR_POC_PATH . 'includes/OpenAI.php';
require_once SGR_POC_PATH . 'includes/Rest.php';

/**
 * Main plugin class to initialize everything.
 */
class Style_Guide_Reviewer_POC {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'init' ] );
    }

    /**
     * Initialize the plugin components.
     */
    public function init() {
        new SGR_POC_Admin();
        new SGR_POC_Rest();

        $this->register_editor_assets();
    }

    /**
     * Register scripts and styles for the Gutenberg editor.
     */
    public function register_editor_assets() {
        $asset_file = include( SGR_POC_PATH . 'build/editor.asset.php' );

        wp_register_script(
            'sgr-poc-editor-script',
            SGR_POC_URL . 'build/editor.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_register_style(
            'sgr-poc-editor-style',
            SGR_POC_URL . 'build/editor.css',
            [],
            $asset_file['version']
        );

        // Enqueue only on post/page editor screens.
        add_action( 'enqueue_block_editor_assets', function() {
            wp_enqueue_script( 'sgr-poc-editor-script' );
            wp_enqueue_style( 'sgr-poc-editor-style' );
        } );
    }
}

// Instantiate the plugin.
new Style_Guide_Reviewer_POC();