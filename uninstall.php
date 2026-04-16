<?php
/**
 * Uninstall handler — purge all plugin data.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Plugin options.
delete_option( 'sgr_guide_text' );
delete_option( 'sgr_plugin_version' );

// Legacy POC options, in case an upgrade never ran.
delete_option( 'sgr_poc_guide_text' );
delete_option( 'sgr_poc_openai' );

// All per-post review caches.
$wpdb->query(
	$wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_sgr_review_cache' )
);

// All rate-limit transients (both the value and the timeout pair).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_sgr_rl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_sgr_rl_' ) . '%'
	)
);

// Multisite: run the same cleanup for every site when uninstalled network-wide.
if ( is_multisite() ) {
	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		delete_option( 'sgr_guide_text' );
		delete_option( 'sgr_plugin_version' );
		delete_option( 'sgr_poc_guide_text' );
		delete_option( 'sgr_poc_openai' );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_sgr_rl_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_sgr_rl_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_sgr_review_cache' )
		);
		restore_current_blog();
	}
}
