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
                        <tr><td style=\"padding:6px 0;color:#666;\">Total:</td><td style=\"text-align:right;font-weight:600;\">$" . number_format( $purchase->total_amount, 2 ) . "</td></tr>
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
}
