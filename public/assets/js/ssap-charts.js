/* SAP Safety: grafik Chart.js untuk dashboard, laporan, dan ringkasan bulanan.
   Data datang dari server (SafetyStats::charts) sebagai JSON; setiap grafik hanya dibuat bila kanvasnya ada di halaman. */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;

    const INK = '#374151', MUTED = '#6b7280', GRID = '#eef2f7';
    const BRAND = '#203868', ACCENT = '#28a8e0';

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
    const noData = {
        id: 'noData',
        afterDraw(chart) {
            const sets = chart.data.datasets || [];
            const any = sets.some((d) => (d.data || []).some((v) => v !== null && Number(v) > 0));
            if (any) return;
            const { ctx, chartArea: a } = chart;
            if (!a) return;
            ctx.save();
            ctx.fillStyle = MUTED; ctx.textAlign = 'center'; ctx.font = "13px 'Inter', sans-serif";
            ctx.fillText('Belum ada data pada periode ini', (a.left + a.right) / 2, (a.top + a.bottom) / 2);
            ctx.restore();
        },
    };
    Chart.register(noData);

    const el = (id) => document.getElementById(id);
    const sum = (arr) => (arr || []).reduce((s, v) => s + (Number(v) || 0), 0);
    const fmt = (v) => (v === null || v === undefined ? '-' : Number(v).toLocaleString('id-ID'));

    const axisY = (extra) => Object.assign({ beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { precision: 0 } }, extra || {});
    const axisX = (extra) => Object.assign({ grid: { display: false }, border: { color: GRID } }, extra || {});

    const short = (t, n) => { t = String(t); return t.length > (n || 24) ? t.slice(0, (n || 24) - 1) + '…' : t; };
    const narrow = () => window.innerWidth < 576;

    // Grafik batang horizontal: tinggi mengikuti jumlah label supaya tidak berdesakan, label panjang dipotong.
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

        if (config.type === 'doughnut' && config.options && config.options.plugins && config.options.plugins.legend && narrow()) {
            config.options.plugins.legend.position = 'bottom';
        }

        return new Chart(canvas, config);
    }

    window.SSAPCharts = function (c) {
        const t = c.trend;

        // Tren kejadian per kelompok (tumpuk)
        make('cvTrend', {
            type: 'bar',
            data: { labels: t.labels, datasets: t.groups.map((g) => ({ label: g.label, data: g.data, backgroundColor: g.color, borderRadius: 3, borderSkipped: false, maxBarThickness: 36 })) },
            options: { scales: { x: axisX({ stacked: true }), y: axisY({ stacked: true }) }, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } } },
        });

        // Laju kecelakaan (LTIFR, TRIR) per bulan
        make('cvRate', {
            type: 'line',
            data: {
                labels: t.labels,
                datasets: [
                    { label: 'LTIFR', data: t.ltifr, borderColor: '#dc2626', backgroundColor: '#dc2626', tension: .3, pointRadius: 3, spanGaps: true },
                    { label: 'TRIR', data: t.trir, borderColor: BRAND, backgroundColor: BRAND, tension: .3, pointRadius: 3, spanGaps: true },
                ],
            },
            options: {
                scales: { x: axisX(), y: axisY({ ticks: { precision: 1 } }) },
                plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false, callbacks: { label: (i) => i.dataset.label + ': ' + fmt(i.parsed.y) } } },
            },
        });

        // Komposisi jenis kejadian
        make('cvType', {
            type: 'doughnut',
            data: { labels: c.type.labels, datasets: [{ data: c.type.data, backgroundColor: c.type.colors, borderWidth: 2, borderColor: '#fff' }] },
            options: { cutout: '62%', plugins: { legend: { position: 'right', labels: { padding: 10 } } } },
        });

        make('cvSeverity', {
            type: 'bar',
            data: { labels: c.severity.labels, datasets: [{ label: 'Kejadian', data: c.severity.data, backgroundColor: c.severity.colors, borderRadius: 4, maxBarThickness: 26 }] },
            options: { indexAxis: 'y', scales: { x: axisY(), y: axisX() }, plugins: { legend: { display: false } } },
        });

        const grouped = (id, d, h) => make(id, {
            type: 'bar',
            data: { labels: d.labels, datasets: [
                { label: 'Insiden', data: d.incidents, backgroundColor: '#dc2626', borderRadius: 4, maxBarThickness: 22 },
                { label: 'Hazard', data: d.hazards, backgroundColor: ACCENT, borderRadius: 4, maxBarThickness: 22 },
            ] },
            options: { indexAxis: 'y', scales: { x: axisY(), y: axisX() }, plugins: { legend: { position: 'bottom' } } },
        });
        grouped('cvLocation', c.location);
        grouped('cvDept', c.department);

        // Hazard dilaporkan vs diselesaikan
        make('cvHazTrend', {
            type: 'bar',
            data: { labels: t.labels, datasets: [
                { type: 'bar', label: 'Dilaporkan', data: t.hazard_opened, backgroundColor: ACCENT, borderRadius: 3, maxBarThickness: 28 },
                { type: 'line', label: 'Diselesaikan', data: t.hazard_closed, borderColor: '#16a34a', backgroundColor: '#16a34a', tension: .3, pointRadius: 3 },
            ] },
            options: { scales: { x: axisX(), y: axisY() }, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } } },
        });

        make('cvHazCat', {
            type: 'bar',
            data: { labels: c.hazardCategory.labels, datasets: [{ label: 'Hazard', data: c.hazardCategory.data, backgroundColor: BRAND, borderRadius: 4, maxBarThickness: 20 }] },
            options: { indexAxis: 'y', scales: { x: axisY(), y: axisX({ ticks: { autoSkip: false } }) }, plugins: { legend: { display: false } } },
        });

        make('cvHazLevel', {
            type: 'doughnut',
            data: { labels: c.hazardLevel.labels, datasets: [{ data: c.hazardLevel.data, backgroundColor: c.hazardLevel.colors, borderWidth: 2, borderColor: '#fff' }] },
            options: { cutout: '62%', plugins: { legend: { position: 'right' } } },
        });

        make('cvRoot', {
            type: 'bar',
            data: { labels: c.rootCause.labels, datasets: [{ label: 'Insiden', data: c.rootCause.data, backgroundColor: '#7c3aed', borderRadius: 4, maxBarThickness: 20 }] },
            options: { indexAxis: 'y', scales: { x: axisY(), y: axisX({ ticks: { autoSkip: false } }) }, plugins: { legend: { display: false } } },
        });

        make('cvActions', {
            type: 'bar',
            data: { labels: c.actions.labels, datasets: [{ label: 'Tindakan', data: c.actions.data, backgroundColor: c.actions.colors, borderRadius: 4, maxBarThickness: 44 }] },
            options: { scales: { x: axisX(), y: axisY() }, plugins: { legend: { display: false } } },
        });
    };

    // Dashboard inspeksi rutin
    window.SSAPInspectionCharts = function (c) {
        const t = c.trend;

        make('cvInsTrend', {
            type: 'bar',
            data: { labels: t.labels, datasets: [
                { type: 'bar', label: 'Inspeksi terlaksana', data: t.count, backgroundColor: BRAND, borderRadius: 3, maxBarThickness: 30, yAxisID: 'y' },
                { type: 'line', label: 'Target', data: t.target, borderColor: '#94a3b8', borderDash: [6, 4], pointRadius: 0, yAxisID: 'y' },
                { type: 'line', label: 'Skor rata-rata (%)', data: t.score, borderColor: '#16a34a', backgroundColor: '#16a34a', tension: .3, pointRadius: 3, spanGaps: true, yAxisID: 'y1' },
            ] },
            options: {
                scales: { x: axisX(), y: axisY(), y1: { position: 'right', min: 0, max: 100, grid: { display: false }, border: { display: false }, ticks: { callback: (v) => v + '%' } } },
                plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } },
            },
        });

        make('cvInsLocation', {
            type: 'bar',
            data: { labels: c.location.labels, datasets: [{ label: 'Inspeksi', data: c.location.count, backgroundColor: ACCENT, borderRadius: 4, maxBarThickness: 22 }] },
            options: {
                indexAxis: 'y', scales: { x: axisY(), y: axisX({ ticks: { autoSkip: false } }) },
                plugins: { legend: { display: false }, tooltip: { callbacks: { afterLabel: (i) => { const sc = c.location.score[i.dataIndex]; return sc === null ? '' : 'Skor rata-rata: ' + fmt(sc) + '%'; } } } },
            },
        });
    };

    // Monitoring LPI kontraktor
    window.SSAPLpiCharts = function (c) {
        make('cvLpiContractor', {
            type: 'bar',
            data: { labels: c.contractor.labels, datasets: c.contractor.series.map((x) => ({ label: x.label, data: x.data, backgroundColor: x.color, borderRadius: 3, maxBarThickness: 26 })) },
            options: { indexAxis: 'y', scales: { x: axisY({ stacked: true }), y: axisX({ stacked: true, ticks: { autoSkip: false } }) }, plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } } },
        });

        make('cvLpiStatus', {
            type: 'doughnut',
            data: { labels: c.status.labels, datasets: [{ data: c.status.data, backgroundColor: c.status.colors, borderWidth: 2, borderColor: '#fff' }] },
            options: { cutout: '62%', plugins: { legend: { position: 'bottom' } } },
        });
    };

    ['SSAPCharts', 'SSAPInspectionCharts', 'SSAPLpiCharts'].forEach(function (name) {
        const fn = window[name];
        window[name] = function (data) {
            const go = () => fn(data);
            if (document.fonts && document.fonts.ready) {
                let done = false;
                const once = () => { if (!done) { done = true; go(); } };
                document.fonts.ready.then(once);
                setTimeout(once, 1500);   // jangan menunggu font selamanya
            } else {
                go();
            }
        };
    });
})();
