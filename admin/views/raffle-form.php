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
$lucky_numbers = '';
if ( $is_edit && ! empty( $raffle->lucky_numbers ) ) {
    $lnums = json_decode( $raffle->lucky_numbers, true );
    if ( is_array( $lnums ) ) {
        $lucky_numbers = implode( ', ', $lnums );
    }
}
?>
<div class="wrap rc-wrap">
    <h1 class="rc-title"><?php echo $is_edit ? '✏️ ' . esc_html__( 'Editar Rifa', 'rafflecore' ) : '➕ ' . esc_html__( 'Crear Nueva Rifa', 'rafflecore' ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rc-form" aria-label="<?php esc_attr_e( 'Formulario de rifa', 'rafflecore' ); ?>">
        <input type="hidden" name="action" value="rc_admin_form">
        <?php wp_nonce_field( 'rc_save_raffle', 'rc_nonce' ); ?>
        <input type="hidden" name="rc_save_raffle" value="1">
        <?php if ( $is_edit ) : ?>
            <input type="hidden" name="raffle_id" value="<?php echo intval( $raffle->id ); ?>">
        <?php endif; ?>

        <div class="rc-form-grid">
            <div class="rc-form-group rc-col-full">
                <label for="rc_title"><?php esc_html_e( 'Nombre de la Rifa', 'rafflecore' ); ?> *</label>
                <input type="text" id="rc_title" name="title" required
                       value="<?php echo $is_edit ? esc_attr( $raffle->title ) : ''; ?>"
                       placeholder="<?php esc_attr_e( 'Ej: iPhone 16 Pro Max', 'rafflecore' ); ?>">
            </div>

            <div class="rc-form-group rc-col-full">
                <label for="rc_description"><?php esc_html_e( 'Descripción', 'rafflecore' ); ?></label>
                <textarea id="rc_description" name="description" rows="4"
                          placeholder="<?php esc_attr_e( 'Descripción detallada del premio...', 'rafflecore' ); ?>"><?php echo $is_edit ? esc_textarea( $raffle->description ) : ''; ?></textarea>
            </div>

            <div class="rc-form-group">
                <label for="rc_total_tickets"><?php esc_html_e( 'Total de Boletos', 'rafflecore' ); ?> *</label>
                <input type="number" id="rc_total_tickets" name="total_tickets" min="1" required
                       value="<?php echo $is_edit ? intval( $raffle->total_tickets ) : ''; ?>"
                       placeholder="Ej: 1000">
            </div>

            <div class="rc-form-group">
                <label for="rc_ticket_price"><?php esc_html_e( 'Precio por Boleto ($)', 'rafflecore' ); ?> *</label>
                <input type="number" id="rc_ticket_price" name="ticket_price" min="0" step="1" required
                       value="<?php echo $is_edit ? intval( $raffle->ticket_price ) : ''; ?>"
                       placeholder="Ej: 5000">
            </div>

            <div class="rc-form-group">
                <label for="rc_draw_date"><?php esc_html_e( 'Fecha del Sorteo', 'rafflecore' ); ?></label>
                <input type="datetime-local" id="rc_draw_date" name="draw_date"
                       value="<?php echo $is_edit && $raffle->draw_date ? esc_attr( date( 'Y-m-d\TH:i', strtotime( $raffle->draw_date ) ) ) : ''; ?>">
            </div>

            <div class="rc-form-group">
                <label for="rc_status"><?php esc_html_e( 'Estado', 'rafflecore' ); ?></label>
                <select id="rc_status" name="status">
                    <option value="active" <?php selected( $is_edit ? $raffle->status : '', 'active' ); ?>><?php esc_html_e( 'Activa', 'rafflecore' ); ?></option>
                    <option value="paused" <?php selected( $is_edit ? $raffle->status : '', 'paused' ); ?>><?php esc_html_e( 'Pausada', 'rafflecore' ); ?></option>
                    <option value="finished" <?php selected( $is_edit ? $raffle->status : '', 'finished' ); ?>><?php esc_html_e( 'Finalizada', 'rafflecore' ); ?></option>
                </select>
            </div>

            <div class="rc-form-group">
                <label for="rc_font_family"><?php esc_html_e( 'Fuente', 'rafflecore' ); ?></label>
                <select id="rc_font_family" name="font_family">
                    <option value="" <?php selected( $is_edit ? ( $raffle->font_family ?? '' ) : '', '' ); ?>><?php esc_html_e( 'Predeterminada (Fredoka + Nunito)', 'rafflecore' ); ?></option>
                    <option value="custom" <?php selected( $is_edit ? ( $raffle->font_family ?? '' ) : '', 'custom' ); ?>>📁 <?php esc_html_e( 'Fuente personalizada (subir archivo)', 'rafflecore' ); ?></option>
                    <?php foreach ( RaffleCore_Raffle_Service::get_font_catalog() as $group_name => $fonts ) : ?>
                        <optgroup label="<?php echo esc_attr( $group_name ); ?>">
                            <?php foreach ( $fonts as $font_name ) : ?>
                                <option value="<?php echo esc_attr( $font_name ); ?>" <?php selected( $is_edit ? ( $raffle->font_family ?? '' ) : '', $font_name ); ?>><?php echo esc_html( $font_name ); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <p class="rc-help"><?php esc_html_e( 'Tipografía que se usará en el frontend de esta rifa. Solo se carga la fuente elegida.', 'rafflecore' ); ?></p>
            </div>

            <div class="rc-form-group rc-col-full" id="rc-custom-font-group" style="<?php echo ( $is_edit && ( $raffle->font_family ?? '' ) === 'custom' ) ? '' : 'display:none'; ?>">
                <label><?php esc_html_e( 'Archivo de Fuente (.woff2, .woff, .ttf, .otf)', 'rafflecore' ); ?></label>
                <div class="rc-font-upload">
                    <input type="hidden" id="rc_custom_font_url" name="custom_font_url"
                           value="<?php echo $is_edit ? esc_url( $raffle->custom_font_url ?? '' ) : ''; ?>">
                    <div id="rc-font-preview" class="rc-font-preview">
                        <?php if ( $is_edit && ! empty( $raffle->custom_font_url ) ) : ?>
                            <span class="rc-font-filename">✅ <?php echo esc_html( basename( $raffle->custom_font_url ) ); ?></span>
                        <?php else : ?>
                            <span><?php esc_html_e( 'Sin fuente cargada', 'rafflecore' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="rc-upload-font-btn" class="rc-btn rc-btn-secondary">📁 <?php esc_html_e( 'Subir Fuente', 'rafflecore' ); ?></button>
                    <button type="button" id="rc-remove-font-btn" class="rc-btn rc-btn-danger rc-btn-sm" style="<?php echo ( $is_edit && ! empty( $raffle->custom_font_url ) ) ? '' : 'display:none'; ?>">??? <?php esc_html_e( 'Quitar', 'rafflecore' ); ?></button>
                </div>
                <p class="rc-help"><?php esc_html_e( 'Sube un archivo de fuente. Formatos recomendados:', 'rafflecore' ); ?> <strong>.woff2</strong> <?php esc_html_e( '(más ligero) o .ttf', 'rafflecore' ); ?></p>
            </div>

            <div class="rc-form-group rc-col-full">
                <label for="rc_packages"><?php esc_html_e( 'Paquetes (cantidad:precio separados por coma)', 'rafflecore' ); ?></label>
                <input type="text" id="rc_packages" name="packages"
                       value="<?php echo esc_attr( $packages ); ?>"
                       placeholder="Ej: 5:20000, 10:35000, 25:75000">
                <p class="rc-help"><?php esc_html_e( 'Formato: cantidad:precio. Ejemplo:', 'rafflecore' ); ?> <code>5:20000, 10:35000, 25:75000</code></p>
            </div>

            <div class="rc-form-group rc-col-full">
                <label for="rc_lucky_numbers"><?php esc_html_e( 'Números Vendedores (separados por coma)', 'rafflecore' ); ?></label>
                <input type="text" id="rc_lucky_numbers" name="lucky_numbers"
                       value="<?php echo esc_attr( $lucky_numbers ); ?>"
                       placeholder="Ej: 42, 777, 1234, 5555, 9999">
                <p class="rc-help"><?php esc_html_e( 'Números especiales que otorgan un premio adicional al comprador. Ejemplo:', 'rafflecore' ); ?> <code>42, 777, 1234</code></p>
            </div>

            <div class="rc-form-group rc-col-full">
                <label><?php esc_html_e( 'Imagen del Premio', 'rafflecore' ); ?></label>
                <div class="rc-image-upload">
                    <input type="hidden" id="rc_prize_image" name="prize_image"
                           value="<?php echo $is_edit ? esc_url( $raffle->prize_image ) : ''; ?>">
                    <div id="rc-image-preview" class="rc-image-preview">
                        <?php if ( $is_edit && $raffle->prize_image ) : ?>
                            <img src="<?php echo esc_url( $raffle->prize_image ); ?>" alt="">
                        <?php else : ?>
                            <span><?php esc_html_e( 'Sin imagen', 'rafflecore' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="rc-upload-btn" class="rc-btn rc-btn-secondary">📷 <?php esc_html_e( 'Seleccionar Imagen', 'rafflecore' ); ?></button>
                    <button type="button" id="rc-remove-btn" class="rc-btn rc-btn-danger rc-btn-sm" style="<?php echo ( $is_edit && $raffle->prize_image ) ? '' : 'display:none'; ?>">✕ <?php esc_html_e( 'Quitar', 'rafflecore' ); ?></button>
                </div>
            </div>

            <div class="rc-form-group rc-col-full">
                <label for="rc_prize_gallery"><?php esc_html_e( 'Galería de Imágenes (URLs separadas por coma)', 'rafflecore' ); ?></label>
                <?php
                $gallery_value = '';
                if ( $is_edit && ! empty( $raffle->prize_gallery ) ) {
                    $gallery_arr = json_decode( $raffle->prize_gallery, true );
                    if ( is_array( $gallery_arr ) ) {
                        $gallery_value = implode( ', ', $gallery_arr );
                    }
                }
                ?>
                <textarea id="rc_prize_gallery" name="prize_gallery" rows="2"
                          placeholder="<?php esc_attr_e( 'https://ejemplo.com/img1.jpg, https://ejemplo.com/img2.jpg', 'rafflecore' ); ?>"><?php echo esc_textarea( $gallery_value ); ?></textarea>
                <p class="rc-help"><?php esc_html_e( 'Múltiples URLs de imágenes para mostrar en galería del premio.', 'rafflecore' ); ?></p>
            </div>
        </div>

        <div class="rc-form-actions">
            <button type="submit" class="rc-btn rc-btn-primary rc-btn-lg">
                <?php echo $is_edit ? '💾 ' . esc_html__( 'Guardar Cambios', 'rafflecore' ) : '🎫 ' . esc_html__( 'Crear Rifa', 'rafflecore' ); ?>
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles' ) ); ?>" class="rc-btn rc-btn-secondary rc-btn-lg"><?php esc_html_e( 'Cancelar', 'rafflecore' ); ?></a>
        </div>
    </form>
</div>
