<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SGR_POC_OpenAI {

    const API_ENDPOINT = 'https://api.openai.com/v1/responses';

    public static function run_review( $guide_text, $post_text ) {
        $options = get_option( 'sgr_poc_openai', [] );
        $api_key = $options['apiKey'] ?? '';
        $model   = ! empty( $options['model'] ) ? $options['model'] : 'gpt-4.1-mini';

        if ( empty( $api_key ) ) {
            return new WP_Error('api_key_missing', __('OpenAI API key is not configured.', 'sgr-poc'), [ 'status' => 500 ]);
        }

        $json_schema_details = self::get_json_schema();

        // UPDATED: A final, even stricter system prompt.
        $system_prompt = "You are a strict style guide linter. Your task is to find violations of the provided GUIDE within the CONTENT."
            . "\n- Analyze ONLY the provided CONTENT. Do not report on rules that are not violated."
            . "\n- For each issue found, you MUST populate the 'offendingText' field with the exact text from the CONTENT that is in violation. This is mandatory."
            . "\n- If no violations are found, you MUST return an empty 'issues' array."
            . "\n- Adhere strictly to the JSON schema and do not add any commentary.";

        $body = [
            'model'       => $model,
            'input'       => [
                [
                    'role'    => 'system',
                    'content' => $system_prompt,
                ],
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => "GUIDE (normalized):\n{$guide_text}\n\nCONTENT:\n{$post_text}\n\nReturn JSON only.",
                        ],
                    ],
                ],
            ],
            'temperature' => 0.2,
            'text'        => [
                'format' => [
                    'type'   => $json_schema_details['type'],
                    'name'   => $json_schema_details['name'],
                    'schema' => $json_schema_details['schema'],
                    'strict' => $json_schema_details['strict'],
                ],
            ],
            'max_output_tokens' => 800,
        ];

        $response = wp_remote_post( self::API_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 120,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return new WP_Error('api_request_failed', __('Failed to connect to OpenAI API.', 'sgr-poc'), [ 'status' => 502 ]);
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error('api_error', sprintf(__('OpenAI API returned an error (HTTP %d): %s', 'sgr-poc'), $code, $raw), [ 'status' => $code ]);
        }

        $data = json_decode( $raw, true );
        
        $structured_output_text = null;
        if ( isset($data['output'][0]['content']) && is_array($data['output'][0]['content']) ) {
            foreach ( $data['output'][0]['content'] as $item ) {
                if ( isset($item['type']) && $item['type'] === 'output_text' && isset($item['text']) ) {
                    $structured_output_text = $item['text'];
                    break;
                }
            }
        }

        if ( ! $structured_output_text ) {
            return new WP_Error('api_malformed_response', __('OpenAI API response was missing the expected structured output.', 'sgr-poc'), [ 'status' => 502, 'body' => $raw ]);
        }

        $result = json_decode( $structured_output_text, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error('api_invalid_json', __('OpenAI returned invalid JSON in its structured output.', 'sgr-poc'), [ 'status' => 502, 'body' => $structured_output_text ]);
        }

        return $result;
    }

    private static function get_json_schema() {
        return [
            'type'   => 'json_schema',
            'name'   => 'StyleGuideReviewResult',
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
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
                            'properties'           => [
                                'ruleId'        => [ 'type' => 'string' ],
                                'severity'      => [ 'type' => 'string', 'enum' => [ 'critical', 'major', 'minor', 'suggestion' ] ],
                                'message'       => [ 'type' => 'string' ],
                                'suggestion'    => [ 'type' => 'string' ], // now required below
                                'start'         => [ 'type' => 'integer' ],
                                'end'           => [ 'type' => 'integer' ],
                                'offendingText' => [
                                    'type'        => 'string',
                                    'description' => 'The exact substring from the content that violates the rule.',
                                ],
                            ],
                            // IMPORTANT: include EVERY key from properties here when strict=true
                            'required' => [ 'ruleId', 'severity', 'message', 'suggestion', 'start', 'end', 'offendingText' ],
                        ],
                    ],
                ],
                'required' => [ 'verdict', 'issues' ],
            ],
        ];
    }
}