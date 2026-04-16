<?php
/**
 * Registers the `sgr/review-post` ability against the core Abilities API.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer\Abilities;

use StyleGuideReviewer\AI\ReviewRunner;
use StyleGuideReviewer\AI\ReviewSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Registration must happen on the Abilities API init hooks; categories first.
 * Using any other hook triggers `_doing_it_wrong` in WP 7.0+.
 */
final class ReviewPostAbility {

	public const CATEGORY_ID = 'sgr';
	public const ABILITY_ID  = 'sgr/review-post';

	/**
	 * Hook category + ability registration.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_categories_init', [ self::class, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ self::class, 'register_ability' ] );
	}

	/**
	 * Register the ability category. Must run *before* the ability itself.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		\wp_register_ability_category(
			self::CATEGORY_ID,
			[
				'label'       => __( 'Style Guide Reviewer', 'style-guide-reviewer' ),
				'description' => __( 'Abilities provided by the Style Guide Reviewer plugin.', 'style-guide-reviewer' ),
			]
		);
	}

	/**
	 * Register the ability itself.
	 */
	public static function register_ability(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		\wp_register_ability(
			self::ABILITY_ID,
			[
				'label'               => __( 'Review post against style guide', 'style-guide-reviewer' ),
				'description'         => __(
					'Analyse a post and return structured style-guide violations, grouped by severity.',
					'style-guide-reviewer'
				),
				'category'            => self::CATEGORY_ID,
				'input_schema'        => ReviewSchema::input_schema(),
				'output_schema'       => ReviewSchema::get(),
				'permission_callback' => [ self::class, 'permission_check' ],
				'callback'            => [ ReviewRunner::class, 'run' ],
				'meta'                => [
					'show_in_rest' => true,
					'readonly'     => true,
				],
			]
		);
	}

	/**
	 * Capability check: the caller must be able to edit the target post.
	 *
	 * @param array<string,mixed> $input Ability input.
	 */
	public static function permission_check( $input ): bool {
		$post_id = 0;
		if ( is_array( $input ) && isset( $input['postId'] ) ) {
			$post_id = (int) $input['postId'];
		}
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}
}
