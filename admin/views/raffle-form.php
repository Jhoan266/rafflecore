<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$is_edit  = ! empty( $raffle );
$packages = '';
if ( $is_edit && $raffle->packages ) {
    $pkgs = json_decode( $raffle->packages, true );
    if ( is_array( $pkgs ) ) {
        $packages = implode( ', ', array_map( function( $p ) {
            return $p['qty'] . ':' . $p['price'];
        }, $pkgs ) );
    }
}
?>
<div class="wrap rc-wrap">
    <h1 class="rc-title"><?php echo $is_edit ? '✏️ Editar Rifa' : '➕ Crear Nueva Rifa'; ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rc-form">
        <input type="hidden" name="action" value="rc_admin_form">
        <?php wp_nonce_field( 'rc_save_raffle', 'rc_nonce' ); ?>
        <input type="hidden" name="rc_save_raffle" value="1">
        <?php if ( $is_edit ) : ?>
            <input type="hidden" name="raffle_id" value="<?php echo intval( $raffle->id ); ?>">
        <?php endif; ?>

        <div class="rc-form-grid">
            <div class="rc-form-group rc-col-full">
                <label for="rc_title">Nombre de la Rifa *</label>
                <input type="text" id="rc_title" name="title" required
                       value="<?php echo $is_edit ? esc_attr( $raffle->title ) : ''; ?>"
                       placeholder="Ej: iPhone 16 Pro Max">
            </div>

            <div class="rc-form-group rc-col-full">
                <label for="rc_description">Descripción</label>
                <textarea id="rc_description" name="description" rows="4"
                          placeholder="Descripción detallada del premio..."><?php echo $is_edit ? esc_textarea( $raffle->description ) : ''; ?></textarea>
            </div>

            <div class="rc-form-group">
                <label for="rc_total_tickets">Total de Boletos *</label>
                <input type="number" id="rc_total_tickets" name="total_tickets" min="1" required
                       value="<?php echo $is_edit ? intval( $raffle->total_tickets ) : ''; ?>"
                       placeholder="Ej: 1000">
            </div>

            <div class="rc-form-group">
                <label for="rc_ticket_price">Precio por Boleto ($) *</label>
                <input type="number" id="rc_ticket_price" name="ticket_price" min="0" step="1" required
                       value="<?php echo $is_edit ? intval( $raffle->ticket_price ) : ''; ?>"
                       placeholder="Ej: 5000">
            </div>

            <div class="rc-form-group">
                <label for="rc_draw_date">Fecha del Sorteo</label>
                <input type="datetime-local" id="rc_draw_date" name="draw_date"
                       value="<?php echo $is_edit && $raffle->draw_date ? esc_attr( date( 'Y-m-d\TH:i', strtotime( $raffle->draw_date ) ) ) : ''; ?>">
            </div>

            <div class="rc-form-group">
                <label for="rc_status">Estado</label>
                <select id="rc_status" name="status">
                    <option value="active" <?php selected( $is_edit ? $raffle->status : '', 'active' ); ?>>Activa</option>
                    <option value="paused" <?php selected( $is_edit ? $raffle->status : '', 'paused' ); ?>>Pausada</option>
                    <option value="finished" <?php selected( $is_edit ? $raffle->status : '', 'finished' ); ?>>Finalizada</option>
                </select>
            </div>

            <div class="rc-form-group rc-col-full">
                <label for="rc_packages">Paquetes (cantidad:precio separados por coma)</label>
                <input type="text" id="rc_packages" name="packages"
                       value="<?php echo esc_attr( $packages ); ?>"
                       placeholder="Ej: 5:20000, 10:35000, 25:75000">
                <p class="rc-help">Formato: cantidad:precio. Ejemplo: <code>5:20000, 10:35000, 25:75000</code></p>
            </div>

            <div class="rc-form-group rc-col-full">
                <label>Imagen del Premio</label>
                <div class="rc-image-upload">
                    <input type="hidden" id="rc_prize_image" name="prize_image"
                           value="<?php echo $is_edit ? esc_url( $raffle->prize_image ) : ''; ?>">
                    <div id="rc-image-preview" class="rc-image-preview">
                        <?php if ( $is_edit && $raffle->prize_image ) : ?>
                            <img src="<?php echo esc_url( $raffle->prize_image ); ?>" alt="">
                        <?php else : ?>
                            <span>Sin imagen</span>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="rc-upload-btn" class="rc-btn rc-btn-secondary">📷 Seleccionar Imagen</button>
                    <button type="button" id="rc-remove-btn" class="rc-btn rc-btn-danger rc-btn-sm" style="<?php echo ( $is_edit && $raffle->prize_image ) ? '' : 'display:none'; ?>">✕ Quitar</button>
                </div>
            </div>
        </div>

        <div class="rc-form-actions">
            <button type="submit" class="rc-btn rc-btn-primary rc-btn-lg">
                <?php echo $is_edit ? '💾 Guardar Cambios' : '🎟️ Crear Rifa'; ?>
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles' ) ); ?>" class="rc-btn rc-btn-secondary rc-btn-lg">Cancelar</a>
        </div>
    </form>
</div>
