/**
 * RaffleCore — Admin JavaScript
 */
(function ($) {
  "use strict";

  // ─── Theme Toggle ───────────────────────
  function rcInitTheme() {
    var wrap = document.querySelector(".rc-wrap");
    if (!wrap) return;

    var saved = localStorage.getItem("rc-theme") || "dark";
    wrap.classList.remove("rc-theme-dark", "rc-theme-light");
    wrap.classList.add("rc-theme-" + saved);

    // Inject toggle button into header
    var header = wrap.querySelector(".rc-dash-header, .rc-title");
    if (header && !header.querySelector(".rc-theme-toggle")) {
      var toggle = document.createElement("div");
      toggle.className = "rc-theme-toggle";
      toggle.setAttribute("role", "switch");
      toggle.setAttribute("aria-label", "Cambiar tema");
      toggle.setAttribute("tabindex", "0");
      toggle.innerHTML =
        '<div class="rc-theme-toggle-track">' +
        '<div class="rc-theme-toggle-thumb">' +
        (saved === "dark" ? "🌙" : "☀️") +
        "</div>" +
        "</div>" +
        '<span class="rc-theme-toggle-label">' +
        (saved === "dark" ? "Oscuro" : "Claro") +
        "</span>";

      toggle.addEventListener("click", function () {
        var isDark = wrap.classList.contains("rc-theme-dark");
        var next = isDark ? "light" : "dark";
        wrap.classList.remove("rc-theme-dark", "rc-theme-light");
        wrap.classList.add("rc-theme-" + next);
        localStorage.setItem("rc-theme", next);

        var thumb = toggle.querySelector(".rc-theme-toggle-thumb");
        var label = toggle.querySelector(".rc-theme-toggle-label");
        thumb.textContent = next === "dark" ? "🌙" : "☀️";
        label.textContent = next === "dark" ? "Oscuro" : "Claro";
      });

      toggle.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          toggle.click();
        }
      });

      header.style.display = "flex";
      header.style.alignItems = "center";
      header.appendChild(toggle);
    }
  }

  $(document).ready(rcInitTheme);

  // ─── Media Uploader ─────────────────────
  $(document).on("click", "#rc-upload-btn", function (e) {
    e.preventDefault();
    var frame = wp.media({
      title: rcAdmin.i18n.selectImage,
      button: { text: rcAdmin.i18n.useImage },
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
    $("#rc-image-preview").html("<span>" + rcAdmin.i18n.noImage + "</span>");
    $(this).hide();
  });

  // ─── Font Family Toggle ─────────────────
  $(document).on("change", "#rc_font_family", function () {
    if ($(this).val() === "custom") {
      $("#rc-custom-font-group").slideDown(200);
    } else {
      $("#rc-custom-font-group").slideUp(200);
    }
  });

  // ─── Font File Uploader ─────────────────
  $(document).on("click", "#rc-upload-font-btn", function (e) {
    e.preventDefault();
    var frame = wp.media({
      title: rcAdmin.i18n.selectFont,
      button: { text: rcAdmin.i18n.useFont },
      multiple: false,
    });
    frame.on("select", function () {
      var attachment = frame.state().get("selection").first().toJSON();
      var url = attachment.url;
      var name = url.split("/").pop();
      $("#rc_custom_font_url").val(url);
      $("#rc-font-preview").html(
        '<span class="rc-font-filename">✅ ' + name + "</span>",
      );
      $("#rc-remove-font-btn").show();
    });
    frame.open();
  });

  $(document).on("click", "#rc-remove-font-btn", function (e) {
    e.preventDefault();
    $("#rc_custom_font_url").val("");
    $("#rc-font-preview").html("<span>" + rcAdmin.i18n.noFont + "</span>");
    $(this).hide();
  });

  // ─── Draw Button ────────────────────────
  $(document).on("click", "#rc-draw-btn", function (e) {
    e.preventDefault();
    var btn = $(this);
    var raffleId = btn.data("raffle-id");

    if (!confirm(rcAdmin.i18n.confirmDraw)) return;

    btn.prop("disabled", true).text("🎰 " + rcAdmin.i18n.drawing);

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
          $(".rc-external-draw-panel").slideUp(400);
        } else {
          alert("Error: " + (res.data || "Error desconocido"));
          btn.prop("disabled", false).text("🎰 " + rcAdmin.i18n.drawButton);
        }
      },
      error: function () {
        alert(rcAdmin.i18n.connectionError);
        btn.prop("disabled", false).text("🎰 " + rcAdmin.i18n.drawButton);
      },
    });
  });

  // ─── Message Templates ──────────────────
  var rcTemplates = {
    congratulations:
      "¡Felicidades {nombre}! 🎉\n\nNos complace informarte que eres el ganador de la rifa {rifa} con el boleto {boleto}.\n\nEl premio tiene un valor de {premio}.\n\nNos pondremos en contacto contigo pronto para coordinar la entrega.\n\n¡Gracias por participar!\n{sitio}",
    formal:
      "Estimado/a {nombre},\n\nPor medio del presente, le informamos que su boleto {boleto} ha resultado ganador en el sorteo de {rifa}.\n\nEl premio correspondiente tiene un valor de {premio}.\n\nPor favor, responda a este correo para coordinar la entrega de su premio.\n\nAtentamente,\n{sitio}",
    claim:
      "¡Hola {nombre}! 🏆\n\nTu boleto {boleto} ha sido el ganador de {rifa}.\n\nPara reclamar tu premio ({premio}), por favor sigue estos pasos:\n\n1. Responde a este correo con tu nombre completo\n2. Adjunta una foto de tu documento de identidad\n3. Indica tu dirección de entrega o si prefieres recogerlo\n\nTienes 30 días para reclamar tu premio.\n\n¡Felicidades!\n{sitio}",
    short:
      "¡{nombre}, ganaste! 🎉\n\nTu boleto {boleto} es el ganador de {rifa}. Premio: {premio}.\n\nResponde a este correo para reclamar tu premio.\n\n— {sitio}",
  };

  $(document).on("click", ".rc-template-btn", function (e) {
    e.preventDefault();
    var key = $(this).data("template");
    if (rcTemplates[key]) {
      $("#rc-winner-message").val(rcTemplates[key]).focus();
      $(".rc-template-btn").removeClass("active");
      $(this).addClass("active");
    }
  });

  // ─── External Draw Button ──────────────
  $(document).on("click", "#rc-external-draw-btn", function (e) {
    e.preventDefault();
    var btn = $(this);
    var raffleId = btn.data("raffle-id");
    var ticketNumber = parseInt($("#rc-external-number").val(), 10);
    var message = $("#rc-winner-message").val().trim();

    if (!ticketNumber || ticketNumber < 1) {
      alert(rcAdmin.i18n.enterNumber || "Ingresa un número de boleto válido.");
      $("#rc-external-number").focus();
      return;
    }

    if (!message) {
      alert(
        rcAdmin.i18n.enterMessage ||
          "Escribe un mensaje para el ganador o selecciona una plantilla.",
      );
      $("#rc-winner-message").focus();
      return;
    }

    if (
      !confirm(
        rcAdmin.i18n.confirmExternal ||
          "¿Confirmas que el número ganador es " +
            ticketNumber +
            "? Se notificará al dueño del boleto.",
      )
    )
      return;

    btn
      .prop("disabled", true)
      .text("🔍 " + (rcAdmin.i18n.searching || "Buscando..."));

    $.ajax({
      url: rcAdmin.ajax_url,
      type: "POST",
      data: {
        action: "rc_external_draw",
        raffle_id: raffleId,
        ticket_number: ticketNumber,
        winner_message: message,
        nonce: rcAdmin.nonce,
      },
      success: function (res) {
        if (res.success) {
          var d = res.data;
          $("#rc-draw-ticket").text("Boleto #" + d.ticket_number);
          $("#rc-draw-buyer").text(d.buyer_name);
          $("#rc-draw-email").text(d.buyer_email);
          if (d.notified) {
            $("#rc-draw-notified").show();
          }
          $("#rc-draw-result").slideDown(400);
          $(".rc-external-draw-panel").slideUp(400);
          $("#rc-draw-btn").hide();
        } else {
          alert(
            "Error: " + (res.data.message || res.data || "Error desconocido"),
          );
          btn
            .prop("disabled", false)
            .text(
              "🎱 " +
                (rcAdmin.i18n.searchNotify || "Buscar y Notificar Ganador"),
            );
        }
      },
      error: function () {
        alert(rcAdmin.i18n.connectionError);
        btn
          .prop("disabled", false)
          .text(
            "🎱 " + (rcAdmin.i18n.searchNotify || "Buscar y Notificar Ganador"),
          );
      },
    });
  });
})(jQuery);

// ─── Export Function (global) ─────────────
function rcExport(type) {
  var data = {
    action: "rc_export_" + type,
    nonce: rcAdmin.nonce,
  };
  var url = rcAdmin.ajax_url + "?" + jQuery.param(data);
  window.location.href = url;
}
