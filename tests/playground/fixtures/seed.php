<?php
/**
 * Seed step for Playground blueprints: create a sample post and pre-populate
 * the plugin's options so reviewers can click Review immediately.
 */

require_once '/wordpress/wp-load.php';

update_option(
	'sgr_guide_text',
	"- Never use the word \"synergy\".\n"
	. "- Write numbers below 10 as words.\n"
	. "- Prefer \"use\" over \"leverage\".\n"
	. "- Be concise and concrete.\n",
	false
);

$existing = get_posts(
	[
		'post_type'   => 'post',
		'post_status' => 'any',
		'name'        => 'sgr-sample-post',
		'numberposts' => 1,
		'fields'      => 'ids',
	]
);

if ( empty( $existing ) ) {
	$content = file_get_contents( __DIR__ . '/sample-post.html' );
	$post_id = wp_insert_post(
		[
			'post_title'   => 'Style Guide Reviewer sample post',
			'post_name'    => 'sgr-sample-post',
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'post',
		]
	);
} else {
	$post_id = (int) $existing[0];
}

// Surface the post ID so the blueprint can land the user on its edit screen.
file_put_contents( '/internal/sample-post-id', (string) $post_id );
