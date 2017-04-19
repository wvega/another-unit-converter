<?php

class AUCP_Resources {

    private $plugin_url;

    public function __construct( $plugin_url ) {
        $this->plugin_url = $plugin_url;
    }

    public function register_scripts_and_styles() {
        wp_register_script(
            'another-unit-converter-frontend',
            $this->plugin_url . 'resources/js/frontend.js',
            array( 'jquery-ui-dialog' ),
            false,
            true
        );

        wp_register_style(
            'another-unit-converter-frontend',
            $this->plugin_url . 'resources/css/frontend.css',
            array(),
            false
        );

        wp_localize_script(
            'another-unit-converter-frontend',
            'aucp_js',
            array( 
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'default_target_currency' => AUCP()->get_default_target_currency(),
                'is_external_api_set' => AUCP()->is_external_currency_conversion_api_ready()
            )
        );
    }

    public function enqueue_frontend_scripts_and_styles() {
        wp_enqueue_style( 'another-unit-converter-frontend' );
    }

    public function are_frontend_scripts_enqueued() {
        $handle = 'another-unit-converter-frontend';
        return wp_script_is( $handle, 'done' ) || wp_script_is( $handle, 'enqueued' );
    }
}
