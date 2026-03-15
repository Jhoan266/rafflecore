<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$available     = $raffle->total_tickets - $raffle->sold_tickets;
$has_draw_date = ! empty( $raffle->draw_date ) && strtotime( $raffle->draw_date ) > time();
$lucky         = ! empty( $raffle->lucky_numbers ) ? json_decode( $raffle->lucky_numbers, true ) : array();

// Best-value package (favor 45-pack, else largest available)
$best_idx = -1;
if ( ! empty( $packages ) && count( $packages ) > 1 ) {
    // 1. Try to find the 45-pack
    foreach ( $packages as $i => $pkg ) {
        if ( $pkg['qty'] == 45 && $pkg['qty'] <= $available ) {
            $best_idx = $i;
            break;
        }
    }

    // 2. Fallback to largest available if 45-pack not found
    if ( $best_idx === -1 ) {
        $max_qty = 0;
        foreach ( $packages as $i => $pkg ) {
            if ( $pkg['qty'] > $max_qty && $pkg['qty'] <= $available ) {
                $max_qty  = $pkg['qty'];
                $best_idx = $i;
            }
        }
    }
}

$raffle_type = $raffle->type ?? 'quantity';
$sold_numbers = array();
if ( $raffle_type === 'selectable' ) {
    $sold_numbers = $this->api->get_used_numbers( $raffle->id );
}
?>

