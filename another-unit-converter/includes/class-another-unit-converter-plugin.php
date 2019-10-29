<?php

class Another_Unit_Converter_Plugin {

    private static $instance = null;

    private $plugin_file;

    private $currency_parser;
    private $currency_conversion;
    public $currencies;
    public $settings;
    private $admin;
    private $resources;


    public static function instance( $plugin_file ) {
        if ( is_null( self::$instance ) ) {
            $currencies = new AUCP_Currencies();

            self::$instance = new self(
                $plugin_file,
                new AUCP_Currency_Parser( $currencies ),
                new AUCP_Currency_Conversion(),
                $currencies,
                new AUCP_Settings(),
                new AUCP_Resources( plugin_dir_url( $plugin_file ) )
            );
        }

        return self::$instance;
    }

    public function __construct( $plugin_file, $currency_parser, $currency_conversion, $currencies, $settings, $resources ) {
        $this->plugin_file = $plugin_file;

        $this->currency_parser = $currency_parser;
        $this->currency_conversion = $currency_conversion;
        $this->currencies = $currencies;
        $this->settings = $settings;
        $this->resources = $resources;

        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
    }

    public function plugins_loaded() {
        add_action( 'init', array( $this, 'init' ) );

        add_action( 'wp_enqueue_scripts', array( $this->resources, 'register_scripts_and_styles' ) );

        if ( ! defined( 'DOING_AJAX' ) && ! is_admin() ) {
            $this->frontend_init();
        }

        if ( is_admin() ) {
            $this->admin = new AUCP_Admin();
        }

        add_action( 'wp_ajax_aucp_get_rates', array( $this, 'ajax_get_rates' ) );
        add_action( 'wp_ajax_nopriv_aucp_get_rates', array( $this,'ajax_get_rates' ) );
        add_action( 'wp_ajax_aucp_convert', array( $this, 'ajax_convert' ) );
        add_action( 'wp_ajax_nopriv_aucp_convert', array( $this,'ajax_convert' ) );
        add_action( 'wp_ajax_aucp_batch_convert', array( $this, 'ajax_batch_convert' ) );
        add_action( 'wp_ajax_nopriv_aucp_batch_convert', array( $this,'ajax_batch_convert' ) );
        add_action( 'wp_ajax_aucp_remember_currency', array( $this, 'ajax_remember_currency' ) );
        add_action( 'wp_ajax_nopriv_aucp_remember_currency', array( $this,'ajax_remember_currency' ) );
        add_action( 'wp_ajax_aucp_reset', array( $this, 'ajax_reset' ) );
        add_action( 'wp_ajax_nopriv_aucp_reset', array( $this,'ajax_reset' ) );
    }

    public function init() {
        if ( ! $this->settings->get_option( 'enabled', false ) ) {
            return;
        }

        if ( false && ! $this->is_external_currency_conversion_api_ready() ) {
            return;
        }

        $this->currency_conversion->maybe_refresh_rates();

        add_filter( 'the_content', array( $this, 'format_currency_amounts' ) );

        if( class_exists( 'WooCommerce' ) ){
            add_filter( 'wc_price', array( $this, 'format_woocommerce_currency_amounts' ), 10, 4 );
        }
    }

    public function is_external_currency_conversion_api_ready() {
        $api_key = $this->settings->get_option( 'currencylayer_key' );
        return ! empty( $api_key );
    }

    public function frontend_init() {
        add_action( 'wp_enqueue_scripts', array( $this->resources, 'enqueue_frontend_scripts_and_styles' ) );
        add_action( 'wp_footer', array( $this, 'maybe_print_currency_switcher_template' ) );
    }

    public function plugins_url( $path ) {
        return plugins_url( $path, $this->plugin_file );
    }

    public function maybe_print_currency_switcher_template() {
        if ( ! $this->resources->are_frontend_scripts_enqueued() ) {
            return;
        }

        $plugin_dir = dirname( $this->plugin_file );

        if ( $this->is_external_currency_conversion_api_ready() ) {
            $currencies = $this->currencies->get_currencies();
            include( $plugin_dir . '/templates/currency-switcher.tpl.php' );
        } else {
            $settings_url = '<a class="aucp-currency-switcher-link" href="' . add_query_arg( 'page', 'aucp_settings', admin_url( 'options-general.php' ) ) . '">';

            $message = _x( 'Once you <a>enter a valid currencylayer API key</a>, this widget will allow vistors to convert currency amounts on this page to one of the supported currencies.', 'currency switcher message', 'another-unit-converter' );
            $message = str_replace( '<a>', $settings_url, $message );

            include( $plugin_dir . '/templates/currency-switcher-not-ready.tpl.php' );
        }
    }

