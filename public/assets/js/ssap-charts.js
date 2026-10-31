/* SAP: grafik Chart.js untuk dashboard hazard, dashboard inspeksi, akuntabilitas, dan laporan.
   Data datang dari server (SafetyStats / InspectionStats ::charts) sebagai JSON; setiap grafik hanya dibuat bila kanvasnya ada di halaman. */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;

    const MUTED = '#6b7280', GRID = '#eef2f7';
    const BRAND = '#203868', ACCENT = '#28a8e0', GOOD = '#16a34a';

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = MUTED;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = '#111827';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.animation.duration = 500;

    // Pesan "belum ada data" bila semua nilai nol.
    Chart.register({
        id: 'noData',
        afterDraw(chart) {
            const any = (chart.data.datasets || []).some((d) => (d.data || []).some((v) => v !== null && Number(v) > 0));
            if (any || !chart.chartArea) return;
            const { ctx, chartArea: a } = chart;
            ctx.save();
            ctx.fillStyle = MUTED; ctx.textAlign = 'center'; ctx.font = "13px 'Inter', sans-serif";
            ctx.fillText('Belum ada data pada periode ini', (a.left + a.right) / 2, (a.top + a.bottom) / 2);
            ctx.restore();
        },
    });

    const el = (id) => document.getElementById(id);
    const fmt = (v) => (v === null || v === undefined ? '-' : Number(v).toLocaleString('id-ID'));
    const short = (t, n) => { t = String(t); return t.length > n ? t.slice(0, n - 1) + '…' : t; };
    const narrow = () => window.innerWidth < 576;

    const axisY = (extra) => Object.assign({ beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { precision: 0 } }, extra || {});
    const axisX = (extra) => Object.assign({ grid: { display: false }, border: { color: GRID } }, extra || {});
    const pctAxis = () => ({ position: 'right', min: 0, max: 100, grid: { display: false }, border: { display: false }, ticks: { callback: (v) => v + '%' } });

    // Grafik batang horizontal: tinggi mengikuti jumlah label, label panjang dipotong sesuai lebar kanvas.
    function make(id, config) {
        const canvas = el(id);
        if (!canvas) return null;

        if (config.options && config.options.indexAxis === 'y') {
            const n = (config.data.labels || []).length;
            const box = canvas.parentElement;
            if (box && n > 7) box.style.height = Math.min(640, 70 + n * 30) + 'px';
            const y = config.options.scales && config.options.scales.y;
            if (y) {
                y.ticks = Object.assign({}, y.ticks, { autoSkip: false, callback(v) { return short(this.getLabelForValue(v), Math.max(9, Math.floor(canvas.clientWidth * 0.34 / 6.4))); } });
            }
            config.options.plugins = config.options.plugins || {};
            config.options.plugins.tooltip = Object.assign({ callbacks: {} }, config.options.plugins.tooltip);
            config.options.plugins.tooltip.callbacks.title = (items) => items.length ? String(items[0].label) : '';
        }

        if (config.type === 'doughnut' && narrow()) {
            config.options.plugins = config.options.plugins || {};
            config.options.plugins.legend = Object.assign({}, config.options.plugins.legend, { position: 'bottom' });
        }

        return new Chart(canvas, config);
    }

    const doughnut = (id, d, extra) => make(id, {
        type: 'doughnut',
        data: { labels: d.labels, datasets: [{ data: d.data, backgroundColor: d.colors, borderWidth: 2, borderColor: '#fff' }] },
        options: Object.assign({ cutout: '62%', plugins: { legend: { position: 'bottom', labels: { padding: 10 } } } }, extra || {}),
    });

    const hbar = (id, d, color, label) => make(id, {
        type: 'bar',
        data: { labels: d.labels, datasets: [{ label: label || 'Jumlah', data: d.data, backgroundColor: d.colors || color, borderRadius: 4, maxBarThickness: 22 }] },
        options: { indexAxis: 'y', scales: { x: axisY(), y: axisX() }, plugins: { legend: { display: false } } },
    });

    // ------------------------------------------------------------------ Hazard
    function hazardCharts(c) {
        const t = c.trend;
        if (!t) return;

        // Hazard dilaporkan (bertumpuk TTA / KTA) + yang diselesaikan + % tepat waktu
        make('cvTrend', {
            type: 'bar',
            data: {
                labels: t.labels,
                datasets: t.categories.map((l) => ({ type: 'bar', label: l.label, data: l.data, backgroundColor: l.color, stack: 'opened', borderRadius: 2, maxBarThickness: 34, yAxisID: 'y' }))
                    .concat([
                        { type: 'line', label: 'Diselesaikan', data: t.closed, borderColor: BRAND, backgroundColor: BRAND, tension: .3, pointRadius: 3, yAxisID: 'y' },
                        { type: 'line', label: 'Tepat waktu (%)', data: t.sla, borderColor: GOOD, backgroundColor: GOOD, borderDash: [5, 4], tension: .3, pointRadius: 3, spanGaps: true, yAxisID: 'y1' },
                    ]),
            },
            options: { scales: { x: axisX({ stacked: true }), y: axisY({ stacked: true }), y1: pctAxis() }, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } } },
        });

        make('cvAging', {
            type: 'bar',
            data: { labels: c.aging.labels, datasets: [{ label: 'Hazard terbuka', data: c.aging.data, backgroundColor: c.aging.colors, borderRadius: 4, maxBarThickness: 46 }] },
            options: {
                scales: { x: axisX(), y: axisY() },
                plugins: { legend: { display: false }, tooltip: { callbacks: { afterLabel: (i) => { const o = c.aging.overdue[i.dataIndex]; return o ? o + ' di antaranya melewati tenggat' : ''; } } } },
            },
        });

        doughnut('cvCategory', c.category);
        hbar('cvDept', c.department, ACCENT, 'Hazard dilaporkan');

        // Beban PIC: terbuka, terlambat, selesai
        if (c.pic) {
            make('cvPic', {
                type: 'bar',
                data: { labels: c.pic.labels, datasets: [
                    { label: 'Selesai', data: c.pic.closed, backgroundColor: GOOD, borderRadius: 3, maxBarThickness: 22 },
                    { label: 'Terbuka', data: c.pic.open, backgroundColor: '#f59e0b', borderRadius: 3, maxBarThickness: 22 },
                    { label: 'Terlambat', data: c.pic.overdue, backgroundColor: '#dc2626', borderRadius: 3, maxBarThickness: 22 },
                ] },
                options: { indexAxis: 'y', scales: { x: axisY({ stacked: true }), y: axisX({ stacked: true }) }, plugins: { legend: { position: 'bottom' } } },
            });
        }

        make('cvLocation', {
            type: 'bar',
            data: { labels: c.location.labels, datasets: [
                { label: 'TTA', data: c.location.tta, backgroundColor: '#f59e0b', borderRadius: 3, maxBarThickness: 22 },
                { label: 'KTA', data: c.location.kta, backgroundColor: BRAND, borderRadius: 3, maxBarThickness: 22 },
            ] },
            options: { indexAxis: 'y', scales: { x: axisY({ stacked: true }), y: axisX({ stacked: true }) }, plugins: { legend: { position: 'bottom' } } },
        });

        doughnut('cvStatus', c.status);
    }

    // ---------------------------------------------------------------- Inspeksi
    function inspectionCharts(c) {
        const t = c.trend;

        make('cvInsTrend', {
            type: 'bar',
            data: { labels: t.labels, datasets: [
                { type: 'bar', label: 'Inspeksi terlaksana', data: t.count, backgroundColor: BRAND, borderRadius: 3, maxBarThickness: 30, yAxisID: 'y' },
                { type: 'line', label: 'Target', data: t.target, borderColor: '#94a3b8', borderDash: [6, 4], pointRadius: 0, yAxisID: 'y' },
                { type: 'line', label: 'Skor rata-rata (%)', data: t.score, borderColor: GOOD, backgroundColor: GOOD, tension: .3, pointRadius: 3, spanGaps: true, yAxisID: 'y1' },
            ] },
            options: { scales: { x: axisX(), y: axisY(), y1: pctAxis() }, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } } },
        });

        make('cvInsLocation', {
            type: 'bar',
            data: { labels: c.location.labels, datasets: [{ label: 'Inspeksi', data: c.location.count, backgroundColor: ACCENT, borderRadius: 4, maxBarThickness: 22 }] },
            options: {
                indexAxis: 'y', scales: { x: axisY(), y: axisX() },
                plugins: { legend: { display: false }, tooltip: { callbacks: { afterLabel: (i) => { const sc = c.location.score[i.dataIndex]; return sc === null ? '' : 'Skor rata-rata: ' + fmt(sc) + '%'; } } } },
            },
        });
    }

    // ------------------------------------------------------------------ Target bulanan
    function targetChart(t) {
        const colors = t.pct.map((v) => (v >= 100 ? GOOD : v >= 50 ? '#f59e0b' : '#dc2626'));
        make('cvTarget', {
            type: 'bar',
            data: { labels: t.labels, datasets: [{ label: 'Capaian (%)', data: t.pct, backgroundColor: colors, borderRadius: 4, maxBarThickness: 22 }] },
            options: { indexAxis: 'y', scales: { x: axisY({ max: 100, ticks: { callback: (v) => v + '%' } }), y: axisX() }, plugins: { legend: { display: false } } },
        });
    }

    // Grafik dibuat setelah font web siap, supaya ukuran label dihitung dengan font yang benar (bukan font cadangan).
    function afterFonts(fn) {
        if (document.fonts && document.fonts.ready) {
            let done = false;
            const once = () => { if (!done) { done = true; fn(); } };
            document.fonts.ready.then(once);
            setTimeout(once, 1500);
        } else {
            fn();
        }
    }

    // Satu pintu untuk semua halaman: data { ...hazard, inspection: {...} }.
    window.SSAPCharts = function (c) {
        afterFonts(function () {
            hazardCharts(c);
            if (c.inspection) inspectionCharts(c.inspection);
            if (c.target) targetChart(c.target);
        });
    };

    window.SSAPInspectionCharts = function (c) {
        afterFonts(function () { inspectionCharts(c); });
    };
})();
