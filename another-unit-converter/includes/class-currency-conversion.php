<?php
class AUCP_Currency_Conversion {

    private $data = array( 'timestamp' => 0, 'rates' => array() );


    public function __construct() {
        $this->maybe_refresh_rates();
    }

    /**
     * Returns USD -> currency quotes for all supported currencies.
     * @return array Array of currency conversion factors (from USD) with currency code as key.
     */
    public function get_rates() {
        return $this->data['rates'];
    }

    /**
     * Returns the timestamp of the last quotes update.
     * @return int
     */
    public function get_last_update_time() {
        return $this->data['timestamp'];
    }

    /**
     * Returns USD -> $currency rate if available.
     * @param string $currency Currency code.
     * @return float|bool False if currency is not available or rate otherwise.
     */
    public function get_rate_for( $currency ) {
        $currency = strtoupper( $currency );

        if ( ! isset( $this->data['rates'][ $currency ] ) )
            return false;

        return $this->data['rates'][ $currency ];
    }

    /**
     * Performs a currency conversion.
     * @param string  $from  Optional. Initial currency (3-letter code). Defaults to 'USD'.
     * @param string  $to    Optional. Final currency (3-letter code). Defaults to 'USD'.
     * @param numeric $value Amount.
     * @return float|bool Amount in the destination currency or False if conversion can't be performed.
     */
    public function convert( $from = 'USD', $to = 'USD', $amount ) {
        $from_rate = $this->get_rate_for( strtoupper( $from ) );
        $to_rate = $this->get_rate_for( strtoupper( $to ) );

        if ( ! $from_rate || ! $to_rate )
            return false;

        return ( 1.0 / $from_rate ) * $to_rate * floatval( $amount );
    }

    private function maybe_refresh_rates() {
        $exchange_rates = get_transient( 'aucp-exchange-rates' );

        if ( ! $exchange_rates ) {
            $api_key = '43ca3a44571f036369d23300c635617f'; // Hardcoded for now.
            $request = wp_remote_get( 'http://apilayer.net/api/live?access_key=' . $api_key . '&source=USD' );

            if ( is_wp_error( $request ) )
                return;

            $response = json_decode( wp_remote_retrieve_body( $request ) );
            if ( ! $response || ! empty( $response->error ) || ! $response->success )
                return;

            $exchange_rates = array(
                'timestamp' => $response->timestamp,
                'rates' => array()
            );

            foreach ( get_object_vars( $response->quotes ) as $convcode => $rate ) {
                $currency = substr( $convcode, 3 );
                $exchange_rates['rates'][ $currency ] = floatval( $rate );
            }

            set_transient( 'aucp-exchange-rates', $exchange_rates, DAY_IN_SECONDS );
        }

        $this->data = $exchange_rates;
    }

}