    public function format_currency_amounts( $content ) {
        $currency_amounts = $this->currency_parser->get_currency_amounts( $content );

        if ( ! $currency_amounts ) {
            return $content;
        }

        $offset = 0;

        foreach ( $currency_amounts as $index => $currency_amount ) {
            $currency_info = $this->select_currency( $currency_amount['currencies'] );
            
            $start_position = $currency_info['position']['start'] + $offset;
            $end_position = $currency_info['position']['end'] + $offset;

            $amount_length = $end_position - $start_position;
            $amount_text = mb_substr( $content, $start_position, $amount_length );
            $amount = esc_attr( $currency_info['amount'] );
            $symbol = esc_attr( $currency_info['currency']['symbol'] );
            $code = esc_attr( $currency_info['currency']['code'] );

            $html  = '';
            $html .= '<span class="aucp-currency-amount" data-unit-converter-currency-amount="%1$s" data-unit-converter-currency-symbol="%2$s" data-unit-converter-currency-code="%3$s">';
            $html .= '<span class="aucp-converted-text">%5$s</span>';
            $html .= '<span class="aucp-original-text ' . ( 'converted' == $this->settings->get_option( 'amount_display', 'both' ) ? 'aucp-keep-hidden' : '' ) . '" title="%6$s">%5$s</span>';
            $html .= '</span>';

            $formatted_text = sprintf(
                $html,
                $amount,
                $symbol,
                $code,
                esc_attr( $amount_text ),
                esc_html( $amount_text ),
                esc_attr( $currency_info['currency']['name'] )
            );

            $content = mb_substr( $content, 0, $start_position ) . $formatted_text . mb_substr( $content, $end_position );
            $offset = $offset + mb_strlen( $formatted_text ) - $amount_length;
        }

        wp_enqueue_script( 'another-unit-converter-frontend' );

        return $content;
    }

    public function format_woocommerce_currency_amounts( $return, $price, $args, $unformatted_price ){

        $content = $this->format_currency_amounts( html_entity_decode( get_woocommerce_currency_symbol( ) ) . $price );
        
        return $content;
    }

    private function select_currency( $currencies ) {
        $default_currency = $this->settings->get_option( 'default_currency' );

        if ( $default_currency && isset( $currencies[ $default_currency ] ) ) {
            return $currencies[ $default_currency ];
        }

        return reset( $currencies );
    }

    public function ajax_get_rates() {
        $response = array( 'success' => false );
        $codes = array();

        foreach ( array( 'code', 'codes' ) as $id ) {
            if ( ! empty( $_REQUEST[ $id ] ) ) {
                foreach ( (array) $_REQUEST[ $id ] as $code ) {
                    $codes = array_merge( $codes, explode( ',', $code ) );
                }
            }
        }
        $codes = array_map( 'strtoupper', $codes );
        $all_rates = $this->currency_conversion->get_rates_with_currency_info();

        $response['success'] = true;

        if ( ! $codes ) {
            $response['rates'] = $all_rates;
        } else {
            $response['rates'] = wp_array_slice_assoc( $all_rates, $codes );
        }

        echo json_encode( $response );
        exit;
    }

    public function ajax_convert() {
        $response = array( 'success' => false );

        $from = ! empty( $_REQUEST['from'] ) ? trim( $_REQUEST['from'] ) : '';
        $to = ! empty( $_REQUEST['to'] ) ? trim( $_REQUEST['to'] ) : '';
        $amount = ! empty( $_REQUEST['amount'] ) ? floatval( $_REQUEST['amount'] ) : '';

        $conversion = $this->currency_conversion->convert( $from, $to, $amount );

        if ( false !== $conversion ) {
            $response['success'] = true;
            $response['from'] = $from;
            $response['to'] = $to;
            $response['amount'] = $amount;
            $response['result'] = $conversion;
            $response['rate'] = $this->currency_conversion->convert( $from, $to, 1.0 );
        }

        echo json_encode( $response );
        exit;
    }

    public function ajax_batch_convert() {
        $response = array( 'success' => false );

        if ( isset( $_REQUEST['items'] ) && is_array( $_REQUEST['items'] ) ) {
            $response['success'] = true;
            $response['results'] = array();

            foreach ( $_REQUEST['items'] as $item ) {
                $from = ! empty( $item['from'] ) ? strtoupper( $item['from'] ) : '';
                $to = ! empty( $item['to'] ) ? strtoupper( $item['to'] ) : '';
                $amount = isset( $item['amount'] ) ? $item['amount'] : '';

                $result_item = array(
                    'from'    => $from,
                    'to'      => $to,
                    'amount'  => $amount,
                    'success' => false
                );

                if ( $result = $this->currency_conversion->convert( $from, $to, $amount ) ) {
                    $result_item['success'] = true;
                    $result_item['result'] = $result;
                    $result_item['rate'] = $this->currency_conversion->convert( $from, $to, 1.0 );
                }

                $response[] = $result_item;
            }
        }

        echo json_encode( $response );
        exit;
    }

    public function get_default_target_currency() {
        $default_currency = ! empty( $_COOKIE['aucp_target_currency'] ) ? $_COOKIE['aucp_target_currency'] : '';
        $default_currency = strtoupper( $default_currency );

        if ( ! $default_currency ) {
            return '';
        }

        $currency = $this->currencies->get_currency( $default_currency );

        if ( ! $currency ) {
            return '';
        }

        return $default_currency;
    }

    /**
     * Stores the latest currency code selection on a cookie for future reference.
     */
    public function ajax_remember_currency() {
        $code = ! empty( $_POST['code'] ) ? $_POST['code'] : '';

        if ( ! $code ) {
            exit;
        }

        $currency = $this->currencies->get_currency( $code );

        if ( ! $currency ) {
            exit;
        }

        @setcookie( 'aucp_target_currency', $code, 30 * DAYS_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
        exit;
    }

    /**
     * Deletes the current cookie.
     */
    public function ajax_reset() {
        @setcookie( 'aucp_target_currency', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
        exit;
    }
}

