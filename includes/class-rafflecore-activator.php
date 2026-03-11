<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ejecutado al activar el plugin. Crea/actualiza las tablas necesarias.
 *
 * Esquema v2.0.0:
 *   rc_raffles     — Rifas con control de estado, sorteo y vínculo a WC product.
 *   rc_purchases   — Compras con amount_paid (historial fiel), order_id genérico (SaaS-ready).
 *   rc_tickets     — Boletos con UNIQUE KEY (raffle_id, ticket_number) para anti-colisión a nivel DB.
 */
class RaffleCore_Activator {

    const DB_VERSION = '2.0.0';

    public static function activate() {
        $installed = get_option( 'rafflecore_db_version', '0' );

        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::migrate_from( $installed );
            self::create_tables();
            update_option( 'rafflecore_db_version', self::DB_VERSION );
        }

        update_option( 'rafflecore_version', RAFFLECORE_VERSION );
        flush_rewrite_rules();
    }

    /**
     * Migra datos de versiones anteriores.
     * v1.x → v2.0: Renombra columnas en rc_purchases.
     */
    private static function migrate_from( $from_version ) {
        global $wpdb;

        $t_purchases = $wpdb->prefix . 'rc_purchases';
        $t_raffles   = $wpdb->prefix . 'rc_raffles';

        // Migración v1 → v2: renombrar columnas si la tabla ya existe.
        if ( version_compare( $from_version, '2.0.0', '<' ) ) {
            $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_purchases}", 0 );

            if ( is_array( $cols ) && in_array( 'total_amount', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_purchases} CHANGE `total_amount` `amount_paid` decimal(12,2) NOT NULL DEFAULT 0" );
            }
            if ( is_array( $cols ) && in_array( 'payment_status', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_purchases} CHANGE `payment_status` `status` varchar(20) NOT NULL DEFAULT 'pending'" );
            }
            if ( is_array( $cols ) && in_array( 'wc_order_id', $cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_purchases} CHANGE `wc_order_id` `order_id` bigint(20) UNSIGNED DEFAULT NULL" );
            }

            // Añadir wc_product_id a raffles si no existe.
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) && ! in_array( 'wc_product_id', $raffle_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `wc_product_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `winner_ticket_id`" );
            }
        }
    }

    /**
     * Crea tablas vía dbDelta (idempotente, solo añade lo que falta).
     *
     * Esquema alineado con la especificación:
     *   raffles:          id, title, description, prize_value, total_tickets, sold_tickets,
     *                     draw_date, status, winner_ticket_id, created_at
     *                     + prize_image, ticket_price, packages, wc_product_id (extensiones)
     *   raffle_purchases: id, raffle_id, buyer_email, quantity, amount_paid,
     *                     order_id, status, purchase_date
     *                     + buyer_name (extensión)
     *   raffle_tickets:   id, raffle_id, purchase_id, ticket_number, buyer_email
     *                     + created_at (extensión)
     */
    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $t_raffles   = $wpdb->prefix . 'rc_raffles';
        $t_purchases = $wpdb->prefix . 'rc_purchases';
        $t_tickets   = $wpdb->prefix . 'rc_tickets';

        $sql = "CREATE TABLE {$t_raffles} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            prize_value decimal(12,2) NOT NULL DEFAULT 0,
            prize_image varchar(500) DEFAULT '',
            total_tickets int(11) NOT NULL DEFAULT 0,
            sold_tickets int(11) NOT NULL DEFAULT 0,
            ticket_price decimal(12,2) NOT NULL DEFAULT 0,
            packages text,
            draw_date datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            winner_ticket_id bigint(20) UNSIGNED DEFAULT NULL,
            wc_product_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY wc_product_id (wc_product_id)
        ) {$charset};

        CREATE TABLE {$t_purchases} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            buyer_name varchar(255) NOT NULL DEFAULT '',
            buyer_email varchar(255) NOT NULL,
            quantity int(11) NOT NULL,
            amount_paid decimal(12,2) NOT NULL DEFAULT 0,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            purchase_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY raffle_id (raffle_id),
            KEY order_id (order_id),
            KEY buyer_email (buyer_email),
            KEY status (status)
        ) {$charset};

        CREATE TABLE {$t_tickets} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            raffle_id bigint(20) UNSIGNED NOT NULL,
            purchase_id bigint(20) UNSIGNED NOT NULL,
            ticket_number int(11) NOT NULL,
            buyer_email varchar(255) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_ticket (raffle_id, ticket_number),
            KEY raffle_id (raffle_id),
            KEY purchase_id (purchase_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
