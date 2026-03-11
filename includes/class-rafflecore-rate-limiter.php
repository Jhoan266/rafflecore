<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rate Limiter — Protección anti-bots con ventana deslizante y backoff progresivo.
 *
 * Usa transients de WordPress para conteo de requests por IP.
 * Esquema:
 *   - Ventana: 60 segundos, máx. 5 intentos.
 *   - Backoff: Nivel 1 = 1 min, Nivel 2 = 5 min, Nivel 3 = 15 min.
 *   - Honeypot: Campo oculto que bots llenan automáticamente.
 */
class RaffleCore_Rate_Limiter {

    const MAX_ATTEMPTS   = 5;
    const WINDOW_SECONDS = 60;

    // Backoff tiers en segundos
    const BACKOFF_TIERS = array(
        1 => 60,    // 1 minuto
        2 => 300,   // 5 minutos
        3 => 900,   // 15 minutos
    );

    /**
     * Verifica si la IP actual está permitida para hacer un request.
     *
     * @return true|WP_Error  True si permitido, WP_Error si bloqueado.
     */
    public static function check() {
        $ip = self::get_client_ip();
        $ip_hash = self::hash_ip( $ip );

        // 1. Verificar si está en backoff (bloqueado temporalmente)
        $backoff_key = 'rc_backoff_' . $ip_hash;
        $backoff = get_transient( $backoff_key );
        if ( $backoff !== false ) {
            return new WP_Error(
                'rate_limited',
                __( 'Demasiados intentos. Por favor espera unos minutos antes de intentar de nuevo.', 'rafflecore' )
            );
        }

        // 2. Contar intentos en la ventana actual
        $count_key = 'rc_count_' . $ip_hash;
        $count = (int) get_transient( $count_key );

        if ( $count >= self::MAX_ATTEMPTS ) {
            // Calcular tier de backoff
            $tier_key = 'rc_tier_' . $ip_hash;
            $tier = (int) get_transient( $tier_key );
            $tier = min( $tier + 1, 3 );

            $backoff_seconds = self::BACKOFF_TIERS[ $tier ];
            set_transient( $backoff_key, 1, $backoff_seconds );
            set_transient( $tier_key, $tier, 3600 ); // tier expira en 1 hora
            delete_transient( $count_key ); // reset counter

            return new WP_Error(
                'rate_limited',
                __( 'Demasiados intentos. Por favor espera unos minutos antes de intentar de nuevo.', 'rafflecore' )
            );
        }

        // 3. Incrementar contador
        if ( $count === 0 ) {
            set_transient( $count_key, 1, self::WINDOW_SECONDS );
        } else {
            // Mantener TTL existente del transient
            $ttl = self::get_transient_ttl( $count_key );
            set_transient( $count_key, $count + 1, $ttl > 0 ? $ttl : self::WINDOW_SECONDS );
        }

        return true;
    }

    /**
     * Verifica el campo honeypot. Si tiene valor, es un bot.
     *
     * @return true|WP_Error
     */
    public static function check_honeypot() {
        // El campo 'rc_website' es un honeypot oculto — usuarios reales nunca lo llenan
        if ( isset( $_POST['rc_website'] ) && ! empty( $_POST['rc_website'] ) ) {
            // Silenciosamente rechazar (no dar pistas al bot)
            return new WP_Error(
                'bot_detected',
                __( 'Error de validación. Intenta de nuevo.', 'rafflecore' )
            );
        }
        return true;
    }

    /**
     * Obtiene la IP del cliente con validación.
     */
    private static function get_client_ip() {
        // Solo usar REMOTE_ADDR (confiable) — X-Forwarded-For es spoofeable
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

        // Validar formato IP
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            $ip = '0.0.0.0';
        }

        return $ip;
    }

    /**
     * Hash de IP para usar como clave de transient (privacidad + longitud fija).
     */
    private static function hash_ip( $ip ) {
        return substr( hash( 'sha256', $ip . wp_salt( 'auth' ) ), 0, 16 );
    }

    /**
     * Obtiene el TTL restante de un transient.
     */
    private static function get_transient_ttl( $key ) {
        global $wpdb;
        $timeout_key = '_transient_timeout_' . $key;
        $timeout = $wpdb->get_var( $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $timeout_key
        ) );

        if ( $timeout ) {
            $remaining = (int) $timeout - time();
            return $remaining > 0 ? $remaining : 0;
        }

        return 0;
    }
}
