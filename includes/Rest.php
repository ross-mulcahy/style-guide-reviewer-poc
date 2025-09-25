<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles the REST API endpoint for running a review.
 */
class SGR_POC_Rest {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register the /review REST route.
     */
    public function register_routes() {
        register_rest_route( 'sgr-poc/v1', '/review', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_review_request' ],
            'permission_callback' => [ $this, 'permission_check' ],
            'args'                => [
                'postId' => [
                    'required'          => true,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) && $param > 0;
                    },
                ],
            ],
        ] );
    }

    /**
     * Check if the current user has permission to perform the action.
     */
    public function permission_check( WP_REST_Request $request ) {
        $post_id = $request->get_param('postId');
        if ( ! $post_id ) {
            return new WP_Error( 'rest_bad_request', __( 'Missing postId.', 'sgr-poc' ), [ 'status' => 400 ] );
        }
        return current_user_can( 'edit_post', $post_id );
    }

    /**
     * Handle the review request.
     */
    public function handle_review_request( WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'postId' );

        // 1. Get the style guide.
        $guide_text = get_option( 'sgr_poc_guide_text' );
        if ( empty( trim( $guide_text ) ) ) {
            return new WP_Error(
                'no_guide_configured',
                __( 'No style guide has been configured in Settings > Style Guide Reviewer.', 'sgr-poc' ),
                [ 'status' => 400 ]
            );
        }

        // 2. Get and sanitize post content.
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', __( 'Post not found.', 'sgr-poc' ), [ 'status' => 404 ] );
        }
        $post_content = $post->post_content;
        $plain_text_content = wp_strip_all_tags( strip_shortcodes( $post_content ) );
        
        if ( empty( trim( $plain_text_content ) ) ) {
             return new WP_Error( 'empty_content', __( 'Post content is empty.', 'sgr-poc' ), [ 'status' => 400 ] );
        }

        // 3. Call OpenAI API.
        $result = SGR_POC_OpenAI::run_review( $guide_text, $plain_text_content );

        if ( is_wp_error( $result ) ) {
            return $result; // Pass the WP_Error through.
        }

        // 4. Return the successful response.
        return new WP_REST_Response( $result, 200 );
    }
}