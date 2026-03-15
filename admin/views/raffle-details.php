<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$progress = $raffle->total_tickets > 0 ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100, 1 ) : 0;
$pkgs     = json_decode( $raffle->packages, true );
?>
<div class="wrap rc-wrap">
    <h1 class="rc-title">
        🏟️ <?php echo esc_html( $raffle->title ); ?>
        <span class="rc-badge rc-badge-<?php echo esc_attr( $raffle->status ); ?>"><?php echo esc_html( ucfirst( $raffle->status ) ); ?></span>
    </h1>

    <div class="rc-detail-header">
        <?php if ( $raffle->prize_image ) : ?>
            <img src="<?php echo esc_url( $raffle->prize_image ); ?>" class="rc-detail-image" alt="">
        <?php endif; ?>
        <div class="rc-detail-info">
            <?php if ( $raffle->description ) : ?>
                <p class="rc-detail-desc"><?php echo esc_html( $raffle->description ); ?></p>
            <?php endif; ?>

            <div class="rc-detail-meta">
                <div><strong><?php esc_html_e( 'Precio:', 'rafflecore' ); ?></strong> $<?php echo number_format_i18n( $raffle->ticket_price ); ?></div>
                <div><strong><?php esc_html_e( 'Boletos:', 'rafflecore' ); ?></strong> <?php echo number_format_i18n( $raffle->sold_tickets ); ?> / <?php echo number_format_i18n( $raffle->total_tickets ); ?></div>
                <div><strong><?php esc_html_e( 'Sorteo:', 'rafflecore' ); ?></strong> <?php echo $raffle->draw_date ? esc_html( date_i18n( 'd/m/Y', strtotime( $raffle->draw_date ) ) ) : esc_html__( 'No definido', 'rafflecore' ); ?></div>
                <div><strong>Shortcode:</strong> <code>[rafflecore id="<?php echo intval( $raffle->id ); ?>"]</code></div>
            </div>

            <div class="rc-progress-bar rc-progress-lg">
                <div class="rc-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%">
                    <?php echo esc_html( $progress ); ?>%
                </div>
            </div>

            <?php if ( is_array( $pkgs ) && count( $pkgs ) > 0 ) : ?>
            <div class="rc-packages-row">
                <strong><?php esc_html_e( 'Paquetes:', 'rafflecore' ); ?></strong>
                <?php foreach ( $pkgs as $p ) : ?>
                    <span class="rc-package-badge"><?php echo intval( $p['qty'] ); ?> boletos — $<?php echo number_format_i18n( $p['price'] ); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="rc-detail-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles&action=edit&id=' . $raffle->id ) ); ?>" class="rc-btn rc-btn-warning">✏️ <?php esc_html_e( 'Editar', 'rafflecore' ); ?></a>
                <?php if ( $raffle->status === 'active' && $raffle->sold_tickets > 0 ) : ?>
                    <button type="button" id="rc-draw-btn"
                            data-raffle-id="<?php echo intval( $raffle->id ); ?>"
                            class="rc-btn rc-btn-primary rc-btn-lg">
                        🎰 <?php esc_html_e( 'Realizar Sorteo', 'rafflecore' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ( $winner ) : ?>
    <div class="rc-winner-banner">
        <h2>🏆 <?php esc_html_e( '¡Ganador!', 'rafflecore' ); ?></h2>
        <div class="rc-winner-info">
            <span class="rc-winner-ticket">Boleto #<?php echo esc_html( str_pad( $winner->ticket_number, strlen( (string) $raffle->total_tickets ), '0', STR_PAD_LEFT ) ); ?></span>
            <span class="rc-winner-name"><?php echo esc_html( $winner->buyer_name ); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div id="rc-draw-result" style="display:none" class="rc-winner-banner">
        <h2>🏆 <?php esc_html_e( '¡Tenemos Ganador!', 'rafflecore' ); ?></h2>
        <div class="rc-winner-info">
            <span id="rc-draw-ticket" class="rc-winner-ticket"></span>
            <span id="rc-draw-buyer" class="rc-winner-name"></span>
            <span id="rc-draw-email" class="rc-winner-email"></span>
            <span id="rc-draw-notified" style="display:none" class="rc-badge rc-badge-active">✉️ <?php esc_html_e( 'Ganador notificado por email', 'rafflecore' ); ?></span>
        </div>
    </div>

    <?php if ( $raffle->status === 'active' && $raffle->sold_tickets > 0 && ! $winner ) : ?>
    <div class="rc-panel rc-external-draw-panel" style="margin-top: 30px;">
        <h2>🎱 <?php esc_html_e( 'Número Ganador Externo (Baloto / Lotería)', 'rafflecore' ); ?></h2>
        <p class="rc-panel-desc"><?php esc_html_e( 'Ingresa el número ganador de la lotería nacional (ej: últimos 4 dígitos del Baloto). Si coincide con un boleto vendido, se marcará como ganador y se le notificará.', 'rafflecore' ); ?></p>

        <div class="rc-external-draw-form">
            <div class="rc-form-row">
                <label for="rc-external-number"><?php esc_html_e( 'Número ganador:', 'rafflecore' ); ?></label>
                <input type="number" id="rc-external-number" min="1" max="<?php echo intval( $raffle->total_tickets ); ?>"
                       placeholder="<?php echo esc_attr( sprintf( __( 'Ej: %d', 'rafflecore' ), min( 1234, $raffle->total_tickets ) ) ); ?>"
                       class="rc-input rc-input-lg">
            </div>

            <div class="rc-form-row" style="margin-top: 20px;">
                <label><?php esc_html_e( 'Mensaje para el ganador:', 'rafflecore' ); ?></label>
                <div class="rc-templates-bar">
                    <button type="button" class="rc-btn rc-btn-sm rc-template-btn" data-template="congratulations"><?php esc_html_e( '🎉 Felicitaciones', 'rafflecore' ); ?></button>
                    <button type="button" class="rc-btn rc-btn-sm rc-template-btn" data-template="formal"><?php esc_html_e( '📋 Formal', 'rafflecore' ); ?></button>
                    <button type="button" class="rc-btn rc-btn-sm rc-template-btn" data-template="claim"><?php esc_html_e( '📦 Reclamar premio', 'rafflecore' ); ?></button>
                    <button type="button" class="rc-btn rc-btn-sm rc-template-btn" data-template="short"><?php esc_html_e( '⚡ Breve', 'rafflecore' ); ?></button>
                </div>
                <textarea id="rc-winner-message" class="rc-textarea" rows="6"
                          placeholder="<?php esc_attr_e( 'Escribe el mensaje que recibirá el ganador por email. Puedes usar: {nombre}, {rifa}, {boleto}, {premio}, {sitio}', 'rafflecore' ); ?>"></textarea>
                <p class="rc-help-text">
                    <?php esc_html_e( 'Variables disponibles:', 'rafflecore' ); ?>
                    <code>{nombre}</code> <code>{rifa}</code> <code>{boleto}</code> <code>{premio}</code> <code>{sitio}</code>
                </p>
            </div>

            <button type="button" id="rc-external-draw-btn"
                    data-raffle-id="<?php echo intval( $raffle->id ); ?>"
                    class="rc-btn rc-btn-primary rc-btn-lg" style="margin-top: 16px;">
                🎱 <?php esc_html_e( 'Buscar y Notificar Ganador', 'rafflecore' ); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="rc-panel" style="margin-top: 30px;">
        <h2>👥 <?php echo esc_html( sprintf( __( 'Compradores de esta Rifa (%d)', 'rafflecore' ), count( $purchases ) ) ); ?></h2>

        <?php if ( empty( $purchases ) ) : ?>
            <p class="rc-empty"><?php esc_html_e( 'No hay compradores para esta rifa aún.', 'rafflecore' ); ?></p>
        <?php else : ?>
        <table class="rc-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php esc_html_e( 'NOMBRE', 'rafflecore' ); ?></th>
                    <th><?php esc_html_e( 'EMAIL', 'rafflecore' ); ?></th>
                    <th><?php esc_html_e( 'BOLETOS', 'rafflecore' ); ?></th>
                    <th><?php esc_html_e( 'TOTAL', 'rafflecore' ); ?></th>
                    <th><?php esc_html_e( 'ESTADO', 'rafflecore' ); ?></th>
                    <th><?php esc_html_e( 'FECHA', 'rafflecore' ); ?></th>
                    <th><?php esc_html_e( 'NÚMEROS', 'rafflecore' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $purchases as $p ) :
                    $tickets = RaffleCore_Ticket_Model::get_by_purchase( $p->id );
                    $digits = isset($raffle->ticket_digits) ? (int)$raffle->ticket_digits : null;
                    $ticket_numbers = RaffleCore_Ticket_Service::format_numbers(array_map(function($t){return $t->ticket_number;}, $tickets), ['digits'=>$digits]);
                ?>
                <tr>
                    <td>#<?php echo intval( $p->id ); ?></td>
                    <td><strong><?php echo esc_html( $p->buyer_name ); ?></strong></td>
                    <td><?php echo esc_html( $p->buyer_email ); ?></td>
                    <td><?php echo intval( $p->quantity ); ?></td>
                    <td>$<?php echo number_format_i18n( $p->amount_paid ); ?></td>
                    <td>
                        <span class="rc-badge rc-badge-<?php echo esc_attr( $p->status ); ?>">
                            <?php echo esc_html( ucfirst( $p->status ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $p->purchase_date ) ) ); ?></td>
                    <td>
                        <?php if ( ! empty( $ticket_numbers ) ) : ?>
                            <div class="rc-ticket-badges">
                                <?php foreach ( $ticket_numbers as $tn ) : ?>
                                    <span class="rc-ticket-badge"><?php echo esc_html( $tn ); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <em><?php esc_html_e( 'Pendiente', 'rafflecore' ); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
