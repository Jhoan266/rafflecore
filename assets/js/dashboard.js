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

  function formatMoney(n) {
    return (
      "$" +
      Number(n).toLocaleString("es-CO", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      })
    );
  }

  function truncate(str, len) {
    return str.length > len ? str.substring(0, len) + "…" : str;
  }

  /* ── Theme-aware Chart.js defaults ────────── */
  function isDarkTheme() {
    return !document.querySelector(".rc-theme-light");
  }

  function getGridColor() {
    return isDarkTheme()
      ? "rgba(255, 255, 255, .06)"
      : "rgba(0, 0, 0, .06)";
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
      { action: "rc_analytics_data", nonce: rcDashboard.nonce, type: type, raffle_id: raffleId },
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
      $("#kpi-revenue").text(formatMoney(d.total_revenue));
      $("#kpi-net-profit")
        .text(formatMoney(d.net_profit))
        .css("color", d.net_profit >= 0 ? "#34d399" : "#f87171");
      $("#kpi-tickets").text(
        d.total_tickets_sold.toLocaleString("es-CO") +
          " / " +
          d.total_tickets_available.toLocaleString("es-CO"),
      );
      $("#kpi-buyers").text(d.total_buyers.toLocaleString("es-CO"));
      $("#kpi-sell-rate").text(d.sell_rate + "%");

      // secondary
      $("#kpi-active-raffles span").text(d.active_raffles);
      $("#kpi-total-raffles span").text(d.total_raffles);
      $("#kpi-avg-price span").text(
        Number(d.avg_ticket_price).toLocaleString("es-CO", {
          minimumFractionDigits: 0,
        }),
      );
      $("#kpi-month-trend span:last").text(
        Number(d.revenue_this_month).toLocaleString("es-CO", {
          minimumFractionDigits: 0,
        }),
      );

      // month trend icon
      var icon = d.revenue_this_month >= d.revenue_last_month ? "📈" : "📉";
      $("#kpi-trend-icon").text(icon);
    });
  }

  /* ── Revenue by Raffle ────────────────── */
  function loadRevenueByRaffle() {
    ajax("revenue_by_raffle").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) {
        return truncate(r.title, 18);
      });
      var values = data.map(function (r) {
        return parseFloat(r.revenue);
      });
      var bg = data.map(function (_, i) {
        return colorAt(i, 0.75);
      });
      var border = data.map(function (_, i) {
        return colorAt(i, 1);
      });

      if (charts.revenue) charts.revenue.destroy();
      charts.revenue = new Chart($("#chart-revenue-raffle")[0], {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: rcDashboard.i18n.revenue,
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
                  return " " + formatMoney(ctx.raw);
                },
              },
            },
          },
          scales: {
            x: {
              beginAtZero: true,
              grid: { color: getGridColor() },
              ticks: {
                color: getTextColor(),
                callback: function (v) {
                  return formatMoney(v);
                },
              },
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

  /* ── Tickets by Raffle ────────────────── */
  function loadTicketsByRaffle() {
    ajax("tickets_by_raffle").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) {
        return truncate(r.title, 18);
      });
      var sold = data.map(function (r) {
        return parseInt(r.sold_tickets, 10);
      });
      var remaining = data.map(function (r) {
        return parseInt(r.total_tickets, 10) - parseInt(r.sold_tickets, 10);
      });

      if (charts.tickets) charts.tickets.destroy();
      charts.tickets = new Chart($("#chart-tickets-raffle")[0], {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: rcDashboard.i18n.sold,
              data: sold,
              backgroundColor: rgba("blue", 0.8),
              borderRadius: 6,
            },
            {
              label: rcDashboard.i18n.available,
              data: remaining,
              backgroundColor: rgba("blue", 0.15),
              borderRadius: 6,
            },
          ],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: "bottom" } },
          scales: {
            x: {
              stacked: true,
              beginAtZero: true,
              grid: { color: getGridColor() },
              ticks: { color: getTextColor() },
            },
            y: {
              stacked: true,
              grid: { display: false },
              ticks: { color: getTextColor() },
            },
          },
        },
      });
    });
  }

  /* ── Net Profit ───────────────────────── */
  function loadNetProfit() {
    ajax("net_profit").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) {
        return truncate(r.title, 18);
      });
      var profits = data.map(function (r) {
        return parseFloat(r.net_profit);
      });
      var bg = profits.map(function (v) {
        return v >= 0 ? rgba("green", 0.75) : rgba("red", 0.75);
      });
      var border = profits.map(function (v) {
        return v >= 0 ? rgba("green", 1) : rgba("red", 1);
      });

      if (charts.profit) charts.profit.destroy();
      charts.profit = new Chart($("#chart-net-profit")[0], {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: rcDashboard.i18n.netProfit,
              data: profits,
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
                  return " " + formatMoney(ctx.raw);
                },
              },
            },
          },
          scales: {
            x: {
              beginAtZero: true,
              grid: { color: getGridColor() },
              ticks: {
                color: getTextColor(),
                callback: function (v) {
                  return formatMoney(v);
                },
              },
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

  /* ── Sales Trend ──────────────────────── */
  function loadSalesTrend(period) {
    period = period || "daily";
    ajax("sales_trend", { period: period }).done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var labels = data.map(function (r) {
        return r.label;
      });
      var revenue = data.map(function (r) {
        return parseFloat(r.revenue);
      });
      var tickets = data.map(function (r) {
        return parseInt(r.tickets, 10);
      });

      if (charts.trend) charts.trend.destroy();

      var ctx = $("#chart-sales-trend")[0].getContext("2d");
      var gradient = ctx.createLinearGradient(0, 0, 0, 280);
      gradient.addColorStop(0, rgba("blue", 0.2));
      gradient.addColorStop(1, rgba("blue", 0.01));

      charts.trend = new Chart(ctx, {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              label: rcDashboard.i18n.revenue,
              data: revenue,
              borderColor: rgba("blue", 1),
              backgroundColor: gradient,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: rgba("blue", 1),
              pointBorderColor: isDarkTheme() ? "#1a1d2e" : "#fff",
              pointBorderWidth: 2,
              pointRadius: 5,
              pointHoverRadius: 7,
              yAxisID: "y",
              _tickets: tickets, // Attach meta data for tooltip
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: "index", intersect: false },
          plugins: { 
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var dataIndex = ctx.dataIndex;
                  var tkts = ctx.dataset._tickets[dataIndex];
                  return " " + formatMoney(ctx.raw) + " | " + tkts + " " + rcDashboard.i18n.tickets.toLowerCase();
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
              ticks: {
                color: getTextColor(),
                callback: function (v) {
                  return formatMoney(v);
                },
              },
            },
            x: {
              grid: { display: false },
              ticks: { color: getTextColor() },
            },
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
      charts.revenueVsPrize = new Chart(
        $("#chart-revenue-vs-prize")[0],
        {
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
                    return " " + ctx.label + ": " + formatMoney(ctx.raw) + " (" + pct + ")";
                  },
                },
              },
            },
          },
        },
      );
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

  /* ── Cumulative Revenue (area chart) ──── */
  function loadCumulativeRevenue() {
    ajax("cumulative_revenue").done(function (res) {
      if (!res.success) return;
      var data = res.data;

      if (!data.length) return;

      var labels = data.map(function (r) {
        return r.date_label;
      });
      var cumulative = data.map(function (r) {
        return parseFloat(r.cumulative);
      });
      var daily = data.map(function (r) {
        return parseFloat(r.daily_revenue);
      });

      if (charts.cumulative) charts.cumulative.destroy();

      var ctx = $("#chart-cumulative-revenue")[0].getContext("2d");
      var gradient = ctx.createLinearGradient(0, 0, 0, 300);
      gradient.addColorStop(0, rgba("indigo", 0.25));
      gradient.addColorStop(1, rgba("indigo", 0.02));

      var gradient2 = ctx.createLinearGradient(0, 0, 0, 300);
      gradient2.addColorStop(0, rgba("teal", 0.15));
      gradient2.addColorStop(1, rgba("teal", 0.01));

      charts.cumulative = new Chart(ctx, {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              label: rcDashboard.i18n.cumulative,
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
              borderWidth: 2.5,
              yAxisID: "y",
            },
            {
              label: rcDashboard.i18n.dailyRevenue,
              data: daily,
              borderColor: rgba("teal", 1),
              backgroundColor: gradient2,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: rgba("teal", 1),
              pointBorderColor: isDarkTheme() ? "#1a1d2e" : "#fff",
              pointBorderWidth: 2,
              pointRadius: 3,
              pointHoverRadius: 5,
              borderWidth: 2,
              borderDash: [4, 3],
              yAxisID: "y1",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: "index", intersect: false },
          plugins: { legend: { position: "bottom" } },
          scales: {
            y: {
              type: "linear",
              position: "left",
              beginAtZero: true,
              grid: { color: getGridColor() },
              ticks: {
                color: getTextColor(),
                callback: function (v) {
                  return formatMoney(v);
                },
              },
            },
            y1: {
              type: "linear",
              position: "right",
              beginAtZero: true,
              grid: { drawOnChartArea: false },
              ticks: {
                color: getTextColor(),
                callback: function (v) {
                  return formatMoney(v);
                },
              },
            },
            x: {
              grid: { display: false },
              ticks: { color: getTextColor(), maxRotation: 45 },
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
  function loadRecentTransactions() {
    ajax("recent_transactions").done(function (res) {
      if (!res.success) return;
      var data = res.data;
      var $tbody = $("#table-recent-txns tbody");
      if (!data.length) {
        $tbody.html(
          '<tr><td colspan="5" class="rc-empty">' +
            rcDashboard.i18n.noData +
            "</td></tr>",
        );
        return;
      }
      var rows = "";
      $.each(data, function (_, t) {
        var statusClass = t.status === "completed" ? "completed" : "pending";
        var statusLabel =
          t.status === "completed"
            ? rcDashboard.i18n.completed
            : rcDashboard.i18n.pending;
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
          statusClass +
          '">' +
          statusLabel +
          "</span></td>" +
          "</tr>";
      });
      $tbody.html(rows);
    });
  }

  function escHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  /* ── Init ─────────────────────────────── */
  function loadAll() {
    loadOverview();
    loadRevenueByRaffle();
    loadTicketsByRaffle();
    loadNetProfit();
    loadSalesTrend("daily");
    loadTopBuyers();
    loadRecentTransactions();
    loadRevenueVsPrize();
    loadPackagePopularity();
    loadCumulativeRevenue();
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
      loadSalesTrend($(this).data("period"));
    });
  });
})(jQuery);
