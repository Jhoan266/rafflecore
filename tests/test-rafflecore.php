<?php
/**
 * Test funcional de RaffleCore — Verificación completa.
 *
 * Ejecutar con:
 * docker exec wp_rifas_app bash -c "cd /var/www/html && php wp-content/plugins/rafflecore/tests/test-rafflecore.php"
 */

// Bootstrap WordPress
define( 'ABSPATH', '/var/www/html/' );
define( 'WPINC', 'wp-includes' );

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

require_once ABSPATH . 'wp-load.php';

$passed = 0;
$failed = 0;

function rc_assert( $condition, $label ) {
    global $passed, $failed;
    if ( $condition ) {
        echo "  ✅ {$label}\n";
        $passed++;
    } else {
        echo "  ❌ FALLO: {$label}\n";
        $failed++;
    }
}

echo "\n🧪 Test Suite: RaffleCore v" . RAFFLECORE_VERSION . "\n";
echo str_repeat( '─', 50 ) . "\n";

// ─── Test 1: Plugin activo ──────────────────────────────────
echo "\n📌 Test 1: Plugin activo y constantes\n";
rc_assert( defined( 'RAFFLECORE_VERSION' ), 'RAFFLECORE_VERSION definida' );
rc_assert( defined( 'RAFFLECORE_PATH' ), 'RAFFLECORE_PATH definida' );
rc_assert( defined( 'RAFFLECORE_MODE' ), 'RAFFLECORE_MODE definida' );
rc_assert( RAFFLECORE_MODE === 'local', 'Modo local activo' );

// ─── Test 2: Tablas existen ─────────────────────────────────
echo "\n📌 Test 2: Tablas de la base de datos\n";
global $wpdb;
$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}rc_%'" );
rc_assert( in_array( $wpdb->prefix . 'rc_raffles', $tables ), 'Tabla rc_raffles existe' );
rc_assert( in_array( $wpdb->prefix . 'rc_purchases', $tables ), 'Tabla rc_purchases existe' );
rc_assert( in_array( $wpdb->prefix . 'rc_tickets', $tables ), 'Tabla rc_tickets existe' );

// ─── Test 3: Clases cargadas ────────────────────────────────
echo "\n📌 Test 3: Clases cargadas\n";
rc_assert( class_exists( 'RaffleCore_API_Service' ), 'RaffleCore_API_Service existe' );
rc_assert( class_exists( 'RaffleCore_Raffle_Model' ), 'RaffleCore_Raffle_Model existe' );
rc_assert( class_exists( 'RaffleCore_Ticket_Model' ), 'RaffleCore_Ticket_Model existe' );
rc_assert( class_exists( 'RaffleCore_Purchase_Model' ), 'RaffleCore_Purchase_Model existe' );
rc_assert( class_exists( 'RaffleCore_Raffle_Service' ), 'RaffleCore_Raffle_Service existe' );
rc_assert( class_exists( 'RaffleCore_Ticket_Service' ), 'RaffleCore_Ticket_Service existe' );
rc_assert( class_exists( 'RaffleCore_Purchase_Service' ), 'RaffleCore_Purchase_Service existe' );
rc_assert( class_exists( 'RaffleCore_Draw_Service' ), 'RaffleCore_Draw_Service existe' );
rc_assert( class_exists( 'RaffleCore_Email_Service' ), 'RaffleCore_Email_Service existe' );
rc_assert( class_exists( 'RaffleCore_WooCommerce' ), 'RaffleCore_WooCommerce existe' );
rc_assert( class_exists( 'RaffleCore_Admin' ), 'RaffleCore_Admin existe' );
rc_assert( class_exists( 'RaffleCore_Public' ), 'RaffleCore_Public existe' );

// ─── Test 4: API Service → Crear rifa ───────────────────────
echo "\n📌 Test 4: API Service — Crear rifa\n";
$api = new RaffleCore_API_Service();

$raffle_id = $api->create_raffle( array(
    'title'         => 'Test iPhone 16 Pro',
    'description'   => 'Rifa de prueba desde test suite.',
    'prize_value'   => 5000000,
    'prize_image'   => 'https://example.com/iphone.jpg',
    'total_tickets' => 100,
    'ticket_price'  => 5000,
    'packages'      => wp_json_encode( array(
        array( 'qty' => 5, 'price' => 20000 ),
        array( 'qty' => 10, 'price' => 35000 ),
        array( 'qty' => 25, 'price' => 75000 ),
    ) ),
    'draw_date'     => date( 'Y-m-d H:i:s', strtotime( '+30 days' ) ),
    'status'        => 'active',
) );

