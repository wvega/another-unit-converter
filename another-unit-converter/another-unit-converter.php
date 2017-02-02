<?php

/**
 * Plugin Name: Another Unit Converter
 * Plugin URI:  http://anotherunitconverter.com
 * Description: A universal unit converter for WordPress content.
 * Version:     0.1-dev-1
 * Author:      Smiling Robots
 * Author URI:  http://smilingrobots.com
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: another-unit-converter
*/

require __DIR__ . '/vendor/autoload.php';

class Another_Unit_Converter_Plugin {

    private $currency_parser;

    public function __construct( $currency_parser, $currency_conversion ) {
        $this->currency_parser = $currency_parser;
        $this->currency_conversion = $currency_conversion;
    }

    public function plugins_loaded() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'template_redirect', array( $this, 'frontend_init' ) );
    }

    public function init() {
        add_filter( 'the_content', array( $this, 'format_currency_amounts' ) );
    }

    public function frontend_init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts_and_styles' ) );

        wp_register_script(
            'another-unit-converter-vue',
            plugin_dir_url( __FILE__ ) . 'resources/js/vue/vue.js',
            array(),
            '2.0.3',
            true
        );

        wp_register_script(
            'another-unit-converter-frontend',
            plugin_dir_url( __FILE__ ) . 'resources/js/frontend.js',
            array( 'another-unit-converter-vue', 'jquery' ),
            false,
            true
        );

        wp_register_style(
            'another-unit-converter-frontend',
            plugin_dir_url( __FILE__ ) . 'resources/css/frontend.css',
            array(),
            false
        );
    }

    public function enqueue_frontend_scripts_and_styles() {
        wp_enqueue_style( 'another-unit-converter-frontend' );
    }

    public function format_currency_amounts( $content ) {
        $currency_amounts = $this->find_currency_amounts( $content );

        if ( ! $currency_amounts ) {
            return $content;
        }

        wp_enqueue_script( 'another-unit-converter-frontend' );

        foreach ( $currency_amounts as $amount_text => $formatted_text ) {
            $content = str_replace( $amount_text, $formatted_text, $content );
        }

        return $content;
    }

    private function find_currency_amounts( $content ) {
        $currency_amounts = array();

        $regexp = '/(*UTF8)([A-Z]{0,4}[^\w\d\s]?)[\s]{0,1}(\d{4,}|\d{1,3}(?:[,.]\d{1,3})*)[\s]{0,1}([A-Z]{0,4}[^\w\d\s]?)/u';

        if ( ! preg_match_all( $regexp, strip_tags( $content ), $matches, PREG_OFFSET_CAPTURE ) ) {
            return $currency_amounts;
        }

        foreach ( $matches[0] as $index => $match ) {
            $amount_text = trim( $match[0] );
            $amount_symbol = $matches[1][ $index ][0];
            $amount_number = $matches[2][ $index ][0];

            if ( ! $amount_symbol )
                $amount_symbol = $matches[3][ $index ][0];

            try {
                // TODO: maybe we should just "pass" everything to the parser. The extra context (for instance in the case
                // of USD $5) could be useful to disambiguate the currency code. And also, it seems we're actually 
                // parsing here instead of inside the parser :P -j
                $extracted_amount = $this->currency_parser->parse_amount(
                    $amount_text,
                    $amount_symbol,
                    $amount_number
                );

                // TODO: Why the try-catch if we are returning null on failure?
                if ( is_null( $extracted_amount ) ) {
                    continue;
                }

                $formatted_text = sprintf(
                    '<currency-switcher data-unit-converter-currency-amount="%1$s" data-unit-converter-currency-symbol="%2$s" data-unit-converter-currency-code="%3$s" amount="%1$s" symbol="%2$s" code="%3$s" text="%4$s">%4$s</currency-switcher>',
                    esc_attr( $extracted_amount['amount'] ),
                    esc_attr( $extracted_amount['symbol'] ),
                    esc_attr( $extracted_amount['code'] ),
                    $amount_text
                );

                $currency_amounts[ $amount_text ] = $formatted_text;
            } catch ( AUCP_Exception $e ) {
                continue;
            }
        }

        return $currency_amounts;
    }
}

function aucp_load_another_unit_converter_plugin() {
    $plugin = new Another_Unit_Converter_Plugin(
        new AUCP_Currency_Parser( new AUCP_Currencies() ),
        new AUCP_Currency_Conversion()
    );

    $plugin->plugins_loaded();
}
add_action( 'plugins_loaded', 'aucp_load_another_unit_converter_plugin' );
