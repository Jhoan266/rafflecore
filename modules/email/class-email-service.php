<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Email Service — Correos de confirmación de compra.
 */
class RaffleCore_Email_Service {

    /**
     * Envía email de confirmación con los números de boleto.
     */
    public static function send_purchase_confirmation( $purchase_id, $raffle, $tickets ) {
        $purchase = RaffleCore_Purchase_Model::find( $purchase_id );
        if ( ! $purchase || ! $raffle ) {
            return false;
        }

        $total_digits = strlen( (string) $raffle->total_tickets );
        $formatted    = RaffleCore_Ticket_Service::format_numbers( $tickets, $raffle->total_tickets );
        $draw_date    = $raffle->draw_date ? date_i18n( 'd \d\e F, Y — H:i', strtotime( $raffle->draw_date ) ) : 'Por anunciar';
        $site_name    = get_bloginfo( 'name' );

        $subject = "🎟️ ¡Tus boletos para {$raffle->title}! — {$site_name}";

        $tickets_html = '';
        foreach ( $formatted as $num ) {
            $tickets_html .= "<span style=\"display:inline-block;background:#667eea;color:#fff;padding:8px 16px;margin:4px;border-radius:8px;font-weight:700;font-size:18px;\">{$num}</span>";
        }

        $body = "
        <div style=\"max-width:600px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;\">
            <div style=\"background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:40px 30px;text-align:center;border-radius:16px 16px 0 0;\">
                <h1 style=\"color:#fff;margin:0;font-size:28px;\">🎉 ¡Compra Confirmada!</h1>
            </div>

            <div style=\"background:#fff;padding:30px;border:1px solid #eee;\">
                <p style=\"font-size:16px;\">Hola <strong>" . esc_html( $purchase->buyer_name ) . "</strong>,</p>
                <p>Tu compra de boletos para <strong>" . esc_html( $raffle->title ) . "</strong> ha sido registrada exitosamente.</p>

                <div style=\"background:#f8f9fa;padding:20px;border-radius:12px;margin:20px 0;\">
                    <table style=\"width:100%;border-collapse:collapse;\">
                        <tr><td style=\"padding:6px 0;color:#666;\">Rifa:</td><td style=\"text-align:right;font-weight:600;\">" . esc_html( $raffle->title ) . "</td></tr>
                        <tr><td style=\"padding:6px 0;color:#666;\">Cantidad:</td><td style=\"text-align:right;font-weight:600;\">" . esc_html( $purchase->quantity ) . " boletos</td></tr>
                        <tr><td style=\"padding:6px 0;color:#666;\">Total:</td><td style=\"text-align:right;font-weight:600;\">$" . number_format( $purchase->amount_paid, 2 ) . "</td></tr>
                        <tr><td style=\"padding:6px 0;color:#666;\">Sorteo:</td><td style=\"text-align:right;font-weight:600;\">" . esc_html( $draw_date ) . "</td></tr>
                    </table>
                </div>

                <h2 style=\"text-align:center;color:#333;margin:24px 0 12px;\">Tus números de boleto</h2>
                <div style=\"text-align:center;padding:16px 0;\">{$tickets_html}</div>
                <p style=\"text-align:center;color:#999;font-size:13px;\">Guarda este correo como comprobante.</p>
            </div>

            <div style=\"background:#f8f9fa;padding:20px;text-align:center;border-radius:0 0 16px 16px;border:1px solid #eee;border-top:0;\">
                <p style=\"margin:0;color:#999;font-size:12px;\">Este correo fue enviado por " . esc_html( $site_name ) . "</p>
            </div>
        </div>";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        return wp_mail( $purchase->buyer_email, $subject, $body, $headers );
    }

    /**
     * Notifica al admin cuando se completa una venta.
     */
    public static function notify_admin_purchase( $purchase_id, $raffle ) {
        $purchase  = RaffleCore_Purchase_Model::find( $purchase_id );
        if ( ! $purchase || ! $raffle ) return;

        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );
        $subject     = sprintf( '🎟️ %s — %s', __( 'Nueva venta de boletos', 'rafflecore' ), $raffle->title );

        $body = "<div style=\"max-width:500px;margin:0 auto;font-family:sans-serif;padding:20px;\">
            <h2>" . esc_html__( 'Nueva venta de boletos', 'rafflecore' ) . "</h2>
            <p><strong>" . esc_html__( 'Rifa', 'rafflecore' ) . ":</strong> " . esc_html( $raffle->title ) . "</p>
            <p><strong>" . esc_html__( 'Comprador', 'rafflecore' ) . ":</strong> " . esc_html( $purchase->buyer_name ) . " (" . esc_html( $purchase->buyer_email ) . ")</p>
            <p><strong>" . esc_html__( 'Cantidad', 'rafflecore' ) . ":</strong> " . esc_html( $purchase->quantity ) . " boletos</p>
            <p><strong>" . esc_html__( 'Total', 'rafflecore' ) . ":</strong> $" . number_format( $purchase->amount_paid, 2 ) . "</p>
            <p><strong>" . esc_html__( 'Progreso', 'rafflecore' ) . ":</strong> " . esc_html( $raffle->sold_tickets ) . "/" . esc_html( $raffle->total_tickets ) . "</p>
        </div>";