rc_assert( is_numeric( $raffle_id ) && $raffle_id > 0, "Rifa creada con ID: {$raffle_id}" );

// ─── Test 5: Leer rifa ─────────────────────────────────────
echo "\n📌 Test 5: Leer rifa\n";
$raffle = $api->get_raffle( $raffle_id );
rc_assert( $raffle !== null, 'Rifa encontrada' );
rc_assert( $raffle->title === 'Test iPhone 16 Pro', 'Título correcto' );
rc_assert( (int) $raffle->total_tickets === 100, 'Total tickets correcto' );
rc_assert( (float) $raffle->ticket_price == 5000, 'Precio unitario correcto' );
rc_assert( $raffle->status === 'active', 'Estado activo' );

$pkgs = json_decode( $raffle->packages, true );
rc_assert( is_array( $pkgs ) && count( $pkgs ) === 3, 'Paquetes: 3 definidos' );
rc_assert( $pkgs[0]['qty'] === 5 && $pkgs[0]['price'] === 20000, 'Paquete 1: 5×$20000' );

// ─── Test 6: Servicios de raffle ────────────────────────────
echo "\n📌 Test 6: Raffle Service\n";
$progress = RaffleCore_Raffle_Service::get_progress( $raffle );
rc_assert( $progress == 0, 'Progreso inicial: 0%' );

$avail_pkgs = RaffleCore_Raffle_Service::get_available_packages( $raffle );
rc_assert( count( $avail_pkgs ) === 3, 'Todos los paquetes disponibles' );

// ─── Test 7: Crear compra ───────────────────────────────────
echo "\n📌 Test 7: Crear compra\n";
$purchase_id = $api->create_purchase( array(
    'raffle_id'      => $raffle_id,
    'buyer_name'     => 'Juan Pérez',
    'buyer_email'    => 'juan@test.com',
    'quantity'       => 5,
    'amount_paid'    => 20000,
    'status'         => 'completed',
) );

rc_assert( is_numeric( $purchase_id ) && $purchase_id > 0, "Compra creada con ID: {$purchase_id}" );

$purchase = $api->get_purchase( $purchase_id );
rc_assert( $purchase !== null, 'Compra encontrada' );
rc_assert( $purchase->buyer_name === 'Juan Pérez', 'Nombre correcto' );
rc_assert( (int) $purchase->quantity === 5, 'Cantidad correcta' );

// ─── Test 8: Validación de compra ───────────────────────────
echo "\n📌 Test 8: Validación de compra\n";
$valid = RaffleCore_Purchase_Service::validate( $raffle, 5, 'Test', 'test@example.com' );
rc_assert( $valid === true, 'Compra válida' );

$invalid_email = RaffleCore_Purchase_Service::validate( $raffle, 5, 'Test', 'no-es-email' );
rc_assert( is_wp_error( $invalid_email ), 'Email inválido rechazado' );

$empty_name = RaffleCore_Purchase_Service::validate( $raffle, 5, '', 'test@example.com' );
rc_assert( is_wp_error( $empty_name ), 'Nombre vacío rechazado' );

$too_many = RaffleCore_Purchase_Service::validate( $raffle, 5, 'Test', 'test@example.com' );
rc_assert( $too_many === true, 'Paquete válido aceptado' );

// ─── Test 9: Generar tickets ────────────────────────────────
echo "\n📌 Test 9: Generación de boletos\n";
$tickets = $api->generate_tickets( $raffle_id, $purchase_id, 5, 'juan@test.com' );
rc_assert( ! is_wp_error( $tickets ), 'Tickets generados sin error' );

$ticket_list = RaffleCore_Ticket_Model::get_by_purchase( $purchase_id );
rc_assert( count( $ticket_list ) === 5, '5 tickets creados' );

// Verificar unicidad
$numbers = array_map( function( $t ) { return $t->ticket_number; }, $ticket_list );
rc_assert( count( $numbers ) === count( array_unique( $numbers ) ), 'Todos los números únicos' );

// Verificar rango
$all_in_range = true;
foreach ( $numbers as $n ) {
    if ( $n < 1 || $n > 100 ) { $all_in_range = false; break; }
}
rc_assert( $all_in_range, 'Todos los números en rango [1, 100]' );

