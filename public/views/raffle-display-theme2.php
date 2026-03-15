<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$available     = $raffle->total_tickets - $raffle->sold_tickets;
$has_draw_date = ! empty( $raffle->draw_date ) && strtotime( $raffle->draw_date ) > time();
$lucky         = ! empty( $raffle->lucky_numbers ) ? json_decode( $raffle->lucky_numbers, true ) : array();
$raffle_type   = $raffle->type ?? 'quantity';
$sold_numbers  = array();
if ( $raffle_type === 'selectable' ) {
    $sold_numbers = $this->api->get_used_numbers( $raffle->id );
}

// Best-value package (favor 45-pack, else largest available)
$best_idx = -1;
if ( ! empty( $packages ) && count( $packages ) > 1 ) {
    foreach ( $packages as $i => $pkg ) {
        if ( $pkg['qty'] == 45 && $pkg['qty'] <= $available ) {
            $best_idx = $i;
            break;
        }
    }
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

$rc_palette_class = '';
if ( ! empty( $raffle->color_palette ) ) {
    $rc_palette_class = ' rc-palette-' . esc_attr( $raffle->color_palette );
}

$rc_font_style = '';
if ( ! empty( $raffle->font_family ) ) {
    if ( $raffle->font_family === 'custom' && ! empty( $raffle->custom_font_url ) ) {
        $rc_font_style = ' style="--rc-font-heading:\'RCCustomFont\'; --rc-font-body:\'RCCustomFont\'"';
    } else {
        $ff = esc_attr( $raffle->font_family );
        $rc_font_style = ' style="--rc-font-heading:\'' . $ff . '\'; --rc-font-body:\'' . $ff . '\'"';
    }
}
?>
<div class="rc-raffle rc-theme2<?php echo $rc_palette_class; ?>"<?php echo $rc_font_style; ?> data-raffle-id="<?php echo intval( $raffle->id ); ?>" data-ticket-price="<?php echo intval( $raffle->ticket_price ); ?>">

    <?php if ( $raffle->status === 'finished' ) : ?>
        <div class="rc-t2-banner rc-t2-banner--finished">
            <span>🏁 Esta rifa ha finalizado</span>
        </div>
    <?php endif; ?>

    <!-- Layout: LEFT + RIGHT -->
    <div class="rc-layout">

        <!-- LEFT COLUMN -->
        <div class="rc-main">
            <!-- Hero -->
            <div class="rc-t2-hero">
                <?php if ( $raffle->prize_image ) : ?>
                    <div class="rc-t2-hero-image">
                        <img src="<?php echo esc_url( $raffle->prize_image ); ?>"
                             alt="<?php echo esc_attr( $raffle->title ); ?>">
                        <div class="rc-t2-hero-overlay"></div>
                    </div>
                <?php endif; ?>
                <div class="rc-t2-hero-body">
                    <h2 class="rc-t2-title"><?php echo esc_html( $raffle->title ); ?></h2>
                    <?php if ( $raffle->prize_value > 0 ) : ?>
                    <div class="rc-t2-prize-badge">
                        <span class="rc-t2-prize-label">Premio</span>
                        <span class="rc-t2-prize-value">$<?php echo esc_html( number_format( $raffle->prize_value, 0 ) ); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $raffle->description ) : ?>
                        <div class="rc-t2-description"><?php echo wp_kses_post( nl2br( $raffle->description ) ); ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $raffle->lottery ) ) : ?>
                        <div class="rc-t2-lottery-info"><?php echo esc_html( $raffle->lottery ); ?></div>
                    <?php endif; ?>
                    <?php if ( $raffle->draw_date ) : ?>
                        <div class="rc-t2-meta">
                            <span>📅 Sorteo: <?php echo esc_html( date_i18n( 'd \d\e F, Y', strtotime( $raffle->draw_date ) ) ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div><!-- /.rc-t2-hero -->

            <!-- Countdown moved here as an individual container -->
            <?php if ( $has_draw_date && $raffle->status === 'active' ) : ?>
                <div class="rc-t2-countdown-section">
                    <div class="rc-t2-countdown-label">Tiempo restante</div>
                    <div id="rc-countdown" class="rc-t2-countdown"
                            data-draw-date="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $raffle->draw_date ) ) ); ?>">
                        <div class="rc-t2-cd-item">
                            <span class="rc-t2-cd-number" id="rc-cd-days">00</span>
                            <span class="rc-t2-cd-label">Días</span>
                        </div>
                        <div class="rc-t2-cd-sep">:</div>
                        <div class="rc-t2-cd-item">
                            <span class="rc-t2-cd-number" id="rc-cd-hours">00</span>
                            <span class="rc-t2-cd-label">Horas</span>
                        </div>
                        <div class="rc-t2-cd-sep">:</div>
                        <div class="rc-t2-cd-item">
                            <span class="rc-t2-cd-number" id="rc-cd-minutes">00</span>
                            <span class="rc-t2-cd-label">Min</span>
                        </div>
                        <div class="rc-t2-cd-sep">:</div>
                        <div class="rc-t2-cd-item">
                            <span class="rc-t2-cd-number" id="rc-cd-seconds">00</span>
                            <span class="rc-t2-cd-label">Seg</span>
                        </div>
                    </div>
                    <div id="rc-countdown-expired" style="display:none;" class="rc-t2-expired">
                        🎉 ¡Es hora del sorteo!
                    </div>
                </div>
            <?php endif; ?>
        </div><!-- /.rc-main -->

        <!-- RIGHT COLUMN -->
        <div class="rc-sidebar">
            <!-- Progress -->
            <div class="rc-t2-progress-section">
                <div class="rc-t2-progress-heading">
                    <span class="rc-t2-progress-title">Boletos vendidos</span>
                    <span class="rc-progress-big-percent"><?php echo esc_html( $progress ); ?>%</span>
                </div>
                <div class="rc-t2-progress-bar-wrap">
                    <div class="rc-progress-bar-fill" style="width:<?php echo esc_attr( $progress ); ?>%"></div>
                </div>
                <div class="rc-t2-progress-details">
                    <div class="rc-t2-stat-card">
                        <span class="rc-t2-stat-number"><?php echo esc_html( number_format( $raffle->sold_tickets ) ); ?></span>
                        <span class="rc-t2-detail-label">vendidos</span>
                    </div>
                    <div class="rc-t2-stat-card">
                        <span class="rc-progress-detail-number"><?php echo esc_html( number_format( $raffle->total_tickets ) ); ?></span>
                        <span class="rc-t2-detail-label">total</span>
                    </div>
                </div>
            </div>

            <!-- Lucky Numbers -->
            <?php if ( ! empty( $lucky ) ) : ?>
            <div class="rc-t2-lucky-section">
                <h3 class="rc-t2-section-title">🍀 Números de la Suerte</h3>
                <p class="rc-t2-lucky-subtitle">¡Si te toca uno de estos números, ganas un premio adicional!</p>
                <div class="rc-t2-lucky-grid">
                    <?php foreach ( $lucky as $num ) : ?>
                    <div class="rc-t2-lucky-number"><?php echo esc_html( $num ); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div><!-- /.rc-sidebar -->

    </div><!-- /.rc-layout -->

    <?php if ( $raffle->status === 'active' && $available > 0 ) : ?>

        <?php if ( $raffle_type === 'selectable' ) : ?>
        <!-- Selectable ticket grid -->
        <div class="rc-t2-selection-section">
            <div class="rc-t2-packages-header">
                <h3>Elige tus números</h3>
                <p class="rc-t2-packages-sub">Haz clic en los números que deseas comprar</p>
            </div>
            <div class="rc-ticket-grid-container" id="rc-ticket-grid">
                <?php
                $max_n = isset( $raffle->max_number ) && $raffle->max_number > 0 ? (int) $raffle->max_number : (int) $raffle->total_tickets - 1;
                for ( $i = 0; $i <= $max_n; $i++ ) :
                    $is_sold = in_array( $i, $sold_numbers, true );
                ?>
                <div class="rc-ticket-box <?php echo $is_sold ? 'rc-ticket--sold' : 'rc-ticket--available'; ?>" data-number="<?php echo $i; ?>">
                    <?php echo str_pad( $i, strlen( $max_n ), '0', STR_PAD_LEFT ); ?>
                </div>
                <?php endfor; ?>
            </div>
            <div class="rc-selected-bar" id="rc-selected-bar" style="display:none;">
                <div class="rc-selected-info"><span class="rc-selected-count">0</span> boletos seleccionados</div>
                <div class="rc-selected-total">Total: <span class="rc-selected-price">$0</span></div>
                <button type="button" class="rc-btn-package" id="rc-open-checkout-selectable">Comprar Seleccionados</button>
            </div>
        </div>

        <?php elseif ( ! empty( $packages ) ) : ?>
        <!-- Packages -->
        <div class="rc-t2-packages">
            <div class="rc-t2-packages-header">
                <h3>Elige tu paquete</h3>
                <p class="rc-t2-packages-sub">Selecciona la cantidad de boletos que deseas</p>
            </div>
            <div class="rc-t2-packages-grid">
                <?php foreach ( $packages as $i => $pkg ) :
                    if ( $pkg['qty'] > $available ) continue;
                    $is_best    = ( $i === $best_idx );
                    $per_ticket = ( $pkg['qty'] > 0 ) ? round( $pkg['price'] / $pkg['qty'] ) : 0;
                ?>
                    <div class="rc-package-card rc-t2-card<?php echo $is_best ? ' rc-t2-card--best' : ''; ?>"
                         data-qty="<?php echo esc_attr( $pkg['qty'] ); ?>"
                         data-price="<?php echo esc_attr( $pkg['price'] ); ?>">
                        <?php if ( $is_best ) : ?>
                            <div class="rc-t2-ribbon">Mejor opción</div>
                        <?php endif; ?>
                        <div class="rc-t2-card-qty"><?php echo esc_html( $pkg['qty'] ); ?></div>
                        <div class="rc-t2-card-label">boletos</div>
                        <div class="rc-t2-card-price">$<?php echo esc_html( number_format( $pkg['price'] ) ); ?></div>
                        <div class="rc-t2-card-per">$<?php echo esc_html( number_format( $per_ticket ) ); ?> c/u</div>
                        <button class="rc-btn-package rc-t2-buy-btn"
                                data-qty="<?php echo esc_attr( $pkg['qty'] ); ?>"
                                data-price="<?php echo esc_attr( $pkg['price'] ); ?>">
                            Comprar ahora
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php $min_custom = intval( $raffle->min_custom_qty ?? 0 ); if ( $min_custom > 0 ) : ?>
            <div class="rc-t2-custom-qty-section">
                <div class="rc-t2-custom-qty-divider"><span>o elige tu cantidad</span></div>
                <div class="rc-t2-custom-qty-row">
                    <div class="rc-t2-custom-qty-input-wrap">
                        <label for="rc-custom-qty-input">Cantidad</label>
                        <div class="rc-t2-qty-selector">
                            <button type="button" class="rc-qty-btn rc-qty-minus">−</button>
                            <input type="number" id="rc-custom-qty-input" class="rc-qty-input"
                                   min="<?php echo $min_custom; ?>" max="<?php echo intval( $available ); ?>"
                                   value="<?php echo $min_custom; ?>" data-min="<?php echo $min_custom; ?>"
                                   data-price="<?php echo intval( $raffle->ticket_price ); ?>">
                            <button type="button" class="rc-qty-btn rc-qty-plus">+</button>
                        </div>
                    </div>
                    <div class="rc-t2-custom-qty-total">
                        <span>Total:</span>
                        <span id="rc-custom-total-price">$<?php echo number_format( $min_custom * $raffle->ticket_price ); ?></span>
                    </div>
                    <button type="button" class="rc-btn-package rc-t2-buy-btn" id="rc-custom-buy-btn">Comprar</button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php else : ?>
        <!-- Single buy (sin paquetes definidos) -->
        <div class="rc-t2-single-buy">
            <h3 class="rc-t2-section-title">🛒 Comprar Boletos</h3>
            <p>Precio por boleto: $<?php echo number_format( $raffle->ticket_price ); ?></p>
            <div class="rc-t2-qty-selector">
                <button type="button" class="rc-qty-btn rc-qty-minus">−</button>
                <input type="number" class="rc-qty-input" value="1" min="1" max="<?php echo intval( $available ); ?>">
                <button type="button" class="rc-qty-btn rc-qty-plus">+</button>
            </div>
            <button type="button" class="rc-btn-package rc-t2-buy-btn" data-qty="1" data-price="<?php echo intval( $raffle->ticket_price ); ?>">Comprar</button>
        </div>
        <?php endif; ?>

        <!-- Trust indicators -->
        <div class="rc-t2-trust">
            <div class="rc-t2-trust-item">🔒 Compra segura</div>
            <div class="rc-t2-trust-item">📧 Confirmación inmediata</div>
            <div class="rc-t2-trust-item">🎰 Números aleatorios</div>
        </div>

    <?php elseif ( $raffle->status === 'active' && $available <= 0 ) : ?>
        <div class="rc-t2-banner rc-t2-banner--soldout">
            <span>🎟️ ¡Todos los boletos han sido vendidos!</span>
        </div>
    <?php endif; ?>

    <!-- Purchase Modal -->
    <div class="rc-modal rc-t2-modal" id="rc-purchase-modal" style="display:none;"
         role="dialog" aria-modal="true" aria-labelledby="rc-t2-modal-title">
        <div class="rc-t2-modal-content">
            <button class="rc-modal-close rc-t2-modal-close" aria-label="Cerrar">&times;</button>
            <div class="rc-t2-modal-header">
                <h3 id="rc-t2-modal-title">Completar Compra</h3>
                <p id="rc-modal-summary" class="rc-t2-modal-summary"></p>
            </div>
            <form id="rc-purchase-form">
                <input type="hidden" name="raffle_id"      id="rc-form-raffle-id"      value="<?php echo esc_attr( $raffle->id ); ?>">
                <input type="hidden" name="ticket_qty"     id="rc-form-qty"            value="">
                <input type="hidden" name="package_price"  id="rc-form-price"          value="">
                <input type="hidden" name="chosen_numbers" id="rc-form-chosen-numbers" value="">
                <!-- Honeypot anti-bot -->
                <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
                    <input type="text" name="rc_website" tabindex="-1" autocomplete="off" value="">
                </div>
                <div class="rc-t2-form-group">
                    <label for="rc-t2-name">Nombre completo</label>
                    <input type="text" id="rc-t2-name" name="buyer_name" required placeholder="Ej: Juan Pérez">
                </div>
                <div class="rc-t2-form-group">
                    <label for="rc-t2-email">Correo electrónico</label>
                    <input type="email" id="rc-t2-email" name="buyer_email" required placeholder="tu@email.com">
                </div>
                <div class="rc-t2-form-group">
                    <label for="rc-t2-phone">Teléfono <span class="rc-t2-optional">(opcional)</span></label>
                    <input type="tel" id="rc-t2-phone" name="buyer_phone" placeholder="+57 300 000 0000">
                </div>
                <div class="rc-t2-form-group">
                    <label for="rc-t2-coupon">Código de cupón <span class="rc-t2-optional">(opcional)</span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="rc-coupon-code" placeholder="DESCUENTO20" style="flex:1;text-transform:uppercase">
                        <button type="button" id="rc-apply-coupon" class="rc-t2-coupon-btn">Aplicar</button>
                    </div>
                    <small id="rc-coupon-status" style="display:block;margin-top:4px;min-height:18px;"></small>
                </div>
                <button type="submit" id="rc-submit-purchase" class="rc-t2-submit-btn">
                    <span>💳</span> Proceder al Pago
                </button>
                <div id="rc-purchase-loading" style="display:none;" class="rc-t2-loading">
                    <div class="rc-t2-spinner"></div>
                    <span>Procesando tu compra...</span>
                </div>
            </form>
            <p class="rc-t2-modal-secure">🔒 Tus datos están protegidos</p>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="rc-modal rc-t2-modal" id="rc-confirm-modal" style="display:none;"
         role="dialog" aria-modal="true">
        <div class="rc-t2-modal-content rc-t2-modal-confirm">
            <button class="rc-modal-close rc-t2-modal-close" aria-label="Cerrar">&times;</button>
            <div class="rc-t2-confirm-icon">🎉</div>
            <h3>¡Compra Exitosa!</h3>
            <p>Tus números de boleto:</p>
            <div id="rc-confirm-tickets" class="rc-t2-ticket-numbers"></div>
            <p class="rc-t2-confirm-email">
                📧 Se ha enviado un correo de confirmación con tus números.
            </p>
        </div>
    </div>

</div>
