<?php
/**
 * Single source of truth for the review result schema.
 *
 * @package StyleGuideReviewer
 */

declare( strict_types=1 );

namespace StyleGuideReviewer\AI;

defined( 'ABSPATH' ) || exit;

/**
 * The JSON Schema describing the structured review result. Shared by the
 * ability's `output_schema` and the AI Client's `as_json_response()` call,
 * so drift is not possible.
 */
final class ReviewSchema {

	/**
	 * Return the shared output schema.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'verdict', 'issues' ],
			'properties'           => [
				'verdict' => [
					'type' => 'string',
					'enum' => [ 'pass', 'pass_warnings', 'fail' ],
				],
				'issues'  => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'ruleId', 'severity', 'message', 'suggestion', 'offendingText' ],
						'properties'           => [
							'ruleId'        => [ 'type' => 'string' ],
							'severity'      => [
								'type' => 'string',
								'enum' => [ 'critical', 'major', 'minor', 'suggestion' ],
							],
							'message'       => [ 'type' => 'string' ],
							'suggestion'    => [ 'type' => 'string' ],
							'offendingText' => [
								'type'        => 'string',
								'description' => 'The exact substring from the content that violates the rule.',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * The ability's input schema.
	 *
	 * @return array<string,mixed>
	 */
	public static function input_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'postId' ],
			'properties'           => [
				'postId' => [
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'ID of the post to review.',
				],
			],
		];
	}
}
