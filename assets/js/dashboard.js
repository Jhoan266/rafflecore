(function ($) {
  "use strict";

  var charts = {};

  /* ── Updated color palette (matches new theme) ── */
  var chartColors = {
    blue: "rgba(59, 130, 246, ##)",
    green: "rgba(52, 211, 153, ##)",
    orange: "rgba(248, 113, 113, ##)",
    red: "rgba(220, 38, 38, ##)",
    purple: "rgba(167, 139, 250, ##)",
    teal: "rgba(45, 212, 191, ##)",
    pink: "rgba(244, 114, 182, ##)",
    yellow: "rgba(251, 191, 36, ##)",
    indigo: "rgba(99, 102, 241, ##)",
    cyan: "rgba(56, 189, 248, ##)",
  };

  var colorKeys = Object.keys(chartColors);

  function rgba(name, alpha) {
    return chartColors[name].replace("##", alpha);
  }

  function colorAt(i, alpha) {
    return rgba(colorKeys[i % colorKeys.length], alpha);
  }

  /* ── Currency config ── */
  var currencyMap = {
    COP: { symbol: "$", locale: "es-CO", decimals: 0 },
    USD: { symbol: "$", locale: "en-US", decimals: 2 },
    EUR: { symbol: "€", locale: "es-ES", decimals: 2 },
    MXN: { symbol: "$", locale: "es-MX", decimals: 2 },
    ARS: { symbol: "$", locale: "es-AR", decimals: 2 },
    BRL: { symbol: "R$", locale: "pt-BR", decimals: 2 },
    PEN: { symbol: "S/", locale: "es-PE", decimals: 2 },
    CLP: { symbol: "$", locale: "es-CL", decimals: 0 },
    VES: { symbol: "Bs.", locale: "es-VE", decimals: 2 },
  };

  var curCode =
    typeof rcDashboard !== "undefined" && rcDashboard.currency
      ? rcDashboard.currency
      : "COP";
  var curCfg = currencyMap[curCode] || currencyMap.COP;

  function formatMoney(n) {
    return (
      curCfg.symbol +
      " " +
      Number(n).toLocaleString(curCfg.locale, {
        minimumFractionDigits: curCfg.decimals,
        maximumFractionDigits: curCfg.decimals,
      })
    );
  }

  function formatMoneyCompact(n) {
    var abs = Math.abs(Number(n));
    var sign = Number(n) < 0 ? "-" : "";
    if (abs >= 1e9) return sign + curCfg.symbol + (abs / 1e9).toFixed(1) + "B";
    if (abs >= 1e6) return sign + curCfg.symbol + (abs / 1e6).toFixed(1) + "M";
    if (abs >= 1e3) return sign + curCfg.symbol + (abs / 1e3).toFixed(1) + "K";
    return formatMoney(n);
  }

  function truncate(str, len) {
    return str.length > len ? str.substring(0, len) + "…" : str;
  }

  /* ── Theme-aware Chart.js defaults ────────── */
  function isDarkTheme() {
    return !document.querySelector(".rc-theme-light");
  }

  function getGridColor() {
    return isDarkTheme() ? "rgba(255, 255, 255, .06)" : "rgba(0, 0, 0, .06)";
  }

  function getTextColor() {
    return isDarkTheme() ? "#9ca3b4" : "#64748b";
  }

  function applyChartDefaults() {
    Chart.defaults.color = getTextColor();
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyleWidth = 10;
    Chart.defaults.plugins.legend.labels.padding = 16;
    Chart.defaults.scale.grid = { color: getGridColor() };
    Chart.defaults.plugins.tooltip.backgroundColor = isDarkTheme()
      ? "rgba(15, 17, 23, .95)"
      : "rgba(15, 23, 42, .90)";
    Chart.defaults.plugins.tooltip.titleFont = {
      family: "'Inter', sans-serif",
      size: 13,
      weight: "600",
    };
    Chart.defaults.plugins.tooltip.bodyFont = {
      family: "'Inter', sans-serif",
      size: 12,
    };
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.displayColors = true;
    Chart.defaults.plugins.tooltip.boxPadding = 4;
  }

  applyChartDefaults();

  // Re-apply defaults when theme changes
  var themeObserver = new MutationObserver(function () {
    applyChartDefaults();
    // Redraw all charts
    Object.keys(charts).forEach(function (key) {
      if (charts[key]) {
        charts[key].options.scales &&
          Object.keys(charts[key].options.scales).forEach(function (axis) {
            if (charts[key].options.scales[axis].grid) {
              charts[key].options.scales[axis].grid.color = getGridColor();
            }
            if (charts[key].options.scales[axis].ticks) {
              charts[key].options.scales[axis].ticks.color = getTextColor();
            }
          });
        charts[key].update("none");
      }
    });
  });

  var wrapEl = document.querySelector(".rc-wrap");
  if (wrapEl) {
    themeObserver.observe(wrapEl, {
      attributes: true,
      attributeFilter: ["class"],
    });
  }

  function ajax(type, extra) {
    var raffleId = $("#rc-raffle-filter").val() || 0;
    var params = $.extend(
      {
        action: "rc_analytics_data",
        nonce: rcDashboard.nonce,
        type: type,
        raffle_id: raffleId,
      },
      extra || {},
    );
    return $.getJSON(rcDashboard.ajax_url, params);
  }

  // Refresh when filter changes
  $("#rc-raffle-filter").on("change", function () {
    $("#rc-refresh-dashboard").trigger("click");
  });

  /* ── KPIs ─────────────────────────────── */
  function loadOverview() {
    ajax("overview").done(function (res) {
      if (!res.success) return;
      var d = res.data;
      $("#kpi-revenue").text(formatMoneyCompact(d.total_revenue));
      $("#kpi-net-profit")
        .text(formatMoneyCompact(d.net_profit))
        .css("color", d.net_profit >= 0 ? "#34d399" : "#f87171");
      $("#kpi-tickets").text(
        d.total_tickets_sold.toLocaleString("es-CO")
      );
      $("#kpi-buyers").text(d.total_buyers.toLocaleString("es-CO"));
      $("#kpi-sell-rate").text(d.sell_rate + "%");

      // secondary
      $("#kpi-active-raffles span").text(d.active_raffles);
      $("#kpi-total-raffles span").text(d.total_raffles);
      $("#kpi-avg-price span").text(formatMoney(d.avg_ticket_price));
      $("#kpi-month-trend span:last").text(formatMoney(d.revenue_this_month));

      // month trend icon
      var icon = d.revenue_this_month >= d.revenue_last_month ? "📈" : "📉";
      $("#kpi-trend-icon").text(icon);
    });
  }


  /* ── Raffle Progress (doughnut) ────────── */
  function loadRaffleProgress() {
    var raffleId = parseInt($("#rc-raffle-filter").val()) || 0;
    if (raffleId === 0) return; // Skip if global view

    ajax("raffle_progress").done(function (res) {
      if (!res.success) return;
      var d = res.data;

      var labels = ["Boletos Vendidos", "Disponibles"];
      var values = [d.sold, d.available];
      var bgColors = [rgba("green", 0.85), rgba("indigo", 0.15)];
      var borderColors = [rgba("green", 1), "transparent"];

      if (charts.progress) charts.progress.destroy();
      
      var total = d.total || 1;
      var pct = ((d.sold / total) * 100).toFixed(1) + "%";
      
      // Custom plugin to draw text in center
      const centerTextPlugin = {
        id: 'centerText',
        beforeDraw: function(chart) {
          var width = chart.width,
              height = chart.height,
              ctx = chart.ctx;

          ctx.restore();
          var fontSize = (height / 114).toFixed(2);
          ctx.font = "bold " + fontSize + "em 'Inter', sans-serif";
          ctx.textBaseline = "middle";
          ctx.fillStyle = getTextColor();

          var text = pct,
              textX = Math.round((width - ctx.measureText(text).width) / 2),
              textY = height / 2.1;

          ctx.fillText(text, textX, textY);
          
          ctx.font = "normal " + (fontSize / 2.5).toFixed(2) + "em 'Inter', sans-serif";
          var subtext = d.sold.toLocaleString("es-CO") + " / " + d.total.toLocaleString("es-CO");
          var subX = Math.round((width - ctx.measureText(subtext).width) / 2);
          var subY = height / 1.7;
          ctx.fillText(subtext, subX, subY);
          
          ctx.save();
        }
      };

      charts.progress = new Chart($("#chart-raffle-progress")[0], {
        type: "doughnut",
        data: {
          labels: labels,
          datasets: [
            {
              data: values,
              backgroundColor: bgColors,
              borderColor: borderColors,
              borderWidth: 2,
              hoverOffset: 4,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "75%",
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                padding: 16,
                color: getTextColor(),
              },
            },
            tooltip: {
              callbacks: {
                label: function (ctx) { return " " + ctx.label + ": " + ctx.raw.toLocaleString("es-CO"); },
              },
            },
          },
        },
        plugins: [centerTextPlugin]
      });
    });
  }

  /* ── Main Trend (Sales & Growth) ──────── */
  function loadMainTrend(period) {
    period = period || "daily";
    ajax("sales_trend", { period: period }).done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) { return r.label; });
      var revenue = data.map(function (r) { return parseFloat(r.revenue); });
      var tkts = data.map(function (r) { return parseInt(r.tickets, 10); });
      
      // Calculate running cumulative for the current window
      var cumulative = [];
      var run_sum = 0;
      for (var i = 0; i < revenue.length; i++) {
         run_sum += revenue[i];
         cumulative.push(run_sum);
      }

      if (charts.trend) charts.trend.destroy();
      var ctx = $("#chart-main-trend")[0].getContext("2d");
      
      var gradient = ctx.createLinearGradient(0, 0, 0, 350);
      gradient.addColorStop(0, rgba("indigo", 0.15));
      gradient.addColorStop(1, rgba("indigo", 0.01));

      charts.trend = new Chart(ctx, {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              type: "line",
              label: rcDashboard.i18n.cumulative || "Acumulado",
              data: cumulative,
              borderColor: rgba("indigo", 1),
              backgroundColor: gradient,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: rgba("indigo", 1),
              pointBorderColor: isDarkTheme() ? "#1a1d2e" : "#fff",
              pointBorderWidth: 2,
              pointRadius: 4,
              pointHoverRadius: 6,
              yAxisID: "y1",
              order: 1
            },
            {
              type: "bar",
              label: "Ingresos del Período",
              data: revenue,
              backgroundColor: rgba("blue", 0.8),
              borderRadius: 4,
              yAxisID: "y",
              order: 2,
              _tickets: tkts,
            }
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: "index", intersect: false },
          plugins: {
            legend: { position: "bottom" },
            tooltip: {
              callbacks: {
                label: function (ctx) { 
                  var val = " " + ctx.dataset.label + ": " + formatMoney(ctx.raw);
                  if (ctx.dataset.type === "bar") {
                    val += " | " + ctx.dataset._tickets[ctx.dataIndex] + " boletos";
                  }
                  return val;
                },
              },
            },
          },
          scales: {
            y: {
              type: "linear",
              position: "left",
              beginAtZero: true,
              grid: { color: getGridColor() },
              ticks: { color: getTextColor(), callback: function (v) { return formatMoneyCompact(v); } },
            },
            y1: {
              type: "linear",
              position: "right",
              beginAtZero: true,
              grid: { drawOnChartArea: false },
              ticks: { color: getTextColor(), callback: function (v) { return formatMoneyCompact(v); } },
            },
            x: { grid: { display: false }, ticks: { color: getTextColor(), maxRotation: 45 } },
          },
        },
      });
    });
  }

  /* ── Revenue vs Prize (doughnut) ──────── */
  function loadRevenueVsPrize() {
    ajax("revenue_vs_prize").done(function (res) {
      if (!res.success) return;
      var d = res.data;

      var labels = [rcDashboard.i18n.profit, rcDashboard.i18n.prizes];
      var values = [d.net_profit, d.total_prize];
      var bgColors = [rgba("green", 0.85), rgba("yellow", 0.85)];
      var borderColors = [rgba("green", 1), rgba("yellow", 1)];

      if (d.deficit > 0) {
        labels.push(rcDashboard.i18n.deficit);
        values = [0, d.total_prize, d.deficit];
        bgColors.push(rgba("red", 0.85));
        borderColors.push(rgba("red", 1));
      }

      if (charts.revenueVsPrize) charts.revenueVsPrize.destroy();
      charts.revenueVsPrize = new Chart($("#chart-revenue-vs-prize")[0], {
        type: "doughnut",
        data: {
          labels: labels,
          datasets: [
            {
              data: values,
              backgroundColor: bgColors,
              borderColor: borderColors,
              borderWidth: 2,
              hoverOffset: 8,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "80%",
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                padding: 16,
                color: getTextColor(),
              },
            },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var total = ctx.dataset.data.reduce(function (a, b) {
                    return a + b;
                  }, 0);
                  var pct =
                    total > 0
                      ? ((ctx.raw / total) * 100).toFixed(1) + "%"
                      : "0%";
                  return (
                    " " +
                    ctx.label +
                    ": " +
                    formatMoney(ctx.raw) +
                    " (" +
                    pct +
                    ")"
                  );
                },
              },
            },
          },
        },
      });
    });
  }

  /* ── Package Popularity (horizontal bar) ─ */
  function loadPackagePopularity() {
    ajax("package_popularity").done(function (res) {
      if (!res.success) return;
      var data = res.data;

      if (!data.length) return;

      var labels = data.map(function (r) {
        return r.package_size + " " + rcDashboard.i18n.packages;
      });
      var values = data.map(function (r) {
        return parseInt(r.purchases, 10);
      });
      var bg = data.map(function (_, i) {
        return colorAt(i, 0.75);
      });
      var border = data.map(function (_, i) {
        return colorAt(i, 1);
      });

      if (charts.packages) charts.packages.destroy();
      charts.packages = new Chart($("#chart-package-popularity")[0], {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: rcDashboard.i18n.purchases,
              data: values,
              backgroundColor: bg,
              borderColor: border,
              borderWidth: 2,
              borderRadius: 8,
            },
          ],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  return (
                    " " +
                    ctx.raw +
                    " " +
                    rcDashboard.i18n.purchases.toLowerCase()
                  );
                },
              },
            },
          },
          scales: {
            x: {
              beginAtZero: true,
              grid: { color: getGridColor() },
              ticks: { color: getTextColor() },
            },
            y: {
              grid: { display: false },
              ticks: { color: getTextColor() },
            },
          },
        },
      });
    });
  }


  /* ── Top Buyers ───────────────────────── */
  function loadTopBuyers() {
    ajax("top_buyers").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var $tbody = $("#table-top-buyers tbody");
      if (!data.length) {
        $tbody.html(
          '<tr><td colspan="5" class="rc-empty">' +
            rcDashboard.i18n.noData +
            "</td></tr>",
        );
        return;
      }
      var rows = "";
      $.each(data, function (i, b) {
        rows +=
          "<tr>" +
          "<td><strong>" +
          (i + 1) +
          "</strong></td>" +
          "<td>" +
          escHtml(b.buyer_name) +
          '<br><small style="color:var(--rc-text-muted);">' +
          escHtml(b.buyer_email) +
          "</small></td>" +
          "<td>" +
          b.purchases +
          "</td>" +
          "<td>" +
          parseInt(b.total_tickets, 10).toLocaleString("es-CO") +
          "</td>" +
          "<td><strong>" +
          formatMoney(b.total_spent) +
          "</strong></td>" +
          "</tr>";
      });
      $tbody.html(rows);
    });
  }

  /* ── Recent Transactions ──────────────── */
  var txnSearchTimer = null;

  function loadRecentTransactions(page) {
    page = page || 1;
    var search = ($("#rc-txn-search").val() || "").trim();
    var status = $("#rc-txn-status").val() || "";

    ajax("recent_transactions", {
      search: search,
      status: status,
      txn_page: page,
    }).done(function (res) {
      if (!res.success) return;
      var result = res.data;
      var data = result.rows || [];
      var $tbody = $("#table-recent-txns tbody");

      if (!data.length) {
        $tbody.html(
          '<tr><td colspan="5" class="rc-empty">' +
            rcDashboard.i18n.noData +
            "</td></tr>",
        );
        $("#rc-txn-pagination").empty();
        return;
      }

      var rows = "";
      $.each(data, function (_, t) {
        var statusMap = {
          completed: { cls: "completed", label: rcDashboard.i18n.completed },
          processing: { cls: "active", label: "Procesando" },
          "on-hold": { cls: "pending", label: "En espera" },
          cancelled: { cls: "cancelled", label: "Cancelado" },
          failed: { cls: "cancelled", label: "Fallido" },
        };
        var st = statusMap[t.status] || {
          cls: "pending",
          label: rcDashboard.i18n.pending,
        };

        rows +=
          "<tr>" +
          "<td>" +
          escHtml(truncate(t.raffle_title, 22)) +
          "</td>" +
          "<td>" +
          escHtml(t.buyer_name) +
          "</td>" +
          "<td>" +
          t.quantity +
          "</td>" +
          "<td><strong>" +
          formatMoney(t.amount_paid) +
          "</strong></td>" +
          '<td><span class="rc-badge rc-badge-' +
          st.cls +
          '">' +
          st.label +
          "</span></td>" +
          "</tr>";
      });
      $tbody.html(rows);

      // Pagination
      var $pag = $("#rc-txn-pagination");
      $pag.empty();
      if (result.pages > 1) {
        if (result.page > 1) {
          $pag.append(
            '<button class="rc-btn rc-btn-secondary rc-btn-sm rc-txn-page" data-page="' +
              (result.page - 1) +
              '">◀</button>',
          );
        }
        $pag.append(
          '<span style="font-size:12px;color:var(--rc-text-muted);">' +
            result.page +
            " / " +
            result.pages +
            " (" +
            result.total +
            ")</span>",
        );
        if (result.page < result.pages) {
          $pag.append(
            '<button class="rc-btn rc-btn-secondary rc-btn-sm rc-txn-page" data-page="' +
              (result.page + 1) +
              '">▶</button>',
          );
        }
      }
    });
  }

  // Search debounce
  $(document).on("input", "#rc-txn-search", function () {
    clearTimeout(txnSearchTimer);
    txnSearchTimer = setTimeout(function () {
      loadRecentTransactions(1);
    }, 400);
  });

  // Status filter
  $(document).on("change", "#rc-txn-status", function () {
    loadRecentTransactions(1);
  });

  // Pagination clicks
  $(document).on("click", ".rc-txn-page", function () {
    loadRecentTransactions($(this).data("page"));
  });

  // Export transactions as Excel (CSV with BOM)
  $(document).on("click", "#rc-export-txns", function (e) {
    e.preventDefault();
    var url =
      rcDashboard.ajax_url +
      "?" +
      $.param({
        action: "rc_export_transactions",
        nonce: rcDashboard.nonce,
      });
    window.location.href = url;
  });

  function escHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  /* ── Init ─────────────────────────────── */
  function loadAll() {
    loadOverview();
    loadMainTrend($(".rc-chip-active").data("period") || "daily");
    loadTopBuyers();
    loadRecentTransactions();
    loadRevenueVsPrize();
    loadPackagePopularity();
    
    // Switch dynamic panels based on global vs single selection
    var raffleId = parseInt($("#rc-raffle-filter").val()) || 0;
    if (raffleId > 0) {
        $(".rc-comparative-panel").hide();
        $(".rc-single-panel").fadeIn();
        loadRaffleProgress();
    } else {
        $(".rc-single-panel").hide();
        $(".rc-comparative-panel").fadeIn();
    }
  }

  $(function () {
    loadAll();

    // Refresh
    $("#rc-refresh-dashboard").on("click", function () {
      $(this).text("⏳ " + rcDashboard.i18n.loading);
      loadAll();
      var $btn = $(this);
      setTimeout(function () {
        $btn.text("🔄 " + rcDashboard.i18n.refresh);
      }, 1200);
    });

    // Period selector
    $(document).on("click", ".rc-chip", function () {
      $(".rc-chip").removeClass("rc-chip-active");
      $(this).addClass("rc-chip-active");
      loadMainTrend($(this).data("period"));
    });
  });
})(jQuery);
