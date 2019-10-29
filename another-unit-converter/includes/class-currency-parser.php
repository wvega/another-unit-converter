<?php

class AUCP_Currency_Parser {

    private $currencies;

    public function __construct( $currencies ) {
        $this->currencies = $currencies;
    }

    public function get_currency_amounts( $content ) {
        $regexp = '/(*UTF8)(?<currency_amount>(\d{4,}|\d{1,3})(?:[,. ]\d{1,3})*)/';

        if ( ! preg_match_all( $regexp, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
            return array();
        }

        $currency_amounts = array();

        foreach ( $matches['currency_amount'] as $match ) {
            $offset = mb_strlen( mb_strcut( $content, 0, $match[1] ) );
            $amount_text = $match[0];
            $amount_length = mb_strlen( $amount_text );

            $prefix_parts = $this->get_prefix_parts( $content, $offset );
            $suffix_parts = $this->get_suffix_parts( $content, $offset, $amount_length );
            $parts = array_merge( $prefix_parts, $suffix_parts );

            $parts_to_check = array( array( 0, array( 2, 1 ) ), array( 2, array( 0, 3 ) ) );
            $currencies = $this->get_currencies_from_parts( $parts, $parts_to_check );

            if ( ! $currencies['currencies'] ) {
                continue;
            }

            $currency_amounts[] = array(
                'amount_text' => $amount_text,
                'currencies' => $this->prepare_currencies( $amount_text, $offset, $amount_length, $currencies ),
            );
        }

        return $currency_amounts;
    }

    private function get_prefix_parts( $content, $offset ) {
        $prefix = mb_substr( $content, max( 0, $offset - 9 ), min( $offset, 9 ) );
        $prefix_parts = explode( "\n", $prefix );
        $closest_prefix = array_pop( $prefix_parts );
        $prefix_parts = mb_split( '[!"#%&\'()*+,-./:;<=>?@\[\]\^_`{|}~ ]', $closest_prefix );
        $augmented_parts = array();
        $accumulated_offset = 0;

        for ( $i = count( $prefix_parts ); $i > 0; $i = $i - 1 ) {
            if ( $prefix_parts[ $i - 1 ] ) {
                $part_length = mb_strlen( $prefix_parts[ $i - 1 ] );
                $augmented_parts[] = array(
                    'type' => 'prefix',
                    'offset' => $offset - $part_length - $accumulated_offset,
                    'length' => $part_length,
                    'content' => $prefix_parts[ $i - 1 ],
                );
                $accumulated_offset = $accumulated_offset + $part_length;
            }

            $accumulated_offset = $accumulated_offset + 1;
        }

        return array_pad( array_slice( $augmented_parts, 0, 2 ), 2, array() );
    }

    private function get_suffix_parts( $content, $offset, $amount_length ) {
        $suffix = mb_substr( $content, $offset + $amount_length, 9 );
        $suffix_parts = explode( "\n", $suffix );
        $closest_suffix = array_shift( $suffix_parts );
        $suffix_parts = mb_split( '[!"#%&\'()*+,-./:;<=>?@\[\]\^_`{|}~ ]', $closest_suffix );
        $augmented_parts = array();
        $accumulated_offset = 0;

        for ( $i = 0; $i < count( $suffix_parts ); $i = $i + 1 ) {
            if ( $suffix_parts[ $i ] ) {
                $part_length = mb_strlen( $suffix_parts[ $i ] );
                $augmented_parts[] = array(
                    'type' => 'suffix',
                    'offset' => $offset + $amount_length + $accumulated_offset,
                    'length' => $part_length,
                    'content' => $suffix_parts[ $i ],
                );
                $accumulated_offset = $accumulated_offset + $part_length;
            }

            $accumulated_offset = $accumulated_offset + 1;
        }

        return array_pad( array_slice( $augmented_parts, 0, 2 ), 2, array() );
    }

    private function get_currencies_from_parts( $parts, $selected_parts = array() ) {
        foreach ( $selected_parts as $selected_part ) {
            $selected_part = (array) $selected_part;

            if ( ! $parts[ $selected_part[0] ] ) {
                continue;
            }

            /**
             * Capturing Groups:
             *
             * currency_code: 1,6
             * country_code: 3,8
             * currency_symbol: 2,4,5,7,9
             *
             * XXX: Symbol regex: [^\s]{0,2}[^\d\s] or [^\d\s]{1,3}
             */
            preg_match(
                    '/(*UTF8)'
                .       '([[:alpha:]]{3})([^\s]{0,2}[^\d\s])?' // Matches USD or USD$
                . '|' . '([[:alpha:]]{2})([^\s]{0,2}[^\d\s])?' // Matches US or US$
                . '|' . '([^\s]{0,2}[^\d\s])([[:alpha:]]{3})'  // Matches $USD
                . '|' . '([^\s]{0,2}[^\d\s])([[:alpha:]]{2})'  // Matches $US
                . '|' . '([^\s]{0,2}[^\d\s])'                  // Matches $
                .   '/',
                $parts[ $selected_part[0] ]['content'],
                $captured_values
            );

            $currency_code = $this->get_captured_values( $captured_values, array( 1, 6 ) );
            $country_code = $this->get_captured_values( $captured_values, array( 3, 8 ) );
            $currency_symbol = $this->get_captured_values( $captured_values, array( 2, 4, 5, 7, 9 ) );

            // some symbols are just three letters (Lek symbol for ALL)
            if ( ! $currency_symbol && $currency_code ) {
                $currency_symbol = $currency_code;
            }

            // some symbols are just two letters ('kr' symbol for SEK)
            if ( ! $currency_symbol && $country_code ) {
                $currency_symbol = $country_code;
            }

            $alternatives = array(
                'get_currency' => $currency_code,
                'get_currency_from_country_code' => $country_code,
                'find_currencies_by_symbol' => $currency_symbol,
            );

            foreach ( $alternatives as $method => $param ) {
                if ( ! $param ) {
                    continue;
                }

                $currencies = (array) $this->currencies->{$method}( $param );

                // The methods return an array or null (casted into an empty array above).
                // We need to know whether it is an indexed array of currencies or a
                // currency represented as an associative array.
                //
                // TODO: represent currencies as objects (could be just stdClass instances).
                if ( isset( $currencies['code'] ) ) {
                    $currencies = array( $currencies );
                }

                if ( $currencies && 1 == count( $currencies ) ) {
                    return array( 'parts' => array( $parts[ $selected_part[0] ] ), 'currencies' => $currencies );
                } elseif ( $currencies && count( $currencies ) > 1 ) {
                    if ( isset( $selected_part[1] ) && count( $selected_part[1] ) > 1 ) {
                        $suggested_currencies = $this->get_currencies_from_parts( $parts, $selected_part[1] );
                    } else {
                        $suggested_currencies = array();
                    }

                    $currencies = $this->try_to_choose_currency( $currencies, $suggested_currencies );

                    array_unshift( $currencies['parts'], $parts[ $selected_part[0] ] );

                    return $currencies;
                }
            }
        }

        return array( 'parts' => array(), 'currencies' => array() );
    }

    private function get_captured_values( $matches, $alternatives ) {
        foreach ( $alternatives as $key ) {
            if ( isset( $matches[ $key ] ) && $matches[ $key ] ) {
                return $matches[ $key ];
            }
        }
    }

    private function try_to_choose_currency( $currencies, $suggested_currencies ) {
        if ( 1 === count( $suggested_currencies['currencies'] ) ) {
            foreach ( $currencies as $currency ) {
                if ( $currency['code'] == $suggested_currencies['currencies'][0]['code'] ) {
                    return $suggested_currencies;
                }
            }
        }

        return array( 'parts' => array(), 'currencies' => $currencies );
    }

    private function prepare_currencies( $amount_text, $amount_offset, $amount_length, $currencies ) {
        $start_position = $amount_offset;
        $end_position = $start_position + $amount_length;

        foreach ( $currencies['parts'] as $part ) {
            if ( 'prefix' == $part['type'] ) {
                $start_position = min( $part['offset'], $start_position );
            } elseif ( 'suffix' == $part['type'] ) {
                $end_position = max( $part['offset'] + $part['length'], $end_position );
            }
        }

        $prepared_currencies = array();

        foreach ( $currencies['currencies'] as $i => $currency ) {
            $prepared_currencies[ $currency['code'] ] = array(
                'currency' => $currency,
                'position' => array( 'start' => $start_position, 'end' => $end_position ),
                'amount' => $this->parse_amount( $amount_text, $currency ),
            );
        }

        return $prepared_currencies;
    }

    public function parse_amount( $amount_text, $currency ) {
        if ( $currency['decimal_point'] ) {
            $amount_parts = explode( $currency['decimal_point'], $amount_text );
        } else {
            $amount_parts = array( $amount_text );
        }

        $amount_integer = preg_replace( '/[^0-9]/', '', $amount_parts[0] );
        $amount_decimals = isset( $amount_parts[1] ) ? $amount_parts[1] : '0';

        return intval( $amount_integer ) + floatval( '0.' . $amount_decimals );
    }
}