        $headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $site_name . ' <' . $admin_email . '>' );
        wp_mail( $admin_email, $subject, $body, $headers );
    }

    /**
     * Notifica al admin cuando una rifa se agotó.
     */
    public static function notify_admin_sold_out( $raffle ) {
        if ( ! $raffle ) return;

        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );
        $subject     = sprintf( '🎉 %s — %s', __( 'Rifa agotada', 'rafflecore' ), $raffle->title );

        $body = "<div style=\"max-width:500px;margin:0 auto;font-family:sans-serif;padding:20px;\">
            <h2>🎉 " . esc_html__( 'Rifa agotada', 'rafflecore' ) . "</h2>
            <p>" . esc_html( $raffle->title ) . " — " . esc_html__( 'Todos los boletos han sido vendidos.', 'rafflecore' ) . "</p>
            <p><strong>" . esc_html( $raffle->total_tickets ) . "</strong> " . esc_html__( 'boletos vendidos en total.', 'rafflecore' ) . "</p>
        </div>";

        $headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $site_name . ' <' . $admin_email . '>' );
        wp_mail( $admin_email, $subject, $body, $headers );
    }

    /**
     * Notifica al admin cuando se ejecuta un sorteo.
     */
    public static function notify_admin_draw( $raffle, $winner_ticket ) {
        if ( ! $raffle || ! $winner_ticket ) return;

        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );
        $subject     = sprintf( '🏆 %s — %s', __( 'Sorteo realizado', 'rafflecore' ), $raffle->title );

        $body = "<div style=\"max-width:500px;margin:0 auto;font-family:sans-serif;padding:20px;\">
            <h2>🏆 " . esc_html__( 'Sorteo realizado', 'rafflecore' ) . "</h2>
            <p><strong>" . esc_html__( 'Rifa', 'rafflecore' ) . ":</strong> " . esc_html( $raffle->title ) . "</p>
            <p><strong>" . esc_html__( 'Boleto ganador', 'rafflecore' ) . ":</strong> #" . esc_html( $winner_ticket->ticket_number ) . "</p>
            <p><strong>" . esc_html__( 'Email ganador', 'rafflecore' ) . ":</strong> " . esc_html( $winner_ticket->buyer_email ) . "</p>
        </div>";

        $headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $site_name . ' <' . $admin_email . '>' );
        wp_mail( $admin_email, $subject, $body, $headers );
    }

    /**
     * Envía email de notificación al ganador con mensaje personalizado.
     */
    public static function send_winner_notification( $raffle, $winner_ticket, $custom_message ) {
        if ( ! $raffle || ! $winner_ticket ) return false;

        $email     = isset( $winner_ticket->purchase_email ) ? $winner_ticket->purchase_email : $winner_ticket->buyer_email;
        $name      = $winner_ticket->buyer_name;
        $site_name = get_bloginfo( 'name' );
        $total_digits = strlen( (string) $raffle->total_tickets );
        $formatted_number = str_pad( $winner_ticket->ticket_number, $total_digits, '0', STR_PAD_LEFT );

        $subject = sprintf( '🏆 %s — %s', __( '¡Felicidades, eres el ganador!', 'rafflecore' ), $raffle->title );

        // Replace placeholders in custom message
        $message_html = nl2br( esc_html( $custom_message ) );
        $message_html = str_replace(
            array( '{nombre}', '{rifa}', '{boleto}', '{premio}', '{sitio}' ),
            array(
                '<strong>' . esc_html( $name ) . '</strong>',
                '<strong>' . esc_html( $raffle->title ) . '</strong>',
                '<strong>#' . esc_html( $formatted_number ) . '</strong>',
                '<strong>$' . number_format( $raffle->prize_value, 2 ) . '</strong>',
                '<strong>' . esc_html( $site_name ) . '</strong>',
            ),
            $message_html
        );

        $body = "
        <div style=\"max-width:600px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;\">
            <div style=\"background:linear-gradient(135deg,#f093fb 0%,#f5576c 50%,#fda085 100%);padding:40px 30px;text-align:center;border-radius:16px 16px 0 0;\">
                <h1 style=\"color:#fff;margin:0;font-size:32px;\">🏆 ¡Felicidades!</h1>
                <p style=\"color:rgba(255,255,255,0.9);font-size:16px;margin:8px 0 0;\">" . esc_html__( 'Has ganado en', 'rafflecore' ) . " " . esc_html( $raffle->title ) . "</p>
            </div>

            <div style=\"background:#fff;padding:30px;border:1px solid #eee;\">
                <div style=\"text-align:center;margin-bottom:24px;\">
                    <span style=\"display:inline-block;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:12px 28px;border-radius:12px;font-weight:700;font-size:24px;\">
                        " . esc_html__( 'Boleto', 'rafflecore' ) . " #" . esc_html( $formatted_number ) . "
                    </span>
                </div>

                <div style=\"background:#f8f9fa;padding:20px;border-radius:12px;margin:20px 0;line-height:1.7;font-size:15px;\">
                    {$message_html}
                </div>
            </div>

            <div style=\"background:#f8f9fa;padding:20px;text-align:center;border-radius:0 0 16px 16px;border:1px solid #eee;border-top:0;\">
                <p style=\"margin:0;color:#999;font-size:12px;\">" . esc_html__( 'Este correo fue enviado por', 'rafflecore' ) . " " . esc_html( $site_name ) . "</p>
            </div>
        </div>";

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        return wp_mail( $email, $subject, $body, $headers );
    }
}
