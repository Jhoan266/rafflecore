<?php
/**
 * Plugin Name: RaffleCore
 * Plugin URI:  https://rafflecore.com
 * Description: Sistema profesional de rifas con WooCommerce. Arquitectura SaaS-ready con API Service layer.
 * Version:     3.2.0
 * Author:      RaffleCore
 * Text Domain: rafflecore
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * License:     GPL-2.0+
 *
 * Arquitectura:
 *   - API Service: Capa de abstracción que permite cambiar de BD local a API externa (SaaS) sin tocar módulos.
 *   - Módulos: raffle, ticket, purchase, draw, email, woocommerce (cada uno con Model + Service).
 *   - WooCommerce: Producto virtual oculto por rifa. Paquete como metadata del ítem en carrito.
 *   - Anticolisión: Generación de boletos sin bucles de adivinanza (pool de disponibles + Fisher-Yates).
 *   - Race Conditions: FOR UPDATE + transacciones atómicas + validación pre-pago.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Constantes ─────────────────────────────────────────────
define( 'RAFFLECORE_VERSION', defined('WP_DEBUG') && WP_DEBUG ? '3.2.0-' . time() : '3.2.0' );
define( 'RAFFLECORE_PATH',     plugin_dir_path( __FILE__ ) );
define( 'RAFFLECORE_URL',      plugin_dir_url( __FILE__ ) );
define( 'RAFFLECORE_BASENAME', plugin_basename( __FILE__ ) );

// Modo de operación: 'local' (BD WordPress) o 'api' (SaaS externo)
if ( ! defined( 'RAFFLECORE_MODE' ) ) {
    define( 'RAFFLECORE_MODE', get_option( 'rafflecore_mode', 'local' ) );
}

// Paquetes de boletos disponibles (cantidades fijas)
if ( ! defined( 'RAFFLECORE_PACKAGES' ) ) {
    define( 'RAFFLECORE_PACKAGES', '15,30,45,90' );
}

// ─── Autoload de clases ─────────────────────────────────────
// Core
require_once RAFFLECORE_PATH . 'includes/class-rafflecore-loader.php';
require_once RAFFLECORE_PATH . 'includes/class-rafflecore-activator.php';
require_once RAFFLECORE_PATH . 'includes/class-rafflecore.php';

// API Service — Provider pattern (SaaS-ready)
require_once RAFFLECORE_PATH . 'api/interface-data-provider.php';
require_once RAFFLECORE_PATH . 'api/class-local-provider.php';
require_once RAFFLECORE_PATH . 'api/class-remote-provider.php';
require_once RAFFLECORE_PATH . 'api/class-api-service.php';

// Módulos — Model + Service
require_once RAFFLECORE_PATH . 'modules/raffle/class-raffle-model.php';
require_once RAFFLECORE_PATH . 'modules/raffle/class-raffle-service.php';
require_once RAFFLECORE_PATH . 'modules/ticket/class-ticket-model.php';
require_once RAFFLECORE_PATH . 'modules/ticket/class-ticket-service.php';
require_once RAFFLECORE_PATH . 'modules/purchase/class-purchase-model.php';
require_once RAFFLECORE_PATH . 'modules/purchase/class-purchase-service.php';
require_once RAFFLECORE_PATH . 'modules/draw/class-draw-service.php';
require_once RAFFLECORE_PATH . 'modules/email/class-email-service.php';
require_once RAFFLECORE_PATH . 'modules/woocommerce/class-woocommerce-integration.php';
require_once RAFFLECORE_PATH . 'modules/woocommerce/class-wc-product-manager.php';
require_once RAFFLECORE_PATH . 'modules/woocommerce/class-wc-gateway-wompi.php';
require_once RAFFLECORE_PATH . 'modules/purchase/class-reservation-service.php';

// New modules v3.0.0
require_once RAFFLECORE_PATH . 'includes/class-rafflecore-rate-limiter.php';
require_once RAFFLECORE_PATH . 'includes/class-rafflecore-logger.php';
require_once RAFFLECORE_PATH . 'includes/class-rafflecore-currency.php';
require_once RAFFLECORE_PATH . 'modules/coupon/class-coupon-model.php';
require_once RAFFLECORE_PATH . 'modules/coupon/class-coupon-service.php';
require_once RAFFLECORE_PATH . 'modules/webhook/class-webhook-service.php';

// Admin & Public controllers
require_once RAFFLECORE_PATH . 'admin/class-rafflecore-admin.php';
require_once RAFFLECORE_PATH . 'admin/class-rafflecore-analytics.php';
require_once RAFFLECORE_PATH . 'admin/class-rafflecore-export.php';
require_once RAFFLECORE_PATH . 'admin/class-rafflecore-rest-api.php';
require_once RAFFLECORE_PATH . 'public/class-rafflecore-public.php';

// Gutenberg Block
require_once RAFFLECORE_PATH . 'blocks/class-rafflecore-block.php';

// ─── Hooks de ciclo de vida ─────────────────────────────────
register_activation_hook( __FILE__, function ( $network_wide ) {
    RaffleCore_Activator::activate( $network_wide );
} );

register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'rc_cleanup_reservations' );
    wp_clear_scheduled_hook( 'rc_cleanup_old_transactions' );
} );

// Multisite: crear tablas cuando se añade un nuevo sitio
add_action( 'wp_initialize_site', function ( $new_site ) {
    RaffleCore_Activator::on_new_blog( $new_site->blog_id );
}, 10, 1 );

// Inicialización + auto-migración en upgrades
add_action( 'plugins_loaded', function () {
    // Ejecutar migración si la versión de BD es anterior (cubre actualizaciones sin desactivar/reactivar)
    $db_version = get_option( 'rafflecore_db_version', '0' );
    if ( version_compare( $db_version, RaffleCore_Activator::DB_VERSION, '<' ) ) {
        RaffleCore_Activator::activate();
    }

    $plugin = new RaffleCore();
    $plugin->run();

    // Analytics AJAX
    new RaffleCore_Analytics();

    // Export AJAX
    new RaffleCore_Export();

    // REST API
    new RaffleCore_REST_API();

    // Gutenberg Block
    RaffleCore_Block::register();
} );
