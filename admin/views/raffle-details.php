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
                <div><strong>Precio:</strong> $<?php echo number_format_i18n( $raffle->ticket_price ); ?></div>
                <div><strong>Boletos:</strong> <?php echo number_format_i18n( $raffle->sold_tickets ); ?> / <?php echo number_format_i18n( $raffle->total_tickets ); ?></div>
                <div><strong>Sorteo:</strong> <?php echo $raffle->draw_date ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $raffle->draw_date ) ) ) : 'No definido'; ?></div>
                <div><strong>Shortcode:</strong> <code>[rafflecore id="<?php echo intval( $raffle->id ); ?>"]</code></div>
            </div>

            <div class="rc-progress-bar rc-progress-lg">
                <div class="rc-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%">
                    <?php echo esc_html( $progress ); ?>%
                </div>
            </div>

            <?php if ( is_array( $pkgs ) && count( $pkgs ) > 0 ) : ?>
            <div class="rc-packages-row">
                <strong>Paquetes:</strong>
                <?php foreach ( $pkgs as $p ) : ?>
                    <span class="rc-package-badge"><?php echo intval( $p['qty'] ); ?> boletos — $<?php echo number_format_i18n( $p['price'] ); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="rc-detail-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles&action=edit&id=' . $raffle->id ) ); ?>" class="rc-btn rc-btn-warning">✏️ Editar</a>
                <?php if ( $raffle->status === 'active' && $raffle->sold_tickets > 0 ) : ?>
                    <button type="button" id="rc-draw-btn"
                            data-raffle-id="<?php echo intval( $raffle->id ); ?>"
                            class="rc-btn rc-btn-primary rc-btn-lg">
                        🎰 Realizar Sorteo
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ( $winner ) : ?>
    <div class="rc-winner-banner">
        <h2>🏆 ¡Ganador!</h2>
        <div class="rc-winner-info">
            <span class="rc-winner-ticket">Boleto #<?php echo esc_html( str_pad( $winner->ticket_number, strlen( (string) $raffle->total_tickets ), '0', STR_PAD_LEFT ) ); ?></span>
            <span class="rc-winner-name"><?php echo esc_html( $winner->buyer_name ); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div id="rc-draw-result" style="display:none" class="rc-winner-banner">
        <h2>🏆 ¡Tenemos Ganador!</h2>
        <div class="rc-winner-info">
            <span id="rc-draw-ticket" class="rc-winner-ticket"></span>
            <span id="rc-draw-buyer" class="rc-winner-name"></span>
        </div>
    </div>

    <div class="rc-panel" style="margin-top: 30px;">
        <h2>👥 Compradores de esta Rifa (<?php echo count( $purchases ); ?>)</h2>

        <?php if ( empty( $purchases ) ) : ?>
            <p class="rc-empty">No hay compradores para esta rifa aún.</p>
        <?php else : ?>
        <table class="rc-table">
            <thead>
                <tr>
                    <th>Comprador</th>
                    <th>Email</th>
                    <th>Cantidad</th>
                    <th>Estado</th>
                    <th>Boletos</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $purchases as $p ) :
                    $tickets = RaffleCore_Ticket_Model::get_by_purchase( $p->id );
                    $ticket_numbers = array_map( function( $t ) use ( $raffle ) {
                        return str_pad( $t->ticket_number, strlen( (string) $raffle->total_tickets ), '0', STR_PAD_LEFT );
                    }, $tickets );
                ?>
                <tr>
                    <td><strong><?php echo esc_html( $p->buyer_name ); ?></strong></td>
                    <td><?php echo esc_html( $p->buyer_email ); ?></td>
                    <td><?php echo intval( $p->quantity ); ?></td>
                    <td>
                        <span class="rc-badge rc-badge-<?php echo esc_attr( $p->status ); ?>">
                            <?php echo esc_html( ucfirst( $p->status ) ); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ( ! empty( $ticket_numbers ) ) : ?>
                            <div class="rc-ticket-badges">
                                <?php foreach ( $ticket_numbers as $tn ) : ?>
                                    <span class="rc-ticket-badge"><?php echo esc_html( $tn ); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <em>Pendiente</em>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $p->purchase_date ) ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
