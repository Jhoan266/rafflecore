<?php
/**
 * RaffleCore Uninstall — Limpieza completa al desinstalar el plugin.
 *
 * Se ejecuta solo cuando el usuario elimina el plugin desde WP Admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Eliminar tablas
$tables = array(
    $wpdb->prefix . 'rc_raffles',
    $wpdb->prefix . 'rc_purchases',
    $wpdb->prefix . 'rc_tickets',
    $wpdb->prefix . 'rc_activity_log',
    $wpdb->prefix . 'rc_coupons',
    $wpdb->prefix . 'rc_webhooks',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Eliminar opciones
$options = array(
    'rafflecore_version',
    'rafflecore_db_version',
    'rafflecore_mode',
    'rafflecore_api_url',
    'rafflecore_api_key',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Limpiar cron
wp_clear_scheduled_hook( 'rc_cleanup_reservations' );

// Limpiar transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_rc_%'" );

// Eliminar order meta de WooCommerce
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_rc_%'" );

// Eliminar productos WC vinculados (virtual ocultos)
$product_ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_rc_raffle_product' AND meta_value = 'yes'" );
foreach ( $product_ids as $pid ) {
    wp_delete_post( $pid, true );
}