<?php
$rc_font_style = '';
if ( ! empty( $raffle->font_family ) ) {
    if ( $raffle->font_family === 'custom' && ! empty( $raffle->custom_font_url ) ) {
        $rc_font_style = ' style="--rc-font-heading:\'RCCustomFont\'; --rc-font-body:\'RCCustomFont\'"';
    } else {
        $ff = esc_attr( $raffle->font_family );
        $rc_font_style = ' style="--rc-font-heading:\''. $ff .'\'; --rc-font-body:\''. $ff .'\'"';
    }
}
?>
<?php
$rc_palette_class = '';
if ( ! empty( $raffle->color_palette ) ) {
    $rc_palette_class = ' rc-palette-' . esc_attr( $raffle->color_palette );
}
?>
<div class="rc-raffle rc-theme1<?php echo $rc_palette_class; ?>"<?php echo $rc_font_style; ?> data-raffle-id="<?php echo intval( $raffle->id ); ?>" data-ticket-price="<?php echo intval( $raffle->ticket_price ); ?>">

    <?php if ( $raffle->status === 'finished' ) : ?>
        <div class="rc-banner rc-banner--finished">
            <span class="rc-banner-icon">🏁</span>
            <span><?php esc_html_e( 'Esta rifa ha finalizado', 'rafflecore' ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Layout: LEFT + RIGHT -->
    <div class="rc-layout">

        <!-- LEFT COLUMN -->
        <div class="rc-main">
            <div class="rc-hero">
                <?php if ( $raffle->prize_image ) : ?>
                <div class="rc-hero-image">
                    <img src="<?php echo esc_url( $raffle->prize_image ); ?>" alt="<?php echo esc_attr( $raffle->title ); ?>">
                    <div class="rc-hero-gradient"></div>
                </div>
                <?php endif; ?>

                <div class="rc-hero-body">
                    <h2 class="rc-hero-title"><?php echo esc_html( $raffle->title ); ?></h2>
                    <?php if ( $raffle->prize_value > 0 ) : ?>
                    <div class="rc-prize-badge">
                        <span class="rc-prize-badge-label"><?php esc_html_e( 'Premio', 'rafflecore' ); ?></span>
                        <span class="rc-prize-badge-value">$<?php echo esc_html( number_format( $raffle->prize_value, 0 ) ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $raffle->description ) : ?>
                    <div class="rc-hero-desc"><?php echo wp_kses_post( nl2br( $raffle->description ) ); ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $raffle->lottery ) ) : ?>
                    <div class="rc-lottery-info" style="margin-bottom:14px; opacity:0.9; font-size:15px; font-weight: 500;">
                        <?php echo esc_html( $raffle->lottery ); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $raffle->draw_date ) : ?>
                    <div class="rc-meta"><span class="rc-meta-item">📅 <?php esc_html_e( 'Sorteo', 'rafflecore' ); ?>: <?php echo esc_html( date_i18n( 'd \d\e F, Y', strtotime( $raffle->draw_date ) ) ); ?></span></div>
                    <?php endif; ?>
                </div>
            </div><!-- /.rc-hero -->

            <?php
            // Re-calc countdown visibility
            $show_countdown = $has_draw_date;
            if ( $show_countdown && ! empty( $raffle->countdown_threshold ) ) {
                $threshold = (int) $raffle->countdown_threshold;
                $current_percent = ( $raffle->total_tickets > 0 ) ? ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 : 0;
                if ( $current_percent < $threshold ) { $show_countdown = false; }
            }
            if ( $show_countdown ) : ?>
            <div class="rc-countdown-section rc-theme1-countdown-section">
                <div class="rc-countdown-label-top"><?php esc_html_e( 'Tiempo restante', 'rafflecore' ); ?></div>
                <div class="rc-countdown" id="rc-countdown" data-draw-date="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $raffle->draw_date ) ) ); ?>">
                    <div class="rc-cd-item"><span class="rc-cd-num" id="rc-cd-days">00</span><span class="rc-cd-label"><?php esc_html_e( 'Días', 'rafflecore' ); ?></span></div>
                    <div class="rc-cd-sep">:</div>
                    <div class="rc-cd-item"><span class="rc-cd-num" id="rc-cd-hours">00</span><span class="rc-cd-label"><?php esc_html_e( 'Horas', 'rafflecore' ); ?></span></div>
                    <div class="rc-cd-sep">:</div>
                    <div class="rc-cd-item"><span class="rc-cd-num" id="rc-cd-minutes">00</span><span class="rc-cd-label"><?php esc_html_e( 'Min', 'rafflecore' ); ?></span></div>
                    <div class="rc-cd-sep">:</div>
                    <div class="rc-cd-item"><span class="rc-cd-num" id="rc-cd-seconds">00</span><span class="rc-cd-label"><?php esc_html_e( 'Seg', 'rafflecore' ); ?></span></div>
                </div>
                <div class="rc-countdown-expired" id="rc-countdown-expired" style="display:none;">🎉 <?php esc_html_e( '¡Es hora del sorteo!', 'rafflecore' ); ?></div>
            </div>
            <?php endif; ?>

            <?php
            $gallery_imgs = ! empty( $raffle->prize_gallery ) ? json_decode( $raffle->prize_gallery, true ) : array();
            if ( ! empty( $gallery_imgs ) && is_array( $gallery_imgs ) ) : ?>
            <div class="rc-gallery">
                <?php foreach ( $gallery_imgs as $gimg ) : ?>
                    <img src="<?php echo esc_url( $gimg ); ?>" alt="<?php echo esc_attr( $raffle->title ); ?>" loading="lazy">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div><!-- /.rc-main -->

        <!-- RIGHT COLUMN -->
        <div class="rc-sidebar">
            <div class="rc-progress-section">
                <div class="rc-progress-heading">
                    <span class="rc-progress-title"><?php esc_html_e( 'Boletos', 'rafflecore' ); ?></span>
                    <span class="rc-progress-big-percent"><?php echo esc_html( $progress ); ?>%</span>
                </div>
                <div class="rc-progress-bar-container">
                    <div class="rc-progress-bar-fill" style="width:<?php echo esc_attr( $progress ); ?>%"></div>
                </div>
                <div class="rc-progress-details">
                    <div class="rc-progress-detail">
                        <span class="rc-progress-detail-number"><?php echo number_format_i18n( $raffle->total_tickets ); ?></span>
                        <span class="rc-progress-detail-label"><?php esc_html_e( 'total', 'rafflecore' ); ?></span>
                    </div>
                    <div class="rc-progress-detail">
                        <span class="rc-progress-detail-number rc-progress-detail--remaining"><?php echo number_format_i18n( $available ); ?></span>
                        <span class="rc-progress-detail-label"><?php esc_html_e( 'disponibles', 'rafflecore' ); ?></span>
                    </div>
                    <div class="rc-progress-detail">
                        <span class="rc-progress-detail-number">$<?php echo number_format_i18n( $raffle->ticket_price ); ?></span>
                        <span class="rc-progress-detail-label"><?php esc_html_e( 'c/u', 'rafflecore' ); ?></span>
                    </div>
                </div>
            </div>

            <?php if ( ! empty( $lucky ) ) : ?>
            <div class="rc-lucky-section">
                <h3 class="rc-section-title">🍀 <?php esc_html_e( 'Números de la Suerte', 'rafflecore' ); ?></h3>
                <p class="rc-lucky-subtitle">
                    <?php 
                    if ( ! empty( $raffle->lucky_numbers_text ) ) {
                        echo esc_html( $raffle->lucky_numbers_text );
                    } else {
                        esc_html_e( '¡Si te toca uno de estos números, ganas un premio adicional!', 'rafflecore' );
                    }
                    ?>
                </p>
                <div class="rc-lucky-grid">
                    <?php foreach ( $lucky as $num ) : ?>
                    <div class="rc-lucky-number"><span class="rc-lucky-num"><?php echo esc_html( $num ); ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div><!-- /.rc-sidebar -->

    </div><!-- /.rc-layout -->

    <!-- PACKS — ancho completo debajo del layout -->
    <?php if ( $raffle->status === 'active' && $available > 0 ) : ?>
    <div class="rc-packages-full">

        <?php if ( $raffle_type === 'selectable' ) : ?>
        <div class="rc-selection-section">
            <div class="rc-packages-header">
                <h3><?php esc_html_e( 'Elige tus números', 'rafflecore' ); ?></h3>
                <p class="rc-packages-sub"><?php esc_html_e( 'Haz clic en los números que deseas comprar', 'rafflecore' ); ?></p>
            </div>
            <div class="rc-ticket-grid-container" id="rc-ticket-grid">
                <?php
                $max_n = isset($raffle->max_number) && $raffle->max_number > 0 ? (int)$raffle->max_number : (int)$raffle->total_tickets - 1;
                for ( $i = 0; $i <= $max_n; $i++ ) :
                    $is_sold = in_array( $i, $sold_numbers, true );
                ?>
                <div class="rc-ticket-box <?php echo $is_sold ? 'rc-ticket--sold' : 'rc-ticket--available'; ?>" data-number="<?php echo $i; ?>">
                    <?php echo str_pad( $i, strlen( $max_n ), '0', STR_PAD_LEFT ); ?>
                </div>
                <?php endfor; ?>
            </div>
            <div class="rc-selected-bar" id="rc-selected-bar" style="display:none;">
                <div class="rc-selected-info"><span class="rc-selected-count">0</span> <?php esc_html_e( 'boletos seleccionados', 'rafflecore' ); ?></div>
                <div class="rc-selected-total"><?php esc_html_e( 'Total:', 'rafflecore' ); ?> <span class="rc-selected-price">$0</span></div>
                <button type="button" class="rc-btn-buy rc-btn-package" id="rc-open-checkout-selectable"><?php esc_html_e( 'Comprar Seleccionados', 'rafflecore' ); ?></button>
            </div>
        </div>

        <?php elseif ( ! empty( $packages ) ) : ?>
        <div class="rc-packages-section">
            <div class="rc-packages-header">
                <h3><?php esc_html_e( 'Elige tu paquete', 'rafflecore' ); ?></h3>
                <p class="rc-packages-sub"><?php esc_html_e( 'Selecciona la cantidad de boletos que deseas', 'rafflecore' ); ?></p>
            </div>
            <div class="rc-packages-grid">
                <?php foreach ( $packages as $i => $pkg ) :
                    if ( $pkg['qty'] > $available ) continue;
                    $is_best    = ( $i === $best_idx );
                    $per_ticket = ( $pkg['qty'] > 0 ) ? round( $pkg['price'] / $pkg['qty'] ) : 0;
                ?>
                <div class="rc-package-card <?php echo $is_best ? 'rc-package-card--best' : ''; ?>"
                     data-qty="<?php echo intval( $pkg['qty'] ); ?>"
                     data-price="<?php echo intval( $pkg['price'] ); ?>">
                    <?php if ( $is_best ) : ?><div class="rc-package-ribbon"><?php esc_html_e( 'MÁS VENDIDO', 'rafflecore' ); ?></div><?php endif; ?>
                    <div class="rc-package-qty"><?php echo intval( $pkg['qty'] ); ?></div>
                    <div class="rc-package-label"><?php esc_html_e( 'boletos', 'rafflecore' ); ?></div>
                    <div class="rc-package-price">$<?php echo number_format_i18n( $pkg['price'] ); ?></div>
                    <div class="rc-package-per">$<?php echo number_format_i18n( $per_ticket ); ?> c/u</div>
                    <button type="button" class="rc-btn-package"><?php esc_html_e( 'Comprar ahora', 'rafflecore' ); ?></button>
                </div>
                <?php endforeach; ?>
            </div>

            <?php $min_custom = intval( $raffle->min_custom_qty ?? 0 ); if ( $min_custom > 0 ) : ?>
            <div class="rc-custom-qty-section">
                <div class="rc-custom-qty-divider"><span><?php esc_html_e( 'o elige tu cantidad', 'rafflecore' ); ?></span></div>
                <div class="rc-custom-qty-row">
                    <div class="rc-custom-qty-input-wrap">
                        <label for="rc-custom-qty-input"><?php esc_html_e( 'Cantidad', 'rafflecore' ); ?></label>
                        <div class="rc-qty-selector">
                            <button type="button" class="rc-qty-btn rc-qty-minus">−</button>
                            <input type="number" id="rc-custom-qty-input" class="rc-custom-qty-input rc-qty-input"
                                   min="<?php echo $min_custom; ?>" max="<?php echo intval( $available ); ?>"
                                   value="<?php echo $min_custom; ?>" data-min="<?php echo $min_custom; ?>"
                                   data-price="<?php echo intval( $raffle->ticket_price ); ?>">
                            <button type="button" class="rc-qty-btn rc-qty-plus">+</button>
                        </div>
                    </div>
                    <div class="rc-custom-qty-total">
                        <span class="rc-custom-qty-total-label"><?php esc_html_e( 'Total:', 'rafflecore' ); ?></span>
                        <span class="rc-custom-qty-total-price" id="rc-custom-total-price">$<?php echo number_format_i18n( $min_custom * $raffle->ticket_price ); ?></span>
                    </div>
                    <button type="button" class="rc-btn-package rc-btn-custom-buy" id="rc-custom-buy-btn"><?php esc_html_e( 'Comprar', 'rafflecore' ); ?></button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php else : ?>
        <div class="rc-single-buy">
            <h3 class="rc-section-title">🛒 <?php esc_html_e( 'Comprar Boletos', 'rafflecore' ); ?></h3>
            <p><?php echo esc_html( sprintf( __( 'Precio por boleto: %s', 'rafflecore' ), '$' . number_format_i18n( $raffle->ticket_price ) ) ); ?></p>
            <div class="rc-qty-selector">
                <button type="button" class="rc-qty-btn rc-qty-minus">−</button>
                <input type="number" class="rc-qty-input" value="1" min="1" max="<?php echo intval( $available ); ?>">
                <button type="button" class="rc-qty-btn rc-qty-plus">+</button>
            </div>
            <button type="button" class="rc-btn-buy rc-btn-package" data-qty="1" data-price="<?php echo intval( $raffle->ticket_price ); ?>"><?php esc_html_e( 'Comprar', 'rafflecore' ); ?></button>
        </div>
        <?php endif; ?>

    </div><!-- /.rc-packages-full -->

    <?php elseif ( $raffle->status === 'active' && $available <= 0 ) : ?>
    <div class="rc-banner rc-banner--soldout">
        <span class="rc-banner-icon">🎟️</span>
        <span><?php esc_html_e( '¡Todos los boletos han sido vendidos!', 'rafflecore' ); ?></span>
    </div>
    <?php endif; ?>

    <!-- Purchase Modal -->
    <div class="rc-modal" id="rc-purchase-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="rc-purchase-title">
        <div class="rc-modal-content">
            <button type="button" class="rc-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'rafflecore' ); ?>">&times;</button>
            <div class="rc-modal-header">
                <h3 id="rc-purchase-title"><?php esc_html_e( 'Completar Compra', 'rafflecore' ); ?></h3>
                <p class="rc-modal-summary" id="rc-modal-summary"></p>
            </div>
            <form id="rc-purchase-form" aria-label="<?php esc_attr_e( 'Formulario de compra', 'rafflecore' ); ?>">
                <input type="hidden" name="raffle_id" id="rc-form-raffle-id" value="<?php echo intval( $raffle->id ); ?>">
                <input type="hidden" name="ticket_qty" id="rc-form-qty">
                <input type="hidden" name="package_price" id="rc-form-price">
                <input type="hidden" name="chosen_numbers" id="rc-form-chosen-numbers">
                <!-- Honeypot anti-bot — oculto para usuarios reales -->
                <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
                    <input type="text" name="rc_website" tabindex="-1" autocomplete="off" value="">
                </div>
                <div class="rc-field">
                    <label for="rc-buyer-name"><?php esc_html_e( 'Nombre completo', 'rafflecore' ); ?></label>
                    <input type="text" id="rc-buyer-name" name="buyer_name" required placeholder="<?php esc_attr_e( 'Ej: Juan Pérez', 'rafflecore' ); ?>" aria-required="true">
                </div>
                <div class="rc-field">
                    <label for="rc-buyer-email"><?php esc_html_e( 'Correo electrónico', 'rafflecore' ); ?></label>
                    <input type="email" id="rc-buyer-email" name="buyer_email" required placeholder="tu@email.com" aria-required="true">
                </div>
                <div class="rc-field">
                    <label for="rc-buyer-phone"><?php esc_html_e( 'Teléfono', 'rafflecore' ); ?></label>
                    <input type="tel" id="rc-buyer-phone" name="buyer_phone" required placeholder="300 123 4567" aria-required="true">
                </div>
                <div class="rc-field rc-coupon-field">
                    <label for="rc-coupon-code"><?php esc_html_e( 'Código de cupón', 'rafflecore' ); ?> <small>(<?php esc_html_e( 'opcional', 'rafflecore' ); ?>)</small></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="rc-coupon-code" placeholder="DESCUENTO20" style="flex:1;text-transform:uppercase">
                        <button type="button" id="rc-apply-coupon" class="rc-btn-secondary" style="white-space:nowrap;padding:8px 16px;border:1px solid #667eea;border-radius:8px;background:transparent;color:#667eea;cursor:pointer;font-weight:600;"><?php esc_html_e( 'Aplicar', 'rafflecore' ); ?></button>
                    </div>
                    <small id="rc-coupon-status" style="display:block;margin-top:4px;min-height:18px;"></small>
                </div>
                <button type="submit" class="rc-btn-submit" id="rc-submit-purchase">
                    <span class="rc-btn-icon">💳</span> <?php esc_html_e( 'Proceder al Pago', 'rafflecore' ); ?>
                </button>
            </form>
            <div id="rc-purchase-loading" class="rc-loading" style="display:none">
                <div class="rc-spinner"></div>
                <span><?php esc_html_e( 'Procesando tu compra...', 'rafflecore' ); ?></span>
            </div>
            <div class="rc-modal-secure">🔒 <?php esc_html_e( 'Tus datos están protegidos', 'rafflecore' ); ?></div>
        </div>
    </div>

    <!-- Confirmation -->
    <div class="rc-modal" id="rc-confirm-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="rc-confirm-title">
        <div class="rc-modal-content rc-modal-confirmation">
            <button type="button" class="rc-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'rafflecore' ); ?>">&times;</button>
            <div class="rc-confirm-icon">🎉</div>
            <h3 id="rc-confirm-title"><?php esc_html_e( '¡Compra Exitosa!', 'rafflecore' ); ?></h3>
            <p><?php esc_html_e( 'Tus números de boleto:', 'rafflecore' ); ?></p>
            <div id="rc-confirm-tickets" class="rc-confirm-tickets"></div>
            <p class="rc-confirm-email">📧 <?php esc_html_e( 'Se ha enviado un correo de confirmación con tus números.', 'rafflecore' ); ?></p>
        </div>
    </div>

</div>
