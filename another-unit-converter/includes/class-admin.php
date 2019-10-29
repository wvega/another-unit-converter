<?php

class AUCP_Admin {

    public function __construct() {
        if ( ! is_admin() )
            return;

        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

        add_action( 'admin_menu', array( $this, 'admin_menu' ) );

        add_action( 'admin_notices', array( $this, 'maybe_show_api_key_notice' ) );
        add_action( 'wp_ajax_aucp_dismissed_api_key_notice', array( $this, 'dismiss_api_key_notice' ) );
    }

    public function admin_enqueue_scripts( $hook ) {
        if ( 'plugins.php' != $hook )
            return;

        wp_enqueue_script( 'aucp-admin', AUCP()->plugins_url( 'resources/js/admin.js' ), array( 'jquery' ) );
    }

    public function admin_menu() {
        add_options_page(
            _x( 'Unit Converter', 'admin menu', 'another-unit-converter' ),
            _x( 'Another Unit Converter', 'admin menu', 'another-unit-converter' ),
            'manage_options',
            'aucp_settings',
            array( $this, 'settings_page' )
        );
    }

    public function settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . get_admin_page_title() . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'aucp_settings' );
        do_settings_sections( 'aucp_settings' );
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function plugin_row_meta( $links, $file ) {
        if ( false !== strpos( $file, 'another-unit-converter.php' ) ) {
            $links['settings'] = '<a href="' . admin_url( 'options-general.php?page=aucp_settings' ) . '">' . _x( 'Plugin Settings', 'plugins page', 'another-unit-converter' ) . '</a>';
        }

        return $links;
    }

    public function maybe_show_api_key_notice( $hook ) {
        global $pagenow;

        if ( empty( $pagenow ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $api_key = AUCP()->settings->get_option( 'currencylayer_key' );
        if ( ! empty( $api_key ) ) {
            return;
        }

        $on_settings_page = 'options-general.php' == $pagenow && ! empty( $_GET['page'] ) && $_GET['page'] == 'aucp_settings';
        $on_plugins_page  = 'plugins.php' == $pagenow;

        if ( $on_settings_page ) {
            echo '<div class="notice notice-warning is-dismissible"><p>';

            _e( '<strong>Another Unit Converter</strong> requires a <strong>currencylayer API key</strong> to work properly. Obtaining an API key from currencylayer is completely FREE.', 'another-unit-converter' );
            echo '<br /><br />';

            echo '<strong>' . __( 'What is currencylayer?', 'another-unit-converter' ) . '</strong>';
            echo '<br />';
            echo str_replace(
                array( '<link>', '<about>', '</link>', '</about>' ),
                array( '<a href="https://currencylayer.com/" target="_blank">',
                       '<a href="https://currencylayer.com/about" target="_blank">',
                       '</a>',
                       '</a>' ),
                __( '<link>currencylayer</link> is a service that provides real-time and historical exchange rates for 168 world currencies. You can read more about currencylayer <about>here</about>.', 'another-unit-converter' )
            );
            echo '<br /><br />';

            echo '<strong>' . __( 'Why do I need an API key?', 'another-unit-converter' ) . '</strong>';
            echo '<br />';
            _e( '<i>Another Unit Converter</i> uses the currencylayer API to download updated daily rates for the supported currencies, and then perform the conversions that you see on your posts and pages using this information.', 'another-unit-converter' );
            echo '<br /><br />';

            echo '<strong>' . __( 'How do I sign-up for and use the currencylayer API key?', 'another-unit-converter' ) . '</strong>';
            echo '<br />';
            echo '<ol>';
            echo '<li>';
            echo str_replace(
                '<a>',
                '<a href="https://currencylayer.com/signup?plan=1" target="_blank">',
                __( 'Visit the <a>currencylayer sign-up page</a> and fill out the form.', 'another-unit-converter' )
            );
            echo '</li>';
            echo '<li>' . __( 'After submitting the form, you\'ll be redirected to a page with the API key on it.', 'another-unit-converter' ) . '</li>';
            echo '<li>';
            echo str_replace(
                '<a>',
                '<a href="#currencylayer_key">',
                __( 'Copy-paste your API key inside the <a>text field below</a> and save the changes.', 'another-unit-converter' )
            );
            echo '</li>';
            echo '<li>' . __( 'You\'re all set. Enjoy currency detection and conversion on all your posts and pages.', 'another-unit-converter' ) . '</li>';
            echo '</ol>';

            echo '</p></div>';
        }

        if ( $on_plugins_page && ! get_user_meta( get_current_user_id(), 'dismissed_aucp_api_key_notice', true ) ) {
            echo '<div id="aucp-api-key-notice" class="notice notice-error is-dismissible">';
            echo '<p>';
            echo str_replace(
                array( '<currencylayer>', '</currencylayer>' ),
                array( '<a href="http://currencylayer.com/" target="_blank">', '</a>' ),
                __( '<strong>Another Unit Converter</strong> requires an API key from <currencylayer>currencylayer.com</currencylayer> to operate. Currency conversion will remain disabled until an API key is entered.', 'another-unit-converter' )
            );
            echo '<br />';
            echo '<a href="' . admin_url( 'options-general.php?page=aucp_settings' ) . '">';
            _e( 'Click here for more details.', 'another-unit-converter' );
            echo '</a>';
            echo '</p>';
            echo '</div>';
        }
    }

    public function dismiss_api_key_notice() {
        update_user_meta( get_current_user_id(), 'dismissed_aucp_api_key_notice', true );
    }

}
