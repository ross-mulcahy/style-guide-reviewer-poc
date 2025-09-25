<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles the admin settings page for the plugin.
 */
class SGR_POC_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Add the settings page to the admin menu under "Settings".
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Style Guide Reviewer', 'sgr-poc' ),
            __( 'Style Guide Reviewer', 'sgr-poc' ),
            'manage_options',
            'sgr-poc-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register plugin settings using the Settings API.
     */
    public function register_settings() {
        // Register the guide text setting.
        register_setting( 'sgr-poc-settings-group', 'sgr_poc_guide_text', [
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
        ] );

        // Register the OpenAI settings.
        register_setting( 'sgr-poc-settings-group', 'sgr_poc_openai', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_openai_options' ],
        ] );

        // Settings Section.
        add_settings_section(
            'sgr-poc-main-section',
            __( 'Plugin Configuration', 'sgr-poc' ),
            null,
            'sgr-poc-settings'
        );

        // Settings Fields.
        add_settings_field(
            'sgr_poc_guide_text',
            __( 'Brand Style Guide', 'sgr-poc' ),
            [ $this, 'render_guide_text_field' ],
            'sgr-poc-settings',
            'sgr-poc-main-section'
        );

        add_settings_field(
            'sgr_poc_openai_api_key',
            __( 'OpenAI API Key', 'sgr-poc' ),
            [ $this, 'render_openai_api_key_field' ],
            'sgr-poc-settings',
            'sgr-poc-main-section'
        );

        add_settings_field(
            'sgr_poc_openai_model',
            __( 'OpenAI Model', 'sgr-poc' ),
            [ $this, 'render_openai_model_field' ],
            'sgr-poc-settings',
            'sgr-poc-main-section'
        );
    }

    /**
     * Sanitize the OpenAI settings array.
     */
    public function sanitize_openai_options( $input ) {
        $output = get_option( 'sgr_poc_openai', [] );
        $output['apiKey'] = isset( $input['apiKey'] ) ? sanitize_text_field( $input['apiKey'] ) : '';
        $output['model'] = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : 'gpt-4.1-mini';
        return $output;
    }

    /**
     * Render the main settings page wrapper.
     */
    public function render_settings_page() {
        $this->handle_file_upload();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Style Guide Reviewer (POC)', 'sgr-poc' ); ?></h1>
            <form method="post" action="options.php" enctype="multipart/form-data">
                <?php
                settings_fields( 'sgr-poc-settings-group' );
                do_settings_sections( 'sgr-poc-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the textarea for the style guide.
     */
    public function render_guide_text_field() {
        $guide_text = get_option( 'sgr_poc_guide_text', '' );
        ?>
        <p><?php esc_html_e('Paste your style guide below, or upload a .txt/.md file.', 'sgr-poc'); ?></p>
        <textarea name="sgr_poc_guide_text" id="sgr_poc_guide_text" rows="15" class="large-text"><?php echo esc_textarea( $guide_text ); ?></textarea>
        <br>
        <label for="sgr_poc_guide_file"><?php esc_html_e('Upload File (will overwrite text above on Save):', 'sgr-poc'); ?></label>
        <input type="file" name="sgr_poc_guide_file" id="sgr_poc_guide_file" accept=".txt,.md">
        <?php
    }

    /**
     * Render the API key input field.
     */
    public function render_openai_api_key_field() {
        $options = get_option( 'sgr_poc_openai', [] );
        $api_key = isset( $options['apiKey'] ) ? $options['apiKey'] : '';
        ?>
        <input type="password" name="sgr_poc_openai[apiKey]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('Your OpenAI API key is stored in your database and never exposed to the browser.', 'sgr-poc'); ?></p>
        <?php
    }

    /**
     * Render the Model input field.
     */
    public function render_openai_model_field() {
        $options = get_option( 'sgr_poc_openai', [] );
        $model = isset( $options['model'] ) && ! empty( $options['model'] ) ? $options['model'] : 'gpt-4.1-mini';
        ?>
        <input type="text" name="sgr_poc_openai[model]" value="<?php echo esc_attr( $model ); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('e.g., gpt-4.1-mini, gpt-4o, etc.', 'sgr-poc'); ?></p>
        <?php
    }

    /**
     * Handle the .txt/.md file upload for the style guide.
     */
    private function handle_file_upload() {
        if ( ! isset( $_FILES['sgr_poc_guide_file'] ) || $_FILES['sgr_poc_guide_file']['error'] !== UPLOAD_ERR_OK ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $file = $_FILES['sgr_poc_guide_file'];
        $allowed_types = [ 'text/plain', 'text/markdown' ];

        if ( in_array( $file['type'], $allowed_types, true ) ) {
            $content = file_get_contents( $file['tmp_name'] );
            if ( $content !== false ) {
                // We update the option directly and then let the standard save process overwrite it if the textarea was also changed.
                // This is a simple POC approach.
                $_POST['sgr_poc_guide_text'] = $content;
            }
        }
    }
}