<?php
/**
 * Plugin Name: RaffleCore
 * Plugin URI:  https://rafflecore.com
 * Description: Sistema profesional de rifas con WooCommerce. Arquitectura SaaS-ready con API Service layer.
 * Version:     2.0.0
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
define( 'RAFFLECORE_VERSION',  '2.0.0' );
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

// API Service (capa de abstracción SaaS-ready)
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
require_once RAFFLECORE_PATH . 'modules/purchase/class-reservation-service.php';

// Admin & Public controllers
require_once RAFFLECORE_PATH . 'admin/class-rafflecore-admin.php';
require_once RAFFLECORE_PATH . 'public/class-rafflecore-public.php';

// ─── Hooks de ciclo de vida ─────────────────────────────────
register_activation_hook( __FILE__, array( 'RaffleCore_Activator', 'activate' ) );

// Inicialización + auto-migración en upgrades
add_action( 'plugins_loaded', function () {
    // Ejecutar migración si la versión de BD es anterior (cubre actualizaciones sin desactivar/reactivar)
    $db_version = get_option( 'rafflecore_db_version', '0' );
    if ( version_compare( $db_version, RaffleCore_Activator::DB_VERSION, '<' ) ) {
        RaffleCore_Activator::activate();
    }

    $plugin = new RaffleCore();
    $plugin->run();
} );
