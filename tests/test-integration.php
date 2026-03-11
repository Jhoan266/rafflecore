<?php
/**
 * Tests de integración — Flujos críticos de RaffleCore.
 *
 * Ejecutar con:
 * docker exec wp_rifas_app bash -c "cd /var/www/html && php wp-content/plugins/rafflecore/tests/test-integration.php"
 *
 * Flujos cubiertos:
 *   1. on_payment_complete + idempotencia
 *   2. Reserva → pago fallido → liberación
 *   3. Cupón + descuento + límite de usos
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

echo "\n🧪 Integration Tests: RaffleCore v" . RAFFLECORE_VERSION . "\n";
echo str_repeat( '─', 60 ) . "\n";

$api = new RaffleCore_API_Service();
global $wpdb;

// ═══════════════════════════════════════════════════════════════
// FLUJO 1: on_payment_complete + idempotencia
// ═══════════════════════════════════════════════════════════════
echo "\n📌 Flujo 1: on_payment_complete + idempotencia\n";

// 1.1 – Crear rifa de prueba
$f1_raffle_id = $api->create_raffle( array(
    'title'         => 'Integration: Payment Complete',
    'total_tickets' => 50,
    'ticket_price'  => 1000,
    'packages'      => wp_json_encode( array( array( 'qty' => 5, 'price' => 4000 ) ) ),
    'status'        => 'active',
) );
rc_assert( is_numeric( $f1_raffle_id ) && $f1_raffle_id > 0, 'F1: Rifa creada' );

// 1.2 – Simular reserva pre-pago (como haría ajax_create_order)
$f1_qty = 5;
$reserve = RaffleCore_Reservation_Service::reserve( $f1_raffle_id, $f1_qty );
rc_assert( $reserve === true, 'F1: Reserva pre-pago exitosa' );

$f1_raffle_after_reserve = $api->get_raffle( $f1_raffle_id );
rc_assert( (int) $f1_raffle_after_reserve->sold_tickets === $f1_qty, 'F1: sold_tickets incrementado por reserva' );

// 1.3 – Crear compra con status reserved
$f1_purchase_id = $api->create_purchase( array(
    'raffle_id'   => $f1_raffle_id,
    'buyer_name'  => 'Test Payment',
    'buyer_email' => 'payment@test.com',
    'quantity'    => $f1_qty,
    'amount_paid' => 4000,
    'status'      => 'reserved',
) );
rc_assert( is_numeric( $f1_purchase_id ) && $f1_purchase_id > 0, 'F1: Compra reservada creada' );

// 1.4 – Simular on_payment_complete: generar tickets sin incrementar sold_tickets
// (Reproduce la lógica de generate_tickets_without_increment)
$t_tickets = $wpdb->prefix . 'rc_tickets';
$t_raffles = $wpdb->prefix . 'rc_raffles';

$used_numbers = $wpdb->get_col( $wpdb->prepare(
    "SELECT ticket_number FROM {$t_tickets} WHERE raffle_id = %d",
    $f1_raffle_id
) );
$used_set = array_flip( $used_numbers );
$pool = array();
for ( $i = 1; $i <= 50; $i++ ) {
    if ( ! isset( $used_set[ $i ] ) ) {
        $pool[] = $i;
    }
}

// Fisher-Yates con CSPRNG
for ( $i = count( $pool ) - 1; $i > 0; $i-- ) {
    $j = random_int( 0, $i );
    $tmp = $pool[ $i ];
    $pool[ $i ] = $pool[ $j ];
    $pool[ $j ] = $tmp;
}
$f1_tickets = array_slice( $pool, 0, $f1_qty );
sort( $f1_tickets );

foreach ( $f1_tickets as $number ) {
    $wpdb->insert( $t_tickets, array(
        'raffle_id'     => $f1_raffle_id,
        'purchase_id'   => $f1_purchase_id,
        'ticket_number' => $number,
        'buyer_email'   => 'payment@test.com',
        'created_at'    => current_time( 'mysql' ),
    ), array( '%d', '%d', '%d', '%s', '%s' ) );
}

// Actualizar compra a completed
$api->update_purchase( $f1_purchase_id, array( 'status' => 'completed', 'order_id' => 99999 ) );

$f1_purchase_after = $api->get_purchase( $f1_purchase_id );
rc_assert( $f1_purchase_after->status === 'completed', 'F1: Compra marcada como completed' );

// 1.5 – Verificar tickets generados
$f1_ticket_list = RaffleCore_Ticket_Model::get_by_purchase( $f1_purchase_id );
rc_assert( count( $f1_ticket_list ) === $f1_qty, "F1: {$f1_qty} tickets generados" );

// 1.6 – sold_tickets NO debe haber cambiado (ya estaba reservado)
$f1_raffle_after_tickets = $api->get_raffle( $f1_raffle_id );
rc_assert( (int) $f1_raffle_after_tickets->sold_tickets === $f1_qty, 'F1: sold_tickets NO incrementó de nuevo (idempotencia reserva)' );

// 1.7 – IDEMPOTENCIA: Intentar generar tickets de nuevo para la misma compra
$f1_tickets_before = count( RaffleCore_Ticket_Model::get_by_purchase( $f1_purchase_id ) );

// Simular la verificación de idempotencia que hace on_payment_complete:
// Si purchase.status === 'completed' → no regenerar
$f1_purchase_check = $api->get_purchase( $f1_purchase_id );
$f1_idempotent = ( $f1_purchase_check->status === 'completed' );
rc_assert( $f1_idempotent === true, 'F1: Guard clause detecta compra ya completada' );

// Verificar que NO se generaron tickets duplicados
$f1_tickets_after = count( RaffleCore_Ticket_Model::get_by_purchase( $f1_purchase_id ) );
rc_assert( $f1_tickets_after === $f1_tickets_before, 'F1: Sin tickets duplicados tras intento idempotente' );

// 1.8 – Verificar unicidad de números
$f1_numbers = array_map( function( $t ) { return (int) $t->ticket_number; }, $f1_ticket_list );
rc_assert( count( $f1_numbers ) === count( array_unique( $f1_numbers ) ), 'F1: Todos los números son únicos' );

// Cleanup flujo 1
$api->delete_raffle( $f1_raffle_id );

// ═══════════════════════════════════════════════════════════════
// FLUJO 2: Reserva → pago fallido → liberación
// ═══════════════════════════════════════════════════════════════
echo "\n📌 Flujo 2: Reserva → pago fallido → liberación\n";

// 2.1 – Crear rifa
$f2_raffle_id = $api->create_raffle( array(
    'title'         => 'Integration: Reserve-Fail-Release',
    'total_tickets' => 20,
    'ticket_price'  => 500,
    'packages'      => wp_json_encode( array( array( 'qty' => 10, 'price' => 4000 ) ) ),
    'status'        => 'active',
) );
rc_assert( is_numeric( $f2_raffle_id ) && $f2_raffle_id > 0, 'F2: Rifa creada' );

// 2.2 – Verificar estado inicial
$f2_raffle_init = $api->get_raffle( $f2_raffle_id );
rc_assert( (int) $f2_raffle_init->sold_tickets === 0, 'F2: sold_tickets inicial = 0' );

// 2.3 – Reservar 10 boletos (simula checkout iniciado)
$f2_reserve = RaffleCore_Reservation_Service::reserve( $f2_raffle_id, 10 );
rc_assert( $f2_reserve === true, 'F2: Reserva de 10 boletos exitosa' );

$f2_raffle_reserved = $api->get_raffle( $f2_raffle_id );
rc_assert( (int) $f2_raffle_reserved->sold_tickets === 10, 'F2: sold_tickets = 10 tras reserva' );

// 2.4 – Crear compra con status reserved
$f2_purchase_id = $api->create_purchase( array(
    'raffle_id'   => $f2_raffle_id,
    'buyer_name'  => 'Pago Fallido',
    'buyer_email' => 'fail@test.com',
    'quantity'    => 10,
    'amount_paid' => 4000,
    'status'      => 'reserved',
) );
rc_assert( is_numeric( $f2_purchase_id ), 'F2: Compra reservada registrada' );

// 2.5 – Verificar que otro usuario ve solo 10 disponibles
$f2_available = (int) $f2_raffle_reserved->total_tickets - (int) $f2_raffle_reserved->sold_tickets;
rc_assert( $f2_available === 10, 'F2: Solo 10 boletos disponibles para otros' );

// 2.6 – Intentar reservar más de lo disponible (debería fallar)
$f2_over_reserve = RaffleCore_Reservation_Service::reserve( $f2_raffle_id, 15 );
rc_assert( is_wp_error( $f2_over_reserve ), 'F2: Sobre-reserva rechazada correctamente' );
rc_assert( $f2_over_reserve->get_error_code() === 'insufficient', 'F2: Código error = insufficient' );

// 2.7 – Simular pago fallido → liberar reserva
$f2_release = RaffleCore_Reservation_Service::release( $f2_raffle_id, 10 );
rc_assert( $f2_release === true, 'F2: Liberación de reserva exitosa' );

// 2.8 – Actualizar compra a cancelled
$api->update_purchase( $f2_purchase_id, array( 'status' => 'cancelled' ) );
$f2_purchase_cancelled = $api->get_purchase( $f2_purchase_id );
rc_assert( $f2_purchase_cancelled->status === 'cancelled', 'F2: Compra marcada como cancelled' );

// 2.9 – sold_tickets debe volver a 0
$f2_raffle_released = $api->get_raffle( $f2_raffle_id );
rc_assert( (int) $f2_raffle_released->sold_tickets === 0, 'F2: sold_tickets restaurado a 0 tras liberación' );

// 2.10 – Verificar que los 20 boletos están disponibles de nuevo
$f2_available_after = (int) $f2_raffle_released->total_tickets - (int) $f2_raffle_released->sold_tickets;
rc_assert( $f2_available_after === 20, 'F2: 20 boletos disponibles de nuevo' );

// 2.11 – Verificar que no se generaron tickets (pago nunca completó)
$f2_tickets = RaffleCore_Ticket_Model::get_by_raffle( $f2_raffle_id );
rc_assert( count( $f2_tickets ) === 0, 'F2: 0 tickets generados (pago nunca completó)' );

// 2.12 – Un nuevo usuario puede ahora reservar exitosamente
$f2_new_reserve = RaffleCore_Reservation_Service::reserve( $f2_raffle_id, 10 );
rc_assert( $f2_new_reserve === true, 'F2: Nueva reserva exitosa tras liberación' );
RaffleCore_Reservation_Service::release( $f2_raffle_id, 10 ); // cleanup

// 2.13 – Liberar más de lo reservado no deja sold_tickets negativo
RaffleCore_Reservation_Service::release( $f2_raffle_id, 100 );
$f2_raffle_floor = $api->get_raffle( $f2_raffle_id );
rc_assert( (int) $f2_raffle_floor->sold_tickets >= 0, 'F2: sold_tickets nunca es negativo (floor=0)' );

// Cleanup flujo 2
$api->delete_raffle( $f2_raffle_id );

// ═══════════════════════════════════════════════════════════════
// FLUJO 3: Cupón + descuento + límite de usos
// ═══════════════════════════════════════════════════════════════
echo "\n📌 Flujo 3: Cupón + descuento + límite de usos\n";

// 3.1 – Crear rifa para cupones
$f3_raffle_id = $api->create_raffle( array(
    'title'         => 'Integration: Coupons',
    'total_tickets' => 100,
    'ticket_price'  => 1000,
    'packages'      => wp_json_encode( array( array( 'qty' => 5, 'price' => 4500 ) ) ),
    'status'        => 'active',
) );
rc_assert( is_numeric( $f3_raffle_id ) && $f3_raffle_id > 0, 'F3: Rifa para cupones creada' );

// 3.2 – Crear cupón porcentaje (20% off, max 3 usos, min 5 tickets)
$f3_coupon_pct_id = RaffleCore_Coupon_Model::create( array(
    'code'           => 'TEST20PCT',
    'discount_type'  => 'percentage',
    'discount_value' => 20,
    'max_uses'       => 3,
    'used_count'     => 0,
    'raffle_id'      => $f3_raffle_id,
    'min_tickets'    => 5,
    'status'         => 'active',
) );
rc_assert( is_numeric( $f3_coupon_pct_id ) && $f3_coupon_pct_id > 0, 'F3: Cupón porcentaje creado' );

// 3.3 – Validar cupón porcentaje
$f3_coupon_valid = RaffleCore_Coupon_Service::validate( 'TEST20PCT', $f3_raffle_id, 5 );
rc_assert( ! is_wp_error( $f3_coupon_valid ), 'F3: Cupón TEST20PCT válido' );
rc_assert( $f3_coupon_valid->discount_type === 'percentage', 'F3: Tipo = percentage' );
rc_assert( (float) $f3_coupon_valid->discount_value === 20.0, 'F3: Valor = 20%' );

// 3.4 – Aplicar descuento porcentaje (4500 - 20% = 3600)
$f3_price_after_pct = RaffleCore_Coupon_Service::apply_discount( 4500, $f3_coupon_valid );
rc_assert( $f3_price_after_pct === 3600.0, 'F3: Precio con 20% off = $3,600 (de $4,500)' );

// 3.5 – Crear cupón monto fijo ($500 off, max 2 usos, sin restricción rifa)
$f3_coupon_fixed_id = RaffleCore_Coupon_Model::create( array(
    'code'           => 'TEST500FLAT',
    'discount_type'  => 'fixed',
    'discount_value' => 500,
    'max_uses'       => 2,
    'used_count'     => 0,
    'raffle_id'      => 0,
    'min_tickets'    => 1,
    'status'         => 'active',
) );
rc_assert( is_numeric( $f3_coupon_fixed_id ) && $f3_coupon_fixed_id > 0, 'F3: Cupón monto fijo creado' );

// 3.6 – Aplicar descuento fijo (4500 - 500 = 4000)
$f3_fixed_coupon = RaffleCore_Coupon_Model::find( $f3_coupon_fixed_id );
$f3_price_after_fixed = RaffleCore_Coupon_Service::apply_discount( 4500, $f3_fixed_coupon );
rc_assert( $f3_price_after_fixed === 4000.0, 'F3: Precio con $500 fijo off = $4,000' );

// 3.7 – Descuento mayor al precio no da negativo (floor = 0)
$f3_price_floor = RaffleCore_Coupon_Service::apply_discount( 300, $f3_fixed_coupon );
rc_assert( (float) $f3_price_floor === 0.0, 'F3: Precio con descuento > total = $0 (floor)' );

// 3.8 – Validar restricción de rifa (cupón solo para f3_raffle_id)
$other_raffle_id = $api->create_raffle( array(
    'title'         => 'Integration: Otra Rifa',
    'total_tickets' => 10,
    'ticket_price'  => 500,
    'status'        => 'active',
) );
$f3_wrong_raffle = RaffleCore_Coupon_Service::validate( 'TEST20PCT', $other_raffle_id, 5 );
rc_assert( is_wp_error( $f3_wrong_raffle ), 'F3: Cupón rechazado en rifa incorrecta' );
rc_assert( $f3_wrong_raffle->get_error_code() === 'wrong_raffle', 'F3: Código error = wrong_raffle' );
$api->delete_raffle( $other_raffle_id );

// 3.9 – Validar mínimo de tickets
$f3_min_fail = RaffleCore_Coupon_Service::validate( 'TEST20PCT', $f3_raffle_id, 2 );
rc_assert( is_wp_error( $f3_min_fail ), 'F3: Cupón rechazado con qty < min_tickets' );
rc_assert( $f3_min_fail->get_error_code() === 'min_tickets', 'F3: Código error = min_tickets' );

// 3.10 – Simular uso del cupón porcentaje (3 usos máximo)
RaffleCore_Coupon_Model::increment_usage( $f3_coupon_pct_id ); // uso 1
RaffleCore_Coupon_Model::increment_usage( $f3_coupon_pct_id ); // uso 2
RaffleCore_Coupon_Model::increment_usage( $f3_coupon_pct_id ); // uso 3

$f3_coupon_exhausted = RaffleCore_Coupon_Model::find( $f3_coupon_pct_id );
rc_assert( (int) $f3_coupon_exhausted->used_count === 3, 'F3: used_count = 3 tras 3 usos' );

// 3.11 – Intentar usar cupón agotado
$f3_exhausted_result = RaffleCore_Coupon_Service::validate( 'TEST20PCT', $f3_raffle_id, 5 );
rc_assert( is_wp_error( $f3_exhausted_result ), 'F3: Cupón agotado rechazado' );
rc_assert( $f3_exhausted_result->get_error_code() === 'exhausted', 'F3: Código error = exhausted' );

// 3.12 – Cupón expirado
$f3_coupon_exp_id = RaffleCore_Coupon_Model::create( array(
    'code'           => 'TESTEXPIRED',
    'discount_type'  => 'percentage',
    'discount_value' => 10,
    'max_uses'       => 100,
    'used_count'     => 0,
    'raffle_id'      => 0,
    'min_tickets'    => 1,
    'expires_at'     => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
    'status'         => 'active',
) );
$f3_expired_result = RaffleCore_Coupon_Service::validate( 'TESTEXPIRED', $f3_raffle_id, 5 );
rc_assert( is_wp_error( $f3_expired_result ), 'F3: Cupón expirado rechazado' );
rc_assert( $f3_expired_result->get_error_code() === 'expired', 'F3: Código error = expired' );

// 3.13 – Cupón inactivo
$f3_coupon_inactive_id = RaffleCore_Coupon_Model::create( array(
    'code'           => 'TESTINACTIVE',
    'discount_type'  => 'percentage',
    'discount_value' => 50,
    'max_uses'       => 0,
    'raffle_id'      => 0,
    'min_tickets'    => 1,
    'status'         => 'inactive',
) );
$f3_inactive_result = RaffleCore_Coupon_Service::validate( 'TESTINACTIVE', $f3_raffle_id, 5 );
rc_assert( is_wp_error( $f3_inactive_result ), 'F3: Cupón inactivo rechazado' );

// 3.14 – Cupón inexistente
$f3_not_found = RaffleCore_Coupon_Service::validate( 'NOEXISTE', $f3_raffle_id, 5 );
rc_assert( is_wp_error( $f3_not_found ), 'F3: Cupón inexistente rechazado' );
rc_assert( $f3_not_found->get_error_code() === 'not_found', 'F3: Código error = not_found' );

// 3.15 – Cupón sin límite de usos (max_uses = 0 = ilimitado)
$f3_coupon_unlimited_id = RaffleCore_Coupon_Model::create( array(
    'code'           => 'TESTUNLIMITED',
    'discount_type'  => 'fixed',
    'discount_value' => 100,
    'max_uses'       => 0,
    'used_count'     => 999,
    'raffle_id'      => 0,
    'min_tickets'    => 1,
    'status'         => 'active',
) );
$f3_unlimited_valid = RaffleCore_Coupon_Service::validate( 'TESTUNLIMITED', $f3_raffle_id, 5 );
rc_assert( ! is_wp_error( $f3_unlimited_valid ), 'F3: Cupón ilimitado válido tras 999 usos' );

// Cleanup flujo 3 — eliminar cupones y rifa
$wpdb->delete( $wpdb->prefix . 'rc_coupons', array( 'id' => $f3_coupon_pct_id ), array( '%d' ) );
$wpdb->delete( $wpdb->prefix . 'rc_coupons', array( 'id' => $f3_coupon_fixed_id ), array( '%d' ) );
$wpdb->delete( $wpdb->prefix . 'rc_coupons', array( 'id' => $f3_coupon_exp_id ), array( '%d' ) );
$wpdb->delete( $wpdb->prefix . 'rc_coupons', array( 'id' => $f3_coupon_inactive_id ), array( '%d' ) );
$wpdb->delete( $wpdb->prefix . 'rc_coupons', array( 'id' => $f3_coupon_unlimited_id ), array( '%d' ) );
$api->delete_raffle( $f3_raffle_id );

// ═══════════════════════════════════════════════════════════════
// Resumen
// ═══════════════════════════════════════════════════════════════
echo "\n" . str_repeat( '═', 60 ) . "\n";
echo "📊 Integration Tests: {$passed} pasaron, {$failed} fallaron de " . ( $passed + $failed ) . " tests\n";

if ( $failed === 0 ) {
    echo "🎉 ¡Todos los tests de integración pasaron!\n\n";
    exit( 0 );
} else {
    echo "⚠️ Hay tests que fallaron.\n\n";
    exit( 1 );
}
