/**
 * RaffleCore — Admin JavaScript
 */
(function ($) {
  "use strict";

  // ─── Media Uploader ─────────────────────
  $(document).on("click", "#rc-upload-btn", function (e) {
    e.preventDefault();
    var frame = wp.media({
      title: "Seleccionar Imagen del Premio",
      button: { text: "Usar esta imagen" },
      multiple: false,
    });
    frame.on("select", function () {
      var attachment = frame.state().get("selection").first().toJSON();
      $("#rc_prize_image").val(attachment.url);
      $("#rc-image-preview").html('<img src="' + attachment.url + '" alt="">');
      $("#rc-remove-btn").show();
    });
    frame.open();
  });

  $(document).on("click", "#rc-remove-btn", function (e) {
    e.preventDefault();
    $("#rc_prize_image").val("");
    $("#rc-image-preview").html("<span>Sin imagen</span>");
    $(this).hide();
  });

  // ─── Draw Button ────────────────────────
  $(document).on("click", "#rc-draw-btn", function (e) {
    e.preventDefault();
    var btn = $(this);
    var raffleId = btn.data("raffle-id");

    if (
      !confirm(
        "¿Estás seguro de realizar el sorteo? Esta acción es irreversible.",
      )
    )
      return;

    btn.prop("disabled", true).text("🎰 Sorteando...");

    $.ajax({
      url: rcAdmin.ajax_url,
      type: "POST",
      data: {
        action: "rc_draw_winner",
        raffle_id: raffleId,
        nonce: rcAdmin.nonce,
      },
      success: function (res) {
        if (res.success) {
          btn.hide();
          var d = res.data;
          $("#rc-draw-ticket").text("Boleto #" + d.ticket_number);
          $("#rc-draw-buyer").text(d.buyer_name);
          $("#rc-draw-result").slideDown(400);
        } else {
          alert("Error: " + (res.data || "Error desconocido"));
          btn.prop("disabled", false).text("🎰 Realizar Sorteo");
        }
      },
      error: function () {
        alert("Error de conexión. Intenta de nuevo.");
        btn.prop("disabled", false).text("🎰 Realizar Sorteo");
      },
    });
  });
})(jQuery);
