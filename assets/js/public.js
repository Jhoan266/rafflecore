/**
 * RaffleCore — Public Frontend JavaScript
 */
(function ($) {
  "use strict";

  // ─── Countdown Timer ────────────────────
  var $cd = $(".rc-countdown");
  if ($cd.length) {
    var drawDate = new Date($cd.data("draw-date")).getTime();

    function updateCountdown() {
      var now = Date.now();
      var diff = drawDate - now;

      if (diff <= 0) {
        $("#rc-cd-days, #rc-cd-hours, #rc-cd-minutes, #rc-cd-seconds").text(
          "00",
        );
        return;
      }

      var d = Math.floor(diff / 86400000);
      var h = Math.floor((diff % 86400000) / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);

      $("#rc-cd-days").text(String(d).padStart(2, "0"));
      $("#rc-cd-hours").text(String(h).padStart(2, "0"));
      $("#rc-cd-minutes").text(String(m).padStart(2, "0"));
      $("#rc-cd-seconds").text(String(s).padStart(2, "0"));
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
  }

  // ─── Package Selection ──────────────────
  $(document).on("click", ".rc-package-card .rc-btn-package", function (e) {
    e.preventDefault();
    var card = $(this).closest(".rc-package-card");
    var qty = card.data("qty");
    var price = card.data("price");

    $(".rc-package-card").removeClass("rc-selected");
    card.addClass("rc-selected");

    openPurchaseModal(qty, price);
  });

  // ─── Quantity Selector (no packages mode) ──
  $(document).on("click", ".rc-qty-minus", function () {
    var $inp = $(this).siblings(".rc-qty-input");
    var val = parseInt($inp.val(), 10);
    if (val > 1) $inp.val(val - 1);
  });
  $(document).on("click", ".rc-qty-plus", function () {
    var $inp = $(this).siblings(".rc-qty-input");
    var val = parseInt($inp.val(), 10);
    var max = parseInt($inp.attr("max"), 10);
    if (val < max) $inp.val(val + 1);
  });
  $(document).on("click", ".rc-single-buy .rc-btn-package", function (e) {
    e.preventDefault();
    var qty = parseInt($(".rc-qty-input").val(), 10) || 1;
    var price = $(this).data("price") * qty;
    openPurchaseModal(qty, price);
  });

  // ─── Modal Management ───────────────────
  function openPurchaseModal(qty, price) {
    $("#rc-modal-qty").text(qty);
    $("#rc-modal-price").text(
      rcPublic.currency + new Intl.NumberFormat().format(price),
    );
    $("#rc-form-qty").val(qty);
    $("#rc-form-price").val(price);
    $("#rc-purchase-error").hide();
    $("#rc-purchase-loading").hide();
    $("#rc-submit-purchase").show().prop("disabled", false);
    $("#rc-purchase-modal").fadeIn(200);
  }

  $(document).on("click", ".rc-modal-close, .rc-modal-close-btn", function () {
    $(this).closest(".rc-modal").fadeOut(200);
  });
  $(document).on("click", ".rc-modal", function (e) {
    if (e.target === this) $(this).fadeOut(200);
  });

  // ─── Purchase Form Submit ───────────────
  $(document).on("submit", "#rc-purchase-form", function (e) {
    e.preventDefault();

    var $form = $(this);
    var $btn = $("#rc-submit-purchase");
    var $error = $("#rc-purchase-error");
    var $loader = $("#rc-purchase-loading");

    $btn.hide();
    $error.hide();
    $loader.show();

    if (rcPublic.wc_enabled) {
      // WooCommerce flow: AJAX → redirect to pay
      $.ajax({
        url: rcPublic.ajax_url,
        type: "POST",
        data:
          $form.serialize() + "&action=rc_create_order&nonce=" + rcPublic.nonce,
        success: function (res) {
          if (res.success && res.data.pay_url) {
            window.location.href = res.data.pay_url;
          } else {
            $error.text(res.data || "Error al crear la orden.").show();
            $btn.show();
            $loader.hide();
          }
        },
        error: function () {
          $error.text("Error de conexión. Intenta de nuevo.").show();
          $btn.show();
          $loader.hide();
        },
      });
    } else {
      // Direct flow (no WooCommerce) — show error
      $error
        .text("WooCommerce no está disponible. Contacta al administrador.")
        .show();
      $btn.show();
      $loader.hide();
    }
  });
})(jQuery);