// Verificar sold_tickets actualizado
$raffle_updated = $api->get_raffle( $raffle_id );
rc_assert( (int) $raffle_updated->sold_tickets === 5, 'sold_tickets actualizado a 5' );

// ─── Test 10: Segunda compra ────────────────────────────────
echo "\n📌 Test 10: Segunda compra y no colisión\n";
$purchase_id_2 = $api->create_purchase( array(
    'raffle_id'      => $raffle_id,
    'buyer_name'     => 'María López',
    'buyer_email'    => 'maria@test.com',
    'quantity'       => 10,
    'amount_paid'    => 35000,
    'status'         => 'completed',
) );

$tickets_2 = $api->generate_tickets( $raffle_id, $purchase_id_2, 10, 'maria@test.com' );
rc_assert( ! is_wp_error( $tickets_2 ), 'Segundo lote generado' );

$all_tickets = RaffleCore_Ticket_Model::get_by_raffle( $raffle_id );
rc_assert( count( $all_tickets ) === 15, '15 tickets totales' );

$all_numbers = array_map( function( $t ) { return $t->ticket_number; }, $all_tickets );
rc_assert( count( $all_numbers ) === count( array_unique( $all_numbers ) ), 'Sin colisiones entre compras' );

// ─── Test 11: Dashboard stats ───────────────────────────────
echo "\n📌 Test 11: Dashboard stats\n";
$stats = $api->get_dashboard_stats();
rc_assert( $stats['total_raffles'] >= 1, 'Total rifas >= 1' );
rc_assert( $stats['active_raffles'] >= 1, 'Rifas activas >= 1' );
rc_assert( $stats['total_tickets'] >= 15, 'Total tickets >= 15' );
rc_assert( $stats['total_revenue'] >= 55000, 'Revenue >= $55,000' );
rc_assert( $stats['total_purchases'] >= 2, 'Total compras >= 2' );
rc_assert( is_array( $stats['recent_purchases'] ), 'recent_purchases es array' );

// ─── Test 12: Sorteo ────────────────────────────────────────
echo "\n📌 Test 12: Sorteo\n";
$draw_service = new RaffleCore_Draw_Service( $api );
$result = $draw_service->execute_draw( $raffle_id );
rc_assert( ! is_wp_error( $result ), 'Sorteo ejecutado' );
rc_assert( isset( $result->ticket_number ), 'Resultado tiene ticket_number' );
rc_assert( isset( $result->buyer_name ), 'Resultado tiene buyer_name' );

$raffle_after_draw = $api->get_raffle( $raffle_id );
rc_assert( $raffle_after_draw->status === 'finished', 'Rifa marcada como finished' );
rc_assert( $raffle_after_draw->winner_ticket_id > 0, 'winner_ticket_id establecido' );

echo "\n📌 Resultado del sorteo: Boleto #{$result->ticket_number} — {$result->buyer_name}\n";

// ─── Test 13: Shortcode ─────────────────────────────────────
echo "\n📌 Test 13: Shortcode\n";
rc_assert( shortcode_exists( 'rafflecore' ), 'Shortcode [rafflecore] registrado' );

// ─── Test 14: prepare_data packages ─────────────────────────
echo "\n📌 Test 14: prepare_data packages\n";
$test_post = array(
    'title'         => 'Test',
    'description'   => '',
    'total_tickets' => 100,
    'ticket_price'  => 5000,
    'draw_date'     => '',
    'status'        => 'active',
    'packages'      => '5:20000, 10:35000, 25:75000',
    'prize_image'   => '',
);
$prepared = RaffleCore_Raffle_Service::prepare_data( $test_post );
$pkgs_prepared = json_decode( $prepared['packages'], true );
rc_assert( is_array( $pkgs_prepared ) && count( $pkgs_prepared ) === 3, 'prepare_data: 3 paquetes parseados' );
rc_assert( $pkgs_prepared[0]['qty'] === 5 && $pkgs_prepared[0]['price'] === 20000, 'prepare_data: par qty:price correcto' );

// ─── Test 15: WooCommerce disponible ────────────────────────
echo "\n📌 Test 15: WooCommerce\n";
$wc = new RaffleCore_WooCommerce( $api );
rc_assert( $wc->is_available(), 'WooCommerce detectado como disponible' );

