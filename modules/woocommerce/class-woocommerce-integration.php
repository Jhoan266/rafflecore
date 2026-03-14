<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce Integration v2.0.0 — Flujo completo con productos virtuales y reservas pre-pago.
 *
 * Flujo:
 * 1. Usuario elige paquete → click "Proceder al Pago"
 * 2. AJAX rc_create_order:
 *    a. Valida disponibilidad
 *    b. Reserva boletos (sold_tickets += qty, status='reserved') — previene race conditions
 *    c. Asegura producto WC virtual+oculto via WC Product Manager
 *    d. Agrega al carrito con metadata del paquete → redirige a checkout
 * 3. WC Checkout → cliente paga
 * 4. Hook woocommerce_payment_complete → genera tickets reales + email
 * 5. Si el pago falla/cancela → libera la reserva (sold_tickets -= qty)
 */
class RaffleCore_WooCommerce {

    private $api;

    public function __construct( $api ) {
        $this->api = $api;
    }

    /**
     * ¿WooCommerce está instalado y activo?
     */
    public static function is_available() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * AJAX: Crear pedido WooCommerce desde el formulario de rifa.
     */
    public function ajax_create_order() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rc_public_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Error de seguridad.', 'rafflecore' ) ) );
        }

        // Honeypot check — campo oculto que bots llenan automáticamente
        $honeypot = RaffleCore_Rate_Limiter::check_honeypot();
        if ( is_wp_error( $honeypot ) ) {
            wp_send_json_error( array( 'message' => $honeypot->get_error_message() ) );
        }

        // Rate limiting: ventana deslizante + backoff progresivo
        $rate_check = RaffleCore_Rate_Limiter::check();
        if ( is_wp_error( $rate_check ) ) {
            wp_send_json_error( array( 'message' => $rate_check->get_error_message() ) );
        }

        if ( ! self::is_available() ) {
            wp_send_json_error( array( 'message' => __( 'WooCommerce no está disponible.', 'rafflecore' ) ) );
        }

        $raffle_id   = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $quantity    = isset( $_POST['ticket_qty'] ) ? absint( $_POST['ticket_qty'] ) : 0;
        $buyer_name  = isset( $_POST['buyer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['buyer_name'] ) ) : '';
        $buyer_email = isset( $_POST['buyer_email'] ) ? sanitize_email( wp_unslash( $_POST['buyer_email'] ) ) : '';
        $buyer_phone    = isset( $_POST['buyer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['buyer_phone'] ) ) : '';
        $chosen_numbers = isset( $_POST['chosen_numbers'] ) ? sanitize_text_field( wp_unslash( $_POST['chosen_numbers'] ) ) : '';

        $raffle = $this->api->get_raffle( $raffle_id );

        // Validar
        $validation = RaffleCore_Purchase_Service::validate( $raffle, $quantity, $buyer_name, $buyer_email );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
        }

        if ( ! $raffle || $raffle->status !== 'active' ) {
            wp_send_json_error( array( 'message' => __( 'Rifa no activa.', 'rafflecore' ) ) );
        }

        // Calcular precio
        $total_amount = $quantity * $raffle->ticket_price;
        $package_price = isset( $_POST['package_price'] ) ? absint( $_POST['package_price'] ) : 0;
        if ( $package_price > 0 ) {
            $total_amount = $package_price;
        }

        // Aplicar cupón si existe
        $coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
        if ( $coupon_code ) {
            $coupon_service = new RaffleCore_Coupon_Service();
            $coupon_result = $coupon_service->validate( $coupon_code, $raffle_id, $quantity );
            if ( ! is_wp_error( $coupon_result ) ) {
                $total_amount = $coupon_service->apply_discount( $total_amount, $coupon_result );
                RaffleCore_Coupon_Model::increment_usage( $coupon_result->id );
                RaffleCore_Logger::log( 'coupon_used', 'coupon', $coupon_result->id, $coupon_code );
                RaffleCore_Webhook_Service::fire( 'coupon.used', array(
                    'code'      => $coupon_code,
                    'raffle_id' => $raffle_id,
                    'discount'  => $coupon_result->discount_value,
                ) );
            }
        }

        // ── PASO 1: Reservar boletos (previene race conditions) ──
        $reservation = RaffleCore_Reservation_Service::reserve( $raffle_id, $quantity );
        if ( is_wp_error( $reservation ) ) {
            wp_send_json_error( array( 'message' => $reservation->get_error_message() ) );
        }

        // ── PASO 2: Asegurar producto WC vinculado a la rifa ──
        $product_id = RaffleCore_WC_Product_Manager::ensure_product( $raffle_id, $raffle->title, $raffle->ticket_price );
        if ( is_wp_error( $product_id ) ) {
            // Liberar reserva si falla
            RaffleCore_Reservation_Service::release( $raffle_id, $quantity );
            wp_send_json_error( array( 'message' => __( 'Error al preparar producto WC.', 'rafflecore' ) ) );
        }

        // ── PASO 3: Agregar al carrito ──
        $cart_key = RaffleCore_WC_Product_Manager::add_to_cart(
            $raffle_id,
            $quantity,
            $total_amount,
            array( 'name' => $buyer_name, 'email' => $buyer_email, 'phone' => $buyer_phone )
        );

        if ( is_wp_error( $cart_key ) ) {
            RaffleCore_Reservation_Service::release( $raffle_id, $quantity );
            wp_send_json_error( array( 'message' => __( 'Error al agregar al carrito.', 'rafflecore' ) ) );
        }

        // ── PASO 4: Crear registro de compra con status 'reserved' ──
        $purchase_id = $this->api->create_purchase( array(
            'raffle_id'   => $raffle_id,
            'buyer_name'  => $buyer_name,
            'buyer_email' => $buyer_email,
            'quantity'    => $quantity,
            'amount_paid' => $total_amount,
            'status'      => 'reserved',
            'order_id'    => null,
        ) );

        if ( is_wp_error( $purchase_id ) ) {
            RaffleCore_Reservation_Service::release( $raffle_id, $quantity );
            WC()->cart->empty_cart();
            wp_send_json_error( array( 'message' => __( 'Error al registrar la compra.', 'rafflecore' ) ) );
        }

        // Guardar purchase_id en sesión WC para vincularlo al pedido después del checkout
        WC()->session->set( 'rc_purchase_id', $purchase_id );
        WC()->session->set( 'rc_raffle_id', $raffle_id );
        WC()->session->set( 'rc_buyer_name', $buyer_name );
        WC()->session->set( 'rc_buyer_email', $buyer_email );
        WC()->session->set( 'rc_quantity', $quantity );
        WC()->session->set( 'rc_chosen_numbers', $chosen_numbers );

        wp_send_json_success( array(
            'checkout_url' => wc_get_checkout_url(),
            'cart_key'     => $cart_key,
        ) );
    }

    /**
     * Hook: Al completar el pago → generar boletos.
     *
     * En v2.0.0, los sold_tickets ya fueron reservados pre-pago.
     * Aquí solo se generan los tickets reales y se envía el email.
     * El Ticket Service NO incrementa sold_tickets de nuevo porque ya están reservados.
     */
    public function on_payment_complete( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Buscar metadata de rifa en line items o en order meta
        $raffle_id   = (int) $order->get_meta( '_rc_raffle_id' );
        $purchase_id = (int) $order->get_meta( '_rc_purchase_id' );
        $quantity       = (int) $order->get_meta( '_rc_quantity' );
        $buyer_email    = $order->get_meta( '_rc_buyer_email' );
        $chosen_numbers = $order->get_meta( '_rc_chosen_numbers' );

        $specific_numbers = array();
        if ( ! empty( $chosen_numbers ) ) {
            $specific_numbers = array_filter( array_map( 'absint', explode( ',', $chosen_numbers ) ) );
        }

        // Si no tiene meta de rifa, no es un pedido de rifa
        if ( ! $raffle_id || ! $quantity ) {
            return;
        }

        // Idempotencia
        if ( $order->get_meta( '_rc_tickets_generated' ) === 'yes' ) {
            return;
        }

        if ( ! $purchase_id ) {
            return;
        }

        // Verificar que no esté ya procesada
        $purchase = $this->api->get_purchase( $purchase_id );
        if ( ! $purchase || $purchase->status === 'completed' ) {
            $order->update_meta_data( '_rc_tickets_generated', 'yes' );
            $order->save();
            return;
        }

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        // generate() usa FOR UPDATE internamente.
        // Nota: sold_tickets ya fue incrementado en la reserva, así que el Ticket Service
        // solo inserta tickets y NO incrementa sold_tickets de nuevo.
        $tickets = $this->generate_tickets_without_increment( $raffle_id, $purchase_id, $quantity, $buyer_email, $specific_numbers );

        if ( is_wp_error( $tickets ) ) {
            $wpdb->query( 'ROLLBACK' );
            $order->add_order_note( sprintf( __( 'Error generando boletos: %s', 'rafflecore' ), $tickets->get_error_message() ) );
            return;
        }

        $this->api->update_purchase( $purchase_id, array(
            'status'   => 'completed',
            'order_id' => $order_id,
        ) );

        $wpdb->query( 'COMMIT' );

        // Marcar como procesado
        $raffle    = $this->api->get_raffle( $raffle_id );
        $formatted = RaffleCore_Ticket_Service::format_numbers( $tickets, $raffle->total_tickets );

        $order->update_meta_data( '_rc_tickets_generated', 'yes' );
        $order->update_meta_data( '_rc_ticket_numbers', $formatted );
        $order->save();
        $order->add_order_note( sprintf( __( 'Boletos generados: %s', 'rafflecore' ), implode( ', ', $formatted ) ) );

        // Email
        $this->api->send_purchase_email( $purchase_id, $raffle, $tickets );

        // Admin notification + webhook + log
        RaffleCore_Email_Service::notify_admin_purchase( $purchase_id, $raffle );
        RaffleCore_Logger::log( 'purchase_completed', 'purchase', $purchase_id, $buyer_email );
        RaffleCore_Webhook_Service::fire( 'purchase.completed', array(
            'purchase_id' => $purchase_id,
            'raffle_id'   => $raffle_id,
            'quantity'    => $quantity,
            'buyer_email' => $buyer_email,
            'tickets'     => $formatted,
        ) );

        // Check if sold out
        $fresh_raffle = $this->api->get_raffle( $raffle_id );
        if ( $fresh_raffle && (int) $fresh_raffle->sold_tickets >= (int) $fresh_raffle->total_tickets ) {
            RaffleCore_Email_Service::notify_admin_sold_out( $fresh_raffle );
            RaffleCore_Webhook_Service::fire( 'raffle.sold_out', array(
                'raffle_id' => $raffle_id,
                'title'     => $fresh_raffle->title,
            ) );
        }
    }

    /**
     * Hook: Al cancelar/fallar un pedido → liberar la reserva.
     */
    public function on_order_cancelled( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $raffle_id   = (int) $order->get_meta( '_rc_raffle_id' );
        $purchase_id = (int) $order->get_meta( '_rc_purchase_id' );
        $quantity    = (int) $order->get_meta( '_rc_quantity' );

        if ( ! $raffle_id || ! $quantity || ! $purchase_id ) {
            return;
        }

        // Solo liberar si estaba reservada (no si ya completed)
        $purchase = $this->api->get_purchase( $purchase_id );
        if ( ! $purchase || $purchase->status !== 'reserved' ) {
            return;
        }

        // Liberar reserva
        RaffleCore_Reservation_Service::release( $raffle_id, $quantity );

        // Marcar compra como cancelled
        $this->api->update_purchase( $purchase_id, array( 'status' => 'cancelled' ) );
    }

    /**
     * Genera tickets sin incrementar sold_tickets (ya reservados).
     *
     * Reutiliza la lógica de RaffleCore_Ticket_Service pero sin el UPDATE de sold_tickets.
     */
    private function generate_tickets_without_increment( $raffle_id, $purchase_id, $quantity, $buyer_email, $specific_numbers = array() ) {
        global $wpdb;

        $t_raffles = $wpdb->prefix . 'rc_raffles';
        $t_tickets = $wpdb->prefix . 'rc_tickets';

        // Lock
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return new WP_Error( 'not_found', __( 'Rifa no encontrada.', 'rafflecore' ) );
        }

        // Obtener números ya usados
        $used_numbers = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$t_tickets} WHERE raffle_id = %d",
            $raffle_id
        ) );

        $used_set = array_flip( $used_numbers );
        $tickets  = array();

        if ( ! empty( $specific_numbers ) ) {
            // Lógica similar a Ticket_Service::generate para números específicos
            foreach ( $specific_numbers as $num ) {
                $num = (int) $num;
                if ( $num < 1 || $num > (int) $raffle->total_tickets || isset( $used_set[ $num ] ) ) {
                    // Si un número elegido se vendió mientras el usuario pagaba, 
                    // tenemos un problema. En un sistema real deberíamos fallar o asignar uno aleatorio.
                    // Para este MVP, si falla, intentamos asignar aleatorios.
                    $specific_numbers = array(); 
                    break;
                }
                $tickets[] = $num;
            }
        }
        
        if ( empty( $tickets ) ) {
            $pool = array();
            $total = (int) $raffle->total_tickets;
            for ( $i = 1; $i <= $total; $i++ ) {
                if ( ! isset( $used_set[ $i ] ) ) {
                    $pool[] = $i;
                }
            }

            if ( count( $pool ) < $quantity ) {
                return new WP_Error( 'insufficient', __( 'No hay suficientes números disponibles.', 'rafflecore' ) );
            }

            for ( $i = count( $pool ) - 1; $i > 0; $i-- ) {
                $j = random_int( 0, $i );
                $tmp = $pool[ $i ];
                $pool[ $i ] = $pool[ $j ];
                $pool[ $j ] = $tmp;
            }

            $tickets = array_slice( $pool, 0, $quantity );
        }

        sort( $tickets );

        foreach ( $tickets as $number ) {
            $wpdb->insert( $t_tickets, array(
                'raffle_id'     => $raffle_id,
                'purchase_id'   => $purchase_id,
                'ticket_number' => $number,
                'buyer_email'   => $buyer_email,
                'created_at'    => current_time( 'mysql' ),
            ), array( '%d', '%d', '%d', '%s', '%s' ) );
        }

        // NO incrementamos sold_tickets aquí — ya se hizo en la reserva.

        return $tickets;
    }

    /**
     * Hook: Guardar metadata de rifa en la orden al crear (checkout).
     * Se registra en woocommerce_checkout_order_created.
     */
    public function on_order_created( $order ) {
        $session = WC()->session;
        if ( ! $session ) {
            return;
        }

        $purchase_id = $session->get( 'rc_purchase_id' );
        $raffle_id   = $session->get( 'rc_raffle_id' );

        if ( ! $purchase_id || ! $raffle_id ) {
            return;
        }

        $order->update_meta_data( '_rc_raffle_id', $raffle_id );
        $order->update_meta_data( '_rc_purchase_id', $purchase_id );
        $order->update_meta_data( '_rc_quantity', $session->get( 'rc_quantity' ) );
        $order->update_meta_data( '_rc_buyer_name', $session->get( 'rc_buyer_name' ) );
        $order->update_meta_data( '_rc_buyer_email', $session->get( 'rc_buyer_email' ) );
        $order->update_meta_data( '_rc_chosen_numbers', $session->get( 'rc_chosen_numbers' ) );
        $order->update_meta_data( '_rc_is_raffle', 'yes' );
        $order->save();

        // Vincular order_id a la compra
        $this->api->update_purchase( $purchase_id, array( 'order_id' => $order->get_id() ) );

        // Limpiar sesión
        $session->set( 'rc_purchase_id', null );
        $session->set( 'rc_raffle_id', null );
        $session->set( 'rc_buyer_name', null );
        $session->set( 'rc_buyer_email', null );
        $session->set( 'rc_quantity', null );
    }

    /**
     * Mostrar boletos en la página de agradecimiento.
     */
    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_meta( '_rc_is_raffle' ) !== 'yes' ) {
            return;
        }

        $tickets   = $order->get_meta( '_rc_ticket_numbers' );
        $raffle_id = (int) $order->get_meta( '_rc_raffle_id' );
        $raffle    = $this->api ? $this->api->get_raffle( $raffle_id ) : null;

        if ( ! empty( $tickets ) && is_array( $tickets ) ) {
            echo '<div class="rc-thankyou" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:30px;border-radius:16px;margin:20px 0;text-align:center;">';
            echo '<h2 style="color:#fff;margin:0 0 8px;">&#127881; ' . esc_html__( '¡Tus Boletos de Rifa!', 'rafflecore' ) . '</h2>';
            if ( $raffle ) {
                echo '<p style="opacity:0.9;margin:0 0 16px;">' . esc_html( $raffle->title ) . '</p>';
            }
            echo '<div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">';
            foreach ( $tickets as $ticket ) {
                echo '<span style="background:rgba(255,255,255,0.2);padding:8px 16px;border-radius:8px;font-weight:700;font-size:18px;">' . esc_html( $ticket ) . '</span>';
            }
            echo '</div>';
            echo '<p style="opacity:0.8;margin:16px 0 0;font-size:14px;">&#128231; ' . esc_html__( 'También enviamos un correo con tus números.', 'rafflecore' ) . '</p>';
            echo '</div>';
        } else {
            $status = $order->get_status();
            if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
                echo '<div style="background:#fff3cd;color:#856404;padding:16px;border-radius:8px;margin:20px 0;">';
                echo '<p><strong>&#9203; ' . esc_html__( 'Tu pago está siendo procesado.', 'rafflecore' ) . '</strong></p>';
                echo '<p>' . esc_html__( 'Recibirás tus boletos por correo una vez se confirme.', 'rafflecore' ) . '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Mostrar meta de rifa en la página de orden del admin WC.
     */
    public function admin_order_meta( $order ) {
        if ( $order->get_meta( '_rc_is_raffle' ) !== 'yes' ) {
            return;
        }

        $tickets = $order->get_meta( '_rc_ticket_numbers' );

        echo '<div style="border-left:3px solid #667eea;padding-left:12px;margin-top:12px;">';
        echo '<h3 style="color:#667eea;">&#127903; ' . esc_html__( 'Datos de Rifa', 'rafflecore' ) . '</h3>';
        echo '<p><strong>' . esc_html__( 'Rifa ID:', 'rafflecore' ) . '</strong> ' . esc_html( $order->get_meta( '_rc_raffle_id' ) ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Cantidad:', 'rafflecore' ) . '</strong> ' . esc_html( $order->get_meta( '_rc_quantity' ) ) . ' ' . esc_html__( 'boletos', 'rafflecore' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Compra ID:', 'rafflecore' ) . '</strong> ' . esc_html( $order->get_meta( '_rc_purchase_id' ) ) . '</p>';

        if ( ! empty( $tickets ) && is_array( $tickets ) ) {
            echo '<p><strong>' . esc_html__( 'Boletos:', 'rafflecore' ) . '</strong> ' . esc_html( implode( ', ', $tickets ) ) . '</p>';
        }
        echo '</div>';
    }
}
