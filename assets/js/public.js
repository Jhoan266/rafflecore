/**
 * RaffleCore — Public Frontend JavaScript v3
 */
(function ($) {
  "use strict";

  // ─── AJAX Hydration — datos frescos post-caché ──
  var $raffle = $(".rc-raffle");
  if ($raffle.length && typeof rcPublic !== "undefined") {
    var raffleId = $raffle.data("raffle-id");
    if (raffleId) {
      $.ajax({
        url: rcPublic.ajax_url,
        type: "GET",
        data: { action: "rc_hydrate_raffle", raffle_id: raffleId },
        success: function (res) {
          if (res.success && res.data) {
            var d = res.data;
            // Actualizar barra de progreso
            $(".rc-progress-big-percent").text(d.progress + "%");
            $(".rc-progress-bar-fill").css("width", d.progress + "%");
            // Actualizar detalles numéricos
            var $details = $(".rc-progress-detail-number");
            if ($details.length >= 3) {
              $details
                .eq(0)
                .text(new Intl.NumberFormat().format(d.total_tickets));
              $details.eq(1).text(new Intl.NumberFormat().format(d.available));
              $details
                .eq(2)
                .text(
                  rcPublic.currency +
                    new Intl.NumberFormat().format(d.ticket_price),
                );
            }
            // Actualizar máximo del selector de cantidad
            $(".rc-qty-input").attr("max", d.available);
          }
        },
      });
    }
  }

  // ─── Countdown Timer ────────────────────
  var $cd = $("#rc-countdown");
  if ($cd.length) {
    var drawDate = new Date($cd.data("draw-date")).getTime();
    var expired = false;

    function updateCountdown() {
      var now = Date.now();
      var diff = drawDate - now;

      if (diff <= 0 && !expired) {
        expired = true;
        $cd.hide();
        $("#rc-countdown-expired").show();
        return;
      }
      if (expired) return;

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

  // ─── Accessibility: Focus Trap ───────
  var focusableSelector =
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  var previousFocus = null;

  function trapFocus($modal) {
    $modal.on("keydown.rcTrap", function (e) {
      if (e.key !== "Tab") return;
      var focusable = $modal.find(focusableSelector).filter(":visible");
      if (!focusable.length) return;
      var first = focusable.first()[0];
      var last = focusable.last()[0];
      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });
  }

  function releaseTrap($modal) {
    $modal.off("keydown.rcTrap");
  }

  // ─── Modal Management ───────────────────
  function openPurchaseModal(qty, price) {
    var formatted = rcPublic.currency + new Intl.NumberFormat().format(price);
    $("#rc-modal-summary").html(
      "<strong>" +
        qty +
        "</strong> " +
        rcPublic.i18n.ticketsFor +
        " <strong>" +
        formatted +
        "</strong>",
    );
    $("#rc-form-qty").val(qty);
    $("#rc-form-price").val(price);
    $("#rc-purchase-loading").hide();
    $("#rc-submit-purchase").show().prop("disabled", false);
    previousFocus = document.activeElement;
    var $modal = $("#rc-purchase-modal");
    $modal.fadeIn(250, function () {
      $modal.find(".rc-modal-close").focus();
      trapFocus($modal);
    });
  }

  function closeModal($modal) {
    releaseTrap($modal);
    $modal.fadeOut(200, function () {
      if (previousFocus) {
        previousFocus.focus();
        previousFocus = null;
      }
    });
  }

  $(document).on("click", ".rc-modal-close", function () {
    var $modal = $(this).closest(".rc-modal");
    closeModal($modal);
    // Reload if closing confirmation
    if ($modal.attr("id") === "rc-confirm-modal") {
      location.reload();
    }
  });
  $(document).on("click", ".rc-modal", function (e) {
    if (e.target === this) {
      var $modal = $(this);
      closeModal($modal);
      if ($modal.attr("id") === "rc-confirm-modal") {
        location.reload();
      }
    }
  });

  // Escape key closes modals
  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      $(".rc-modal:visible").each(function () {
        var $modal = $(this);
        closeModal($modal);
        if ($modal.attr("id") === "rc-confirm-modal") {
          location.reload();
        }
      });
    }
  });

  // ─── Purchase Form Submit ───────────────
  $(document).on("submit", "#rc-purchase-form", function (e) {
    e.preventDefault();

    var $form = $(this);
    var $btn = $("#rc-submit-purchase");
    var $loader = $("#rc-purchase-loading");

    // Frontend validation
    var name = $form.find('[name="buyer_name"]').val().trim();
    var email = $form.find('[name="buyer_email"]').val().trim();
    var phone = $form.find('[name="buyer_phone"]').val().trim();
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (name.length < 2) {
      alert(rcPublic.i18n.nameRequired);
      return;
    }
    if (!emailRegex.test(email)) {
      alert(rcPublic.i18n.emailInvalid);
      return;
    }
    if (phone.length > 0 && phone.length < 7) {
      alert(rcPublic.i18n.phoneInvalid);
      return;
    }

    $btn.hide();
    $loader.show();

    if (rcPublic.wc_enabled) {
      handleWooCommercePurchase($form, $btn, $loader);
    } else {
      handleDirectPurchase($form, $btn, $loader);
    }
  });

  function handleWooCommercePurchase($form, $btn, $loader) {
    // Include coupon code if present
    var couponCode = $("#rc-coupon-code").val() || "";
    var extraData = couponCode
      ? "&coupon_code=" + encodeURIComponent(couponCode)
      : "";

    $.ajax({
      url: rcPublic.ajax_url,
      type: "POST",
      data:
        $form.serialize() +
        "&action=rc_create_order&nonce=" +
        rcPublic.nonce +
        extraData,
      success: function (res) {
        if (res.success && res.data.checkout_url) {
          window.location.href = res.data.checkout_url;
        } else {
          alert((res.data && res.data.message) || rcPublic.i18n.orderError);
          $btn.show();
          $loader.hide();
        }
      },
      error: function () {
        alert(rcPublic.i18n.connectionError);
        $btn.show();
        $loader.hide();
      },
    });
  }

  function handleDirectPurchase($form, $btn, $loader) {
    $.ajax({
      url: rcPublic.ajax_url,
      type: "POST",
      data:
        $form.serialize() +
        "&action=rc_direct_purchase&nonce=" +
        rcPublic.nonce,
      success: function (res) {
        $loader.hide();
        if (res.success) {
          $("#rc-purchase-modal").hide();
          showConfirmation(res.data.tickets);
        } else {
          alert((res.data && res.data.message) || rcPublic.i18n.purchaseError);
          $btn.show();
        }
      },
      error: function () {
        alert(rcPublic.i18n.connectionError);
        $btn.show();
        $loader.hide();
      },
    });
  }

  function showConfirmation(tickets) {
    var $container = $("#rc-confirm-tickets");
    $container.empty();
    if (tickets && tickets.length) {
      tickets.forEach(function (t) {
        $container.append('<span class="rc-confirm-ticket">#' + t + "</span>");
      });
    }
    var $modal = $("#rc-confirm-modal");
    previousFocus = document.activeElement;
    $modal.fadeIn(250, function () {
      $modal.find(".rc-modal-close").focus();
      trapFocus($modal);
    });
  }

  // ─── Coupon Code Validation ────────────
  $(document).on("click", "#rc-apply-coupon", function (e) {
    e.preventDefault();
    var code = $("#rc-coupon-code").val().trim();
    var raffleId =
      $("#rc-form-raffle-id").val() || $('[name="raffle_id"]').val();
    var qty = $("#rc-form-qty").val();

    if (!code) return;

    var $status = $("#rc-coupon-status");
    $status.html(rcPublic.i18n.validating).css("color", "#999");

    $.ajax({
      url: rcPublic.ajax_url,
      type: "POST",
      data: {
        action: "rc_validate_coupon",
        nonce: rcPublic.nonce,
        coupon_code: code,
        raffle_id: raffleId,
        quantity: qty,
      },
      success: function (res) {
        if (res.success) {
          $status.html("✅ " + res.data.message).css("color", "#28a745");
        } else {
          $status
            .html("❌ " + (res.data.message || res.data))
            .css("color", "#dc3545");
        }
      },
      error: function () {
        $status.html(rcPublic.i18n.connectionErr).css("color", "#dc3545");
      },
    });
  });
})(jQuery);
