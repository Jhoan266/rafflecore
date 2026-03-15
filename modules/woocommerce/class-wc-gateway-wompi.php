<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce Payment Gateway — Wompi (Colombia).
 *
 * Flujo redirect-based:
 *   1. process_payment() → redirige a checkout.wompi.co con firma de integridad.
 *   2. Wompi redirige de vuelta → handle_redirect_return() verifica transacción vía API.
 *   3. Webhook async → handle_webhook() confirma pagos pendientes/tardíos.
 *   4. $order->payment_complete() dispara los hooks existentes de RaffleCore (tickets, email, etc).
 */
class RaffleCore_WC_Gateway_Wompi extends WC_Payment_Gateway {

    const SANDBOX_API    = 'https://sandbox.wompi.co/v1';
    const PRODUCTION_API = 'https://production.wompi.co/v1';
    const CHECKOUT_URL   = 'https://checkout.wompi.co/p/';

    private $sandbox;
    private $public_key;
    private $private_key;
    private $integrity_secret;
    private $events_secret;

    public function __construct() {
        $this->id                 = 'rafflecore_wompi';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = 'Wompi';
        $this->method_description = __( 'Pasarela de pago Wompi para Colombia. Los clientes son redirigidos a Wompi para completar el pago.', 'rafflecore' );
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title            = $this->get_option( 'title', 'Wompi' );
        $this->description      = $this->get_option( 'description', __( 'Paga de forma segura con Wompi.', 'rafflecore' ) );
        $this->sandbox          = $this->get_option( 'sandbox', 'yes' ) === 'yes';
        $this->public_key       = $this->get_option( 'public_key', '' );
        $this->private_key      = $this->get_option( 'private_key', '' );
        $this->integrity_secret = $this->get_option( 'integrity_secret', '' );
        $this->events_secret    = $this->get_option( 'events_secret', '' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_wompi_return', array( $this, 'handle_redirect_return' ) );
        add_action( 'woocommerce_api_wompi_gateway', array( $this, 'handle_webhook' ) );
    }

    /**
     * Register this gateway with WooCommerce.
     */
    public static function register( $gateways ) {
        $gateways[] = 'RaffleCore_WC_Gateway_Wompi';
        return $gateways;
    }

    /**
     * Settings fields for WooCommerce > Payments > Wompi.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Activar/Desactivar', 'rafflecore' ),
                'type'    => 'checkbox',
                'label'   => __( 'Activar pasarela Wompi', 'rafflecore' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Título', 'rafflecore' ),
                'type'        => 'text',
                'description' => __( 'Nombre que ve el cliente en el checkout.', 'rafflecore' ),
                'default'     => 'Wompi',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Descripción', 'rafflecore' ),
                'type'        => 'textarea',
                'description' => __( 'Descripción mostrada durante el checkout.', 'rafflecore' ),
                'default'     => __( 'Paga de forma segura con Wompi (tarjeta, PSE, Nequi, Bancolombia).', 'rafflecore' ),
            ),
            'sandbox' => array(
                'title'       => __( 'Modo Sandbox', 'rafflecore' ),
                'type'        => 'checkbox',
                'label'       => __( 'Activar modo de pruebas', 'rafflecore' ),
                'default'     => 'yes',
                'description' => __( 'Desactiva esto cuando vayas a producción.', 'rafflecore' ),
            ),
            'public_key' => array(
                'title'       => __( 'Llave Pública', 'rafflecore' ),
                'type'        => 'text',
                'description' => __( 'pub_test_... o pub_prod_... — Encuéntrala en Wompi → Desarrolladores.', 'rafflecore' ),
                'default'     => '',
            ),
            'private_key' => array(
                'title'       => __( 'Llave Privada', 'rafflecore' ),
                'type'        => 'password',
                'description' => __( 'prv_test_... o prv_prod_... — Para consultas servidor a servidor.', 'rafflecore' ),
                'default'     => '',
            ),
            'integrity_secret' => array(
                'title'       => __( 'Secreto de Integridad', 'rafflecore' ),
                'type'        => 'password',
                'description' => __( 'Para firmar los pagos y evitar manipulación de montos.', 'rafflecore' ),
                'default'     => '',
            ),
            'events_secret' => array(
                'title'       => __( 'Secreto de Eventos', 'rafflecore' ),
                'type'        => 'password',
                'description' => __( 'Para verificar la firma de los webhooks de Wompi.', 'rafflecore' ),
                'default'     => '',
            ),
        );
    }

    /**
     * Show webhook URL in admin settings.
     */
    public function admin_options() {
        echo '<h2>' . esc_html( $this->method_title ) . '</h2>';
        echo '<p>' . esc_html( $this->method_description ) . '</p>';

        echo '<div style="background:#f0f6fc;border:1px solid #c8d8e4;border-radius:8px;padding:16px;margin-bottom:16px;">';
        echo '<strong>' . esc_html__( 'URL del Webhook:', 'rafflecore' ) . '</strong> ';
        echo '<code>' . esc_html( home_url( '/wc-api/wompi_gateway/' ) ) . '</code>';
        echo '<p style="margin:8px 0 0;color:#666;font-size:13px;">';
        echo esc_html__( 'Configura esta URL en tu panel de Wompi → Desarrolladores → Eventos.', 'rafflecore' );
        echo '</p></div>';

        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * Only available if currency is COP and keys are configured.
     */
    public function is_available() {
        if ( ! parent::is_available() ) {
            return false;
        }

        if ( get_woocommerce_currency() !== 'COP' ) {
            return false;
        }

        if ( empty( $this->public_key ) || empty( $this->integrity_secret ) ) {
            return false;
        }

        return true;
    }

    /**
     * Process payment: redirect to Wompi hosted checkout.
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Error al procesar el pedido.', 'rafflecore' ), 'error' );
            return array( 'result' => 'fail' );
        }

        $reference    = 'RC-' . $order_id . '-' . bin2hex( random_bytes( 4 ) );
        $amount_cents = (int) round( $order->get_total() * 100 );
        $currency     = 'COP';
        $signature    = $this->generate_signature( $reference, $amount_cents, $currency );

        $order->update_meta_data( '_wompi_reference', $reference );
        $order->update_status( 'pending', __( 'Aguardando pago en Wompi.', 'rafflecore' ) );
        $order->save();

        WC()->cart->empty_cart();

        $return_url = add_query_arg( array(
            'order_id'  => $order_id,
            'order_key' => $order->get_order_key(),
        ), home_url( '/wc-api/wompi_return/' ) );

        $wompi_url = add_query_arg( array(
            'public-key'          => $this->public_key,
            'currency'            => $currency,
            'amount-in-cents'     => $amount_cents,
            'reference'           => $reference,
            'redirect-url'        => $return_url,
            'signature:integrity' => $signature,
        ), self::CHECKOUT_URL );

        return array(
            'result'   => 'success',
            'redirect' => $wompi_url,
        );
    }

    /**
     * Handle redirect return from Wompi.
     * Verifies transaction via API and redirects to thank-you page.
     */
    public function handle_redirect_return() {
        $order_id  = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order_key = isset( $_GET['order_key'] ) ? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : '';
        $tx_id     = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';

        $order = wc_get_order( $order_id );

        if ( ! $order || ! $order->key_is_valid( $order_key ) ) {
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        // Already completed — just redirect to thank-you
        if ( $order->is_paid() ) {
            wp_safe_redirect( $this->get_return_url( $order ) );
            exit;
        }

        if ( empty( $tx_id ) ) {
            $order->update_status( 'failed', __( 'Pago cancelado o sin ID de transacción.', 'rafflecore' ) );
            wp_safe_redirect( $this->get_return_url( $order ) );
            exit;
        }

        // Verify transaction with Wompi API
        $result = $this->verify_transaction( $tx_id, $order );

        if ( $result === 'APPROVED' ) {
            $order->payment_complete( $tx_id );
            $order->update_meta_data( '_wompi_transaction_id', $tx_id );
            $order->add_order_note( sprintf( __( 'Pago aprobado en Wompi. Transacción: %s', 'rafflecore' ), $tx_id ) );
            $order->save();
        } elseif ( $result === 'PENDING' ) {
            $order->update_meta_data( '_wompi_transaction_id', $tx_id );
            $order->add_order_note( __( 'Pago pendiente en Wompi. Se confirmará vía webhook.', 'rafflecore' ) );
            $order->save();
        } else {
            $order->update_status( 'failed', sprintf( __( 'Pago no aprobado en Wompi. Estado: %s', 'rafflecore' ), $result ) );
        }

        wp_safe_redirect( $this->get_return_url( $order ) );
        exit;
    }

    /**
     * Handle async webhook from Wompi.
     */
    public function handle_webhook() {
        $raw_body = file_get_contents( 'php://input' );
        $body     = json_decode( $raw_body, true );

        if ( empty( $body['event'] ) || $body['event'] !== 'transaction.updated' ) {
            wp_send_json( array( 'status' => 'ignored' ), 200 );
        }

        if ( empty( $body['data']['transaction'] ) ) {
            wp_send_json( array( 'status' => 'no_data' ), 200 );
        }

        // Verify webhook signature
        if ( ! empty( $this->events_secret ) && ! empty( $body['signature']['checksum'] ) ) {
            $properties = isset( $body['signature']['properties'] ) ? $body['signature']['properties'] : array();
            $values     = array();
            foreach ( $properties as $prop ) {
                $keys = explode( '.', $prop );
                $val  = $body;
                foreach ( $keys as $k ) {
                    $val = isset( $val[ $k ] ) ? $val[ $k ] : '';
                }
                $values[] = $val;
            }
            $values[]      = $body['timestamp'];
            $values[]      = $this->events_secret;
            $expected_hash = hash( 'sha256', implode( '', $values ) );

            if ( ! hash_equals( $expected_hash, $body['signature']['checksum'] ) ) {
                status_header( 401 );
                wp_send_json( array( 'status' => 'invalid_signature' ), 401 );
            }
        }

        $tx        = $body['data']['transaction'];
        $reference = isset( $tx['reference'] ) ? sanitize_text_field( $tx['reference'] ) : '';

        if ( empty( $reference ) ) {
            wp_send_json( array( 'status' => 'no_reference' ), 200 );
        }

        // Find order by Wompi reference (HPOS-compatible)
        $orders = wc_get_orders( array(
            'meta_key'   => '_wompi_reference',
            'meta_value' => $reference,
            'limit'      => 1,
        ) );

        if ( empty( $orders ) ) {
            wp_send_json( array( 'status' => 'order_not_found' ), 200 );
        }

        $order = $orders[0];

        // Verify amount
        $expected_cents = (int) round( $order->get_total() * 100 );
        if ( (int) $tx['amount_in_cents'] !== $expected_cents ) {
            $order->add_order_note( __( 'Webhook Wompi: monto no coincide. Posible manipulación.', 'rafflecore' ) );
            wp_send_json( array( 'status' => 'amount_mismatch' ), 200 );
        }

        $tx_id = isset( $tx['id'] ) ? sanitize_text_field( $tx['id'] ) : '';

        if ( $tx['status'] === 'APPROVED' && ! $order->is_paid() ) {
            $order->payment_complete( $tx_id );
            $order->update_meta_data( '_wompi_transaction_id', $tx_id );
            $order->add_order_note( sprintf( __( 'Pago confirmado vía webhook Wompi. Transacción: %s', 'rafflecore' ), $tx_id ) );
            $order->save();
        } elseif ( in_array( $tx['status'], array( 'DECLINED', 'VOIDED', 'ERROR' ), true ) ) {
            if ( ! $order->is_paid() ) {
                $order->update_status( 'failed', sprintf( __( 'Webhook Wompi: pago %s. Transacción: %s', 'rafflecore' ), strtolower( $tx['status'] ), $tx_id ) );
            }
        }

        wp_send_json( array( 'status' => 'ok' ), 200 );
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Verify a transaction with Wompi API.
     *
     * @return string Transaction status (APPROVED, DECLINED, PENDING, ERROR, etc.) or 'ERROR' on failure.
     */
    private function verify_transaction( $transaction_id, $order ) {
        $api_url  = $this->api_url() . '/transactions/' . $transaction_id;
        $response = wp_remote_get( $api_url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) {
            $order->add_order_note( sprintf( __( 'Error al verificar transacción Wompi: %s', 'rafflecore' ), $response->get_error_message() ) );
            return 'ERROR';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['data'] ) ) {
            $order->add_order_note( __( 'Transacción no encontrada en Wompi.', 'rafflecore' ) );
            return 'ERROR';
        }

        $tx = $data['data'];

        // Verify reference matches
        $stored_ref = $order->get_meta( '_wompi_reference' );
        if ( $tx['reference'] !== $stored_ref ) {
            $order->add_order_note( __( 'Referencia de Wompi no coincide.', 'rafflecore' ) );
            return 'ERROR';
        }

        // Verify amount
        $expected_cents = (int) round( $order->get_total() * 100 );
        if ( (int) $tx['amount_in_cents'] !== $expected_cents ) {
            $order->add_order_note( __( 'Monto de Wompi no coincide con la orden.', 'rafflecore' ) );
            return 'ERROR';
        }

        return $tx['status'];
    }

    private function api_url() {
        return $this->sandbox ? self::SANDBOX_API : self::PRODUCTION_API;
    }

    private function generate_signature( $reference, $amount_cents, $currency ) {
        return hash( 'sha256', $reference . $amount_cents . $currency . $this->integrity_secret );
    }
}