// ─── Test 16: Nuevas clases v2.0.0 ─────────────────────────
echo "\n📌 Test 16: Clases v2.0.0\n";
rc_assert( class_exists( 'RaffleCore_WC_Product_Manager' ), 'RaffleCore_WC_Product_Manager existe' );
rc_assert( class_exists( 'RaffleCore_Reservation_Service' ), 'RaffleCore_Reservation_Service existe' );
rc_assert( class_exists( 'RaffleCore_Activator' ) && defined( 'RaffleCore_Activator::DB_VERSION' ), 'Activator::DB_VERSION definida' );
rc_assert( RaffleCore_Activator::DB_VERSION === '2.0.0', 'DB_VERSION es 2.0.0' );

// ─── Test 17: Esquema v2.0.0 — columnas correctas ──────────
echo "\n📌 Test 17: Esquema v2.0.0\n";
$cols_purchases = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}rc_purchases", 0 );
rc_assert( in_array( 'amount_paid', $cols_purchases, true ), 'Columna amount_paid existe' );
rc_assert( in_array( 'status', $cols_purchases, true ), 'Columna status existe' );
rc_assert( in_array( 'order_id', $cols_purchases, true ), 'Columna order_id existe' );
rc_assert( ! in_array( 'total_amount', $cols_purchases, true ), 'Columna total_amount NO existe (migrada)' );
rc_assert( ! in_array( 'payment_status', $cols_purchases, true ), 'Columna payment_status NO existe (migrada)' );

$cols_raffles = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}rc_raffles", 0 );
rc_assert( in_array( 'wc_product_id', $cols_raffles, true ), 'Columna wc_product_id existe en raffles' );

// ─── Test 18: Reservation Service ───────────────────────────
echo "\n📌 Test 18: Reservation Service\n";

$res_raffle_id = $api->create_raffle( array(
    'title'         => 'Test Reservas',
    'total_tickets' => 20,
    'ticket_price'  => 1000,
    'packages'      => wp_json_encode( array( array( 'qty' => 5, 'price' => 4000 ) ) ),
    'status'        => 'active',
) );

rc_assert( is_numeric( $res_raffle_id ), "Rifa de reservas creada ID: {$res_raffle_id}" );

// Reservar 5 boletos
$reserve_result = RaffleCore_Reservation_Service::reserve( $res_raffle_id, 5 );
rc_assert( $reserve_result === true, 'Reserva de 5 boletos exitosa' );

$raffle_after_reserve = $api->get_raffle( $res_raffle_id );
rc_assert( (int) $raffle_after_reserve->sold_tickets === 5, 'sold_tickets incrementado a 5 tras reserva' );

// Reservar 10 mas
$reserve_result_2 = RaffleCore_Reservation_Service::reserve( $res_raffle_id, 10 );
rc_assert( $reserve_result_2 === true, 'Segunda reserva de 10 exitosa' );

$raffle_after_2 = $api->get_raffle( $res_raffle_id );
rc_assert( (int) $raffle_after_2->sold_tickets === 15, 'sold_tickets = 15 tras 2 reservas' );

// Intentar reservar mas de lo disponible (quedan 5)
$reserve_fail = RaffleCore_Reservation_Service::reserve( $res_raffle_id, 10 );
rc_assert( is_wp_error( $reserve_fail ), 'Reserva rechazada si excede disponibles' );

// Liberar la segunda reserva
$release_result = RaffleCore_Reservation_Service::release( $res_raffle_id, 10 );
rc_assert( $release_result === true, 'Liberacion de 10 boletos exitosa' );

$raffle_after_release = $api->get_raffle( $res_raffle_id );
rc_assert( (int) $raffle_after_release->sold_tickets === 5, 'sold_tickets = 5 tras liberar 10' );

// Cleanup
$api->delete_raffle( $res_raffle_id );

// ─── Test 19: WC Product Manager — crear producto ───────────
echo "\n📌 Test 19: WC Product Manager\n";

$pm_raffle_id = $api->create_raffle( array(
    'title'         => 'Test WC Product',
    'total_tickets' => 50,
    'ticket_price'  => 2000,
    'packages'      => wp_json_encode( array( array( 'qty' => 5, 'price' => 8000 ) ) ),
    'status'        => 'active',
) );

$product_id = RaffleCore_WC_Product_Manager::ensure_product( $pm_raffle_id, 'Test WC Product', 2000 );
rc_assert( ! is_wp_error( $product_id ) && $product_id > 0, "Producto WC creado ID: {$product_id}" );

