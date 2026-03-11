<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="rc-my-tickets-wrap" style="max-width:700px;margin:40px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <h2 style="text-align:center;margin-bottom:24px;"><?php esc_html_e( '🎟️ Mis Boletos', 'rafflecore' ); ?></h2>

    <form id="rc-lookup-form" class="rc-lookup-form" aria-label="<?php esc_attr_e( 'Buscar boletos', 'rafflecore' ); ?>"
          style="display:flex;gap:12px;margin-bottom:32px;">
        <input type="email" id="rc-lookup-email" name="email" required
               placeholder="<?php esc_attr_e( 'Tu correo electrónico', 'rafflecore' ); ?>"
               aria-label="<?php esc_attr_e( 'Correo electrónico', 'rafflecore' ); ?>"
               style="flex:1;padding:12px 16px;border:2px solid #ddd;border-radius:10px;font-size:16px;">
        <button type="submit" class="rc-btn rc-btn-primary"
                style="padding:12px 24px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;">
            <?php esc_html_e( 'Buscar', 'rafflecore' ); ?>
        </button>
    </form>

    <div id="rc-tickets-loading" style="display:none;text-align:center;padding:40px;">
        <div class="rc-spinner"></div>
        <p><?php esc_html_e( 'Buscando boletos...', 'rafflecore' ); ?></p>
    </div>

    <div id="rc-tickets-results" style="display:none;"></div>
    <div id="rc-tickets-empty" style="display:none;text-align:center;padding:40px;color:#666;">
        <p style="font-size:18px;">😔</p>
        <p><?php esc_html_e( 'No se encontraron boletos con este correo.', 'rafflecore' ); ?></p>
    </div>
</div>

<script>
(function($) {
    $('#rc-lookup-form').on('submit', function(e) {
        e.preventDefault();
        var email = $('#rc-lookup-email').val().trim();
        if (!email) return;

        $('#rc-tickets-results, #rc-tickets-empty').hide();
        $('#rc-tickets-loading').show();

        $.ajax({
            url: '<?php echo esc_url( rest_url( 'rafflecore/v1/lookup-tickets' ) ); ?>',
            type: 'POST',
            data: JSON.stringify({ email: email }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
            },
            success: function(res) {
                $('#rc-tickets-loading').hide();
                if (!res.length) {
                    $('#rc-tickets-empty').show();
                    return;
                }
                var html = '';
                res.forEach(function(group) {
                    html += '<div style="background:#f8f9fa;border-radius:12px;padding:20px;margin-bottom:16px;">';
                    html += '<h3 style="margin:0 0 8px;">' + $('<span>').text(group.raffle).html() + '</h3>';
                    html += '<p style="color:#666;margin:0 0 12px;">' + group.tickets.length + ' <?php echo esc_js( __( 'boletos', 'rafflecore' ) ); ?></p>';
                    html += '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
                    group.tickets.forEach(function(t) {
                        html += '<span style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:6px 14px;border-radius:8px;font-weight:700;">#' + $('<span>').text(t).html() + '</span>';
                    });
                    html += '</div></div>';
                });
                $('#rc-tickets-results').html(html).show();
            },
            error: function() {
                $('#rc-tickets-loading').hide();
                alert('<?php echo esc_js( __( 'Error al buscar boletos.', 'rafflecore' ) ); ?>');
            }
        });
    });
})(jQuery);
</script>
