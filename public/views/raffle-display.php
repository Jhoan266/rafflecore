<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$digits       = strlen( (string) $raffle->total_tickets );
$available    = $raffle->total_tickets - $raffle->sold_tickets;
$has_draw_date = ! empty( $raffle->draw_date ) && strtotime( $raffle->draw_date ) > time();
?>

<div class="rc-raffle" data-raffle-id="<?php echo intval( $raffle->id ); ?>">

    <!-- Hero -->
    <div class="rc-hero">
        <?php if ( $raffle->prize_image ) : ?>
            <img src="<?php echo esc_url( $raffle->prize_image ); ?>" class="rc-hero-image" alt="<?php echo esc_attr( $raffle->title ); ?>">
        <?php endif; ?>
        <div class="rc-hero-overlay">
            <h1 class="rc-hero-title"><?php echo esc_html( $raffle->title ); ?></h1>
            <?php if ( $raffle->description ) : ?>
                <p class="rc-hero-desc"><?php echo esc_html( $raffle->description ); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Countdown -->
    <?php if ( $has_draw_date ) : ?>
    <div class="rc-countdown-wrapper">
        <h3 class="rc-section-title">⏰ El sorteo se realiza en</h3>
        <div class="rc-countdown" data-draw-date="<?php echo esc_attr( $raffle->draw_date ); ?>">
            <div class="rc-cd-unit"><span class="rc-cd-num" id="rc-cd-days">00</span><span class="rc-cd-label">Días</span></div>
            <div class="rc-cd-sep">:</div>
            <div class="rc-cd-unit"><span class="rc-cd-num" id="rc-cd-hours">00</span><span class="rc-cd-label">Horas</span></div>
            <div class="rc-cd-sep">:</div>
            <div class="rc-cd-unit"><span class="rc-cd-num" id="rc-cd-minutes">00</span><span class="rc-cd-label">Minutos</span></div>
            <div class="rc-cd-sep">:</div>
            <div class="rc-cd-unit"><span class="rc-cd-num" id="rc-cd-seconds">00</span><span class="rc-cd-label">Segundos</span></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Progress -->
    <div class="rc-progress-section">
        <div class="rc-progress-header">
            <span class="rc-progress-label">Boletos vendidos</span>
            <span class="rc-progress-count"><?php echo number_format_i18n( $raffle->sold_tickets ); ?> / <?php echo number_format_i18n( $raffle->total_tickets ); ?></span>
        </div>
        <div class="rc-progress-track">
            <div class="rc-progress-bar-fill" style="width: <?php echo esc_attr( $progress ); ?>%">
                <span class="rc-progress-text"><?php echo esc_html( $progress ); ?>%</span>
            </div>
        </div>
        <p class="rc-available">🎫 <?php echo number_format_i18n( $available ); ?> boletos disponibles</p>
    </div>

    <!-- Packages -->
    <?php if ( ! empty( $packages ) ) : ?>
    <div class="rc-packages-section">
        <h3 class="rc-section-title">🎁 Elige tu paquete</h3>
        <div class="rc-packages-grid">
            <?php foreach ( $packages as $i => $pkg ) :
                $savings = ( $pkg['qty'] * $raffle->unit_price ) - $pkg['price'];
            ?>
            <div class="rc-package-card <?php echo $i === 1 ? 'rc-package-featured' : ''; ?>"
                 data-qty="<?php echo intval( $pkg['qty'] ); ?>"
                 data-price="<?php echo intval( $pkg['price'] ); ?>">
                <?php if ( $i === 1 ) : ?>
                    <div class="rc-package-ribbon">⭐ Popular</div>
                <?php endif; ?>
                <div class="rc-package-qty"><?php echo intval( $pkg['qty'] ); ?></div>
                <div class="rc-package-label">boletos</div>
                <div class="rc-package-price">$<?php echo number_format_i18n( $pkg['price'] ); ?></div>
                <?php if ( $savings > 0 ) : ?>
                    <div class="rc-package-savings">Ahorras $<?php echo number_format_i18n( $savings ); ?></div>
                <?php endif; ?>
                <button type="button" class="rc-btn-package">Seleccionar</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else : ?>
    <div class="rc-single-buy">
        <h3 class="rc-section-title">🛒 Comprar Boletos</h3>
        <p>Precio por boleto: <strong>$<?php echo number_format_i18n( $raffle->ticket_price ); ?></strong></p>
        <div class="rc-qty-selector">
            <button type="button" class="rc-qty-btn rc-qty-minus">−</button>
            <input type="number" class="rc-qty-input" value="1" min="1" max="<?php echo intval( $available ); ?>">
            <button type="button" class="rc-qty-btn rc-qty-plus">+</button>
        </div>
        <button type="button" class="rc-btn-buy rc-btn-package" data-qty="1" data-price="<?php echo intval( $raffle->ticket_price ); ?>">
            Comprar
        </button>
    </div>
    <?php endif; ?>

    <!-- Purchase Modal -->
    <div class="rc-modal" id="rc-purchase-modal" style="display:none">
        <div class="rc-modal-content">
            <button type="button" class="rc-modal-close">&times;</button>
            <h2>🛒 Comprar Boletos</h2>
            <p class="rc-modal-summary">
                <strong id="rc-modal-qty">0</strong> boletos por <strong id="rc-modal-price">$0</strong>
            </p>
            <form id="rc-purchase-form">
                <input type="hidden" name="raffle_id" value="<?php echo intval( $raffle->id ); ?>">
                <input type="hidden" name="ticket_qty" id="rc-form-qty">
                <input type="hidden" name="package_price" id="rc-form-price">
                <div class="rc-field">
                    <label>Nombre completo *</label>
                    <input type="text" name="buyer_name" required placeholder="Tu nombre completo">
                </div>
                <div class="rc-field">
                    <label>Email *</label>
                    <input type="email" name="buyer_email" required placeholder="tu@email.com">
                </div>
                <div class="rc-field">
                    <label>Teléfono *</label>
                    <input type="tel" name="buyer_phone" required placeholder="300 123 4567">
                </div>
                <button type="submit" class="rc-btn-submit" id="rc-submit-purchase">
                    💳 Proceder al Pago
                </button>
                <div id="rc-purchase-error" class="rc-error" style="display:none"></div>
                <div id="rc-purchase-loading" class="rc-loading" style="display:none">
                    <div class="rc-spinner"></div>
                    <span>Procesando...</span>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="rc-modal" id="rc-confirm-modal" style="display:none">
        <div class="rc-modal-content rc-modal-success">
            <h2>✅ ¡Compra Exitosa!</h2>
            <p>Tu compra ha sido procesada correctamente.</p>
            <p>Tus boletos han sido enviados a tu correo electrónico.</p>
            <div id="rc-confirm-tickets" class="rc-confirm-tickets"></div>
            <button type="button" class="rc-btn-submit rc-modal-close-btn">Cerrar</button>
        </div>
    </div>
</div>