// Verificar que es virtual y oculto
$product = wc_get_product( $product_id );
rc_assert( $product !== false && $product !== null, 'Producto WC encontrado' );
rc_assert( $product->is_virtual(), 'Producto es virtual' );
rc_assert( $product->get_catalog_visibility() === 'hidden', 'Producto oculto del catalogo' );
rc_assert( $product->get_meta( '_rc_raffle_id' ) == $pm_raffle_id, 'Meta _rc_raffle_id correcto' );

// ensure_product devuelve el mismo ID si ya existe
$product_id_2 = RaffleCore_WC_Product_Manager::ensure_product( $pm_raffle_id, 'Test WC Product', 2000 );
rc_assert( $product_id_2 === $product_id, 'ensure_product idempotente (mismo ID)' );

// Verificar wc_product_id en rc_raffles
$pm_raffle = $api->get_raffle( $pm_raffle_id );
rc_assert( (int) $pm_raffle->wc_product_id === $product_id, 'wc_product_id vinculado en rc_raffles' );

// Sync producto
RaffleCore_WC_Product_Manager::sync_product( $pm_raffle_id, 'Nombre Actualizado', 3000 );
$product_updated = wc_get_product( $product_id );
rc_assert( $product_updated->get_name() === 'Boletos — Nombre Actualizado', 'sync_product actualiza nombre' );
rc_assert( (float) $product_updated->get_regular_price() === 3000.0, 'sync_product actualiza precio' );

// Delete producto
RaffleCore_WC_Product_Manager::delete_product( $pm_raffle_id );
$product_deleted = wc_get_product( $product_id );
rc_assert( $product_deleted === false || $product_deleted === null, 'Producto WC eliminado' );

// Cleanup
$api->delete_raffle( $pm_raffle_id );

// ─── Test 20: Boletos rango [1, total] en v2 ───────────────
echo "\n📌 Test 20: Boletos rango [1, total]\n";

$range_raffle_id = $api->create_raffle( array(
    'title'         => 'Test Rango',
    'total_tickets' => 10,
    'ticket_price'  => 100,
    'packages'      => wp_json_encode( array( array( 'qty' => 10, 'price' => 900 ) ) ),
    'status'        => 'active',
) );

$range_purchase_id = $api->create_purchase( array(
    'raffle_id'   => $range_raffle_id,
    'buyer_name'  => 'Rango Test',
    'buyer_email' => 'rango@test.com',
    'quantity'    => 10,
    'amount_paid' => 900,
    'status'      => 'completed',
) );

$range_tickets = $api->generate_tickets( $range_raffle_id, $range_purchase_id, 10, 'rango@test.com' );
rc_assert( ! is_wp_error( $range_tickets ), '10/10 tickets generados' );

$range_all_valid = true;
foreach ( $range_tickets as $t ) {
    if ( $t < 1 || $t > 10 ) { $range_all_valid = false; break; }
}
rc_assert( $range_all_valid, 'Todos en rango [1, 10]' );
rc_assert( ! in_array( 0, $range_tickets, true ), 'Ningun ticket es 0' );

$range_sorted = $range_tickets;
sort( $range_sorted );
rc_assert( $range_sorted === range( 1, 10 ), '10/10 = todos los numeros del 1 al 10' );

// Cleanup
$api->delete_raffle( $range_raffle_id );

// ─── Cleanup ────────────────────────────────────────────────
echo "\n📌 Limpieza\n";
$api->delete_raffle( $raffle_id );
$verify = $api->get_raffle( $raffle_id );
rc_assert( $verify === null, 'Rifa eliminada correctamente (cascada)' );

$remaining_tickets = RaffleCore_Ticket_Model::get_by_raffle( $raffle_id );
rc_assert( count( $remaining_tickets ) === 0, 'Tickets eliminados en cascada' );

// ─── Resumen ────────────────────────────────────────────────
echo "\n" . str_repeat( '═', 50 ) . "\n";
echo "📊 Resultado: {$passed} pasaron, {$failed} fallaron de " . ( $passed + $failed ) . " tests\n";

if ( $failed === 0 ) {
    echo "🎉 ¡Todos los tests pasaron!\n\n";
    exit( 0 );
} else {
    echo "⚠️ Hay tests que fallaron.\n\n";
    exit( 1 );
}
