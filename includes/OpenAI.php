<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SGR_POC_OpenAI {

    const API_ENDPOINT = 'https://api.openai.com/v1/responses';
    const MAX_INPUT_CHARS = 50000; // simple POC guard

 /**
     * Call the OpenAI API to review content against a guide.
     *
     * @param string $guide_text The brand style guide.
     * @param string $post_text  The plain text content of the post.
     * @return array|WP_Error The decoded JSON response or a WP_Error on failure.
     */
    public static function run_review( $guide_text, $post_text ) {
        $options = get_option( 'sgr_poc_openai', [] );
        $api_key = $options['apiKey'] ?? '';
        $model = ! empty( $options['model'] ) ? $options['model'] : 'gpt-4.1-mini';

        if ( empty( $api_key ) ) {
            return new WP_Error(
                'api_key_missing',
                __( 'OpenAI API key is not configured.', 'sgr-poc' ),
                [ 'status' => 500 ]
            );
        }

        $json_schema_details = self::get_json_schema();

        // UPDATED: A much stricter system prompt to reduce hallucinations.
        $system_prompt = "You are a strict style guide linter. Your task is to find violations of the provided GUIDE within the CONTENT."
            . "\n- Analyze ONLY the provided CONTENT. Do not invent issues or suggest improvements for things that are not present."
            . "\n- For each issue found, the 'start' and 'end' character positions MUST correspond to the actual text in the CONTENT."
            . "\n- If no violations are found, you MUST return an empty 'issues' array."
            . "\n- Adhere strictly to the JSON schema and do not add any commentary.";

        $body = [
            'model'           => $model,
            'input'           => [
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
            'temperature'     => 0.2,
            'text'            => [
                'format' => [
                    'type'   => 'json_schema',
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

        // ... the rest of the function remains the same ...
        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_request_failed',
                __( 'Failed to connect to OpenAI API.', 'sgr-poc' ),
                [ 'status' => 502 ]
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ( $response_code !== 200 ) {
             return new WP_Error(
                'api_error',
                sprintf(
                    __( 'OpenAI API returned an error (HTTP %d): %s', 'sgr-poc' ),
                    $response_code,
                    $response_body
                ),
                [ 'status' => $response_code ]
            );
        }
        
        $data = json_decode($response_body, true);
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
             return new WP_Error(
                'api_malformed_response',
                __( 'OpenAI API response was missing the expected structured output.', 'sgr-poc' ),
                [ 'status' => 502, 'body' => $response_body ]
            );
        }

        $result = json_decode($structured_output_text, true);

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'api_invalid_json',
                __( 'OpenAI returned invalid JSON in its structured output.', 'sgr-poc' ),
                [ 'status' => 502, 'body' => $structured_output_text ]
            );
        }

        return $result;
    }

    private static function get_json_schema() {
        return [
            'name'   => 'StyleGuideReviewResult',
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => [
                    'verdict' => [ 'type' => 'string', 'enum' => [ 'pass', 'pass_warnings', 'fail' ] ],
                    'issues'  => [
                        'type'  => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'properties'           => [
                                'ruleId'     => [ 'type' => 'string' ],
                                'severity'   => [ 'type' => 'string', 'enum' => [ 'critical', 'major', 'minor', 'suggestion' ] ],
                                'message'    => [ 'type' => 'string' ],
                                'suggestion' => [ 'type' => 'string' ],
                                'start'      => [ 'type' => 'integer', 'minimum' => 0 ],
                                'end'        => [ 'type' => 'integer', 'minimum' => 0 ],
                            ],
                            'required' => [ 'ruleId', 'severity', 'message', 'suggestion', 'start', 'end' ],
                        ],
                    ],
                    'scores'  => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'properties'           => [
                            'readability' => [ 'type' => 'number' ],
                            'consistency' => [ 'type' => 'number' ],
                        ],
                        'required' => [ 'readability', 'consistency' ],
                    ],
                ],
                'required' => [ 'verdict', 'issues', 'scores' ],
            ],
            'strict' => true,
        ];
    }
}