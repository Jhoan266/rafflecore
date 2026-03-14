<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RaffleCore Currency
 * Maneja la conversión de monedas usando una API gratuita y caché con transients.
 */
class RaffleCore_Currency {

    private static $api_url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/';
    private static $cache_time = 12 * HOUR_IN_SECONDS;

    /**
     * Convierte una cantidad de una moneda origen a una moneda destino.
     * Si las monedas son iguales, o si falla la API, devuelve el valor original.
     *
     * @param float  $amount Cantidad a convertir.
     * @param string $from   Moneda origen (ISO 3 letras).
     * @param string $to     Moneda destino (ISO 3 letras).
     * @return float Cantidad convertida.
     */
    public static function convert( $amount, $from, $to ) {
        if ( empty( $amount ) || (float) $amount === 0.0 ) {
            return 0.0;
        }

        $from = strtolower( trim( $from ) );
        $to   = strtolower( trim( $to ) );

        if ( $from === $to ) {
            return (float) $amount;
        }

        $rate = self::get_rate( $from, $to );

        if ( $rate === null ) {
            // Fallback al valor original si no hay tasa disponible
            return (float) $amount;
        }

        return (float) $amount * $rate;
    }

    /**
     * Obtiene la tasa de cambio entre dos monedas.
     * Usa un transient de WordPress para evitar peticiones constantes a la API.
     *
     * @param string $from Moneda origen.
     * @param string $to   Moneda destino.
     * @return float|null Tasa de cambio o null si falla.
     */
    private static function get_rate( $from, $to ) {
        $transient_key = 'rc_curr_rate_' . $from;
        
        $rates = get_transient( $transient_key );

        if ( false === $rates ) {
            $rates = self::fetch_rates_from_api( $from );
            if ( $rates ) {
                set_transient( $transient_key, $rates, self::$cache_time );
            }
        }

        if ( isset( $rates[ $to ] ) ) {
            return (float) $rates[ $to ];
        }

        return null;
    }

    /**
     * Hace la consulta real a la API para obtener todas las tasas de una moneda base.
     *
     * @param string $base_currency Moneda base (ej. 'cop').
     * @return array|null Array de tasas de cambio o null si falla.
     */
    private static function fetch_rates_from_api( $base_currency ) {
        $url = self::$api_url . $base_currency . '.json';
        
        $response = wp_remote_get( $url, array( 'timeout' => 5 ) );

        if ( is_wp_error( $response ) ) {
            RaffleCore_Logger::log( 'currency_api_error', 'system', 0, $response->get_error_message() );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data[ $base_currency ] ) ) {
            RaffleCore_Logger::log( 'currency_api_invalid_response', 'system', 0, 'Invalid data structure returned by API' );
            return null;
        }

        return $data[ $base_currency ];
    }
}
