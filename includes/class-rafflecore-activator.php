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

    const DB_VERSION = '3.5.0';

    public static function activate( $network_wide = false ) {
        if ( is_multisite() && $network_wide ) {
            $sites = get_sites( array( 'fields' => 'ids' ) );
            foreach ( $sites as $site_id ) {
                switch_to_blog( $site_id );
                self::single_activate();
                restore_current_blog();
            }
        } else {
            self::single_activate();
        }
    }

    /**
     * Activación para un sitio individual.
     */
    private static function single_activate() {
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
     * Hook: Crear tablas cuando se añade un nuevo sitio a la red multisite.
     */
    public static function on_new_blog( $blog_id ) {
        if ( is_plugin_active_for_network( RAFFLECORE_BASENAME ) ) {
            switch_to_blog( $blog_id );
            self::single_activate();
            restore_current_blog();
        }
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

        // v2.0 → v2.1: Añadir lucky_numbers a raffles.
        if ( version_compare( $from_version, '2.1.0', '<' ) ) {
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) && ! in_array( 'lucky_numbers', $raffle_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `lucky_numbers` text DEFAULT NULL AFTER `wc_product_id`" );
            }
        }

        // v2.1 → v2.2: Añadir font_family a raffles.
        if ( version_compare( $from_version, '2.2.0', '<' ) ) {
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) && ! in_array( 'font_family', $raffle_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `font_family` varchar(100) DEFAULT '' AFTER `lucky_numbers`" );
            }
        }

        // v2.2 → v2.3: Añadir custom_font_url a raffles.
        if ( version_compare( $from_version, '2.3.0', '<' ) ) {
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) && ! in_array( 'custom_font_url', $raffle_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `custom_font_url` varchar(500) DEFAULT '' AFTER `font_family`" );
            }
        }

        // v2.3 → v3.0: Añadir prize_gallery a raffles.
        if ( version_compare( $from_version, '3.0.0', '<' ) ) {
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) && ! in_array( 'prize_gallery', $raffle_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `prize_gallery` text DEFAULT NULL AFTER `custom_font_url`" );
            }
        }

        // v3.0 → v3.1: Añadir entry_hash a activity_log para cadena de integridad.
        if ( version_compare( $from_version, '3.1.0', '<' ) ) {
            $t_log = $wpdb->prefix . 'rc_activity_log';
            $log_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_log}", 0 );
            if ( is_array( $log_cols ) && ! in_array( 'entry_hash', $log_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_log} ADD COLUMN `entry_hash` varchar(64) DEFAULT '' AFTER `ip_address`" );
            }
        }

        // v3.3 → v3.4: Añadir type y max_number a rc_raffles para rifas por selección
        if ( version_compare( $from_version, '3.4.0', '<' ) ) {
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) ) {
                if ( ! in_array( 'type', $raffle_cols, true ) ) {
                    $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `type` varchar(20) NOT NULL DEFAULT 'quantity' AFTER `status`" );
                }
                if ( ! in_array( 'max_number', $raffle_cols, true ) ) {
                    $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `max_number` int(11) NOT NULL DEFAULT 0 AFTER `type`" );
                }
            }
        }

        // v3.4.0 → v3.4.1: Añadir countdown_threshold a rc_raffles
        if ( version_compare( $from_version, '3.4.1', '<' ) ) {
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) && ! in_array( 'countdown_threshold', $raffle_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `countdown_threshold` int(3) NOT NULL DEFAULT 0 AFTER `max_number`" );
            }
        }

        // v3.4.1 → v3.4.2: Añadir ticket_digits a rc_raffles
        if ( version_compare( $from_version, '3.4.2', '<' ) ) {
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) && ! in_array( 'ticket_digits', $raffle_cols, true ) ) {
                $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `ticket_digits` int(1) NOT NULL DEFAULT 2 AFTER `total_tickets`" );
            }
        }

        // v3.4.2 → v3.5.0: Añadir color_palette y min_custom_qty a rc_raffles
        if ( version_compare( $from_version, '3.5.0', '<' ) ) {
            $raffle_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t_raffles}", 0 );
            if ( is_array( $raffle_cols ) ) {
                if ( ! in_array( 'color_palette', $raffle_cols, true ) ) {
                    $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `color_palette` varchar(20) DEFAULT '' AFTER `prize_gallery`" );
                }
                if ( ! in_array( 'min_custom_qty', $raffle_cols, true ) ) {
                    $wpdb->query( "ALTER TABLE {$t_raffles} ADD COLUMN `min_custom_qty` int(11) NOT NULL DEFAULT 0 AFTER `color_palette`" );
                }
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
        $t_log       = $wpdb->prefix . 'rc_activity_log';
        $t_coupons   = $wpdb->prefix . 'rc_coupons';
        $t_webhooks  = $wpdb->prefix . 'rc_webhooks';

        $sql = "CREATE TABLE {$t_raffles} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            prize_value decimal(12,2) NOT NULL DEFAULT 0,
            prize_image varchar(500) DEFAULT '',
            total_tickets int(11) NOT NULL DEFAULT 0,
            ticket_digits int(1) NOT NULL DEFAULT 2,
            sold_tickets int(11) NOT NULL DEFAULT 0,
            ticket_price decimal(12,2) NOT NULL DEFAULT 0,
            packages text,
            draw_date datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            type varchar(20) NOT NULL DEFAULT 'quantity',
            max_number int(11) NOT NULL DEFAULT 0,
            countdown_threshold int(3) NOT NULL DEFAULT 0,
            winner_ticket_id bigint(20) UNSIGNED DEFAULT NULL,
            wc_product_id bigint(20) UNSIGNED DEFAULT NULL,
            lucky_numbers text DEFAULT NULL,
            font_family varchar(100) DEFAULT '',
            custom_font_url varchar(500) DEFAULT '',
            prize_gallery text DEFAULT NULL,
            color_palette varchar(20) DEFAULT '',
            min_custom_qty int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY wc_product_id (wc_product_id),
            KEY created_at (created_at)
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
            KEY status (status),
            KEY purchase_date (purchase_date),
            KEY raffle_status (raffle_id, status)
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
        ) {$charset};

        CREATE TABLE {$t_log} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            action varchar(50) NOT NULL,
            object_type varchar(50) DEFAULT '',
            object_id bigint(20) UNSIGNED DEFAULT 0,
            details text,
            ip_address varchar(45) DEFAULT '',
            entry_hash varchar(64) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY created_at (created_at),
            KEY object_lookup (object_type, object_id)
        ) {$charset};

        CREATE TABLE {$t_coupons} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            discount_type varchar(20) NOT NULL DEFAULT 'percentage',
            discount_value decimal(12,2) NOT NULL DEFAULT 0,
            max_uses int(11) NOT NULL DEFAULT 0,
            used_count int(11) NOT NULL DEFAULT 0,
            raffle_id bigint(20) UNSIGNED DEFAULT 0,
            min_tickets int(11) NOT NULL DEFAULT 0,
            expires_at datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY status (status),
            KEY raffle_id (raffle_id)
        ) {$charset};

        CREATE TABLE {$t_webhooks} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event varchar(50) NOT NULL,
            url varchar(500) NOT NULL,
            secret varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event (event),
            KEY status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
