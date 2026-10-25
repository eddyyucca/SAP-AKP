/* SSAP Safety: perilaku halaman (pilih karyawan HRIS, baris orang, matriks risiko, dsb). Tanpa pustaka tambahan selain jQuery/Bootstrap. */
(function () {
    'use strict';

    const cfg = window.SSAP || {};

    // --- Pilih karyawan dari HRIS -------------------------------------------------------------
    // Markup: <div data-emp-picker data-scope=".scope-selector"> <input data-emp-search> <div data-emp-results> ...
    // Kolom yang diisi memakai atribut data-emp-field="nik|name|department|position|email" di dalam cakupan.
    function debounce(fn, ms) {
        let t;
        return function () { const a = arguments, c = this; clearTimeout(t); t = setTimeout(() => fn.apply(c, a), ms); };
    }

    function initPicker(picker) {
        if (picker.dataset.ready) return;
        picker.dataset.ready = '1';

        const input = picker.querySelector('[data-emp-search]');
        const results = picker.querySelector('[data-emp-results]');
        const scope = (picker.dataset.scope && picker.closest(picker.dataset.scope)) || picker.closest('[data-emp-scope]') || picker;
        if (!input || !results || !cfg.employeeUrl) return;

        const field = (k) => scope.querySelector('[data-emp-field="' + k + '"]');
        const hide = () => { results.classList.add('d-none'); results.innerHTML = ''; };

        function choose(emp) {
            ['nik', 'name', 'department', 'position', 'email'].forEach(function (k) {
                const el = field(k);
                if (el && (emp[k] != null)) el.value = emp[k];
            });
            input.value = '';
            hide();
            const note = picker.querySelector('[data-emp-chosen]');
            if (note) note.textContent = 'Dari HRIS: ' + emp.name + ' (NIK ' + emp.nik + ')';
        }

        const search = debounce(function () {
            const q = input.value.trim();
            if (q.length < 2) { hide(); return; }

            fetch(cfg.employeeUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then((r) => r.ok ? r.json() : Promise.reject(r.status))
                .then(function (res) {
                    results.innerHTML = '';
                    results.classList.remove('d-none');

                    if (!res.configured) {
                        results.innerHTML = '<div class="emp-empty">Pencarian HRIS belum diaktifkan. Isi nama secara manual.</div>';
                        return;
                    }
                    if (!res.data.length) {
                        results.innerHTML = '<div class="emp-empty">Tidak ditemukan di HRIS. Anda dapat mengisi manual.</div>';
                        return;
                    }
                    res.data.forEach(function (emp) {
                        const b = document.createElement('button');
                        b.type = 'button';
                        const strong = document.createElement('strong');
                        strong.textContent = emp.name;
                        const small = document.createElement('small');
                        small.textContent = emp.nik + ' · ' + (emp.department || '-') + ' · ' + (emp.position || '-');
                        b.appendChild(strong); b.appendChild(small);
                        b.addEventListener('click', () => choose(emp));
                        results.appendChild(b);
                    });
                })
                .catch(function () {
                    results.classList.remove('d-none');
                    results.innerHTML = '<div class="emp-empty">Pencarian tidak tersedia. Isi manual.</div>';
                });
        }, 250);

        input.addEventListener('input', search);
        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); if (e.key === 'Enter') e.preventDefault(); });
        document.addEventListener('click', function (e) { if (!picker.contains(e.target)) hide(); });
    }

    // --- Baris orang (korban/saksi) pada formulir insiden -------------------------------------
    function initPersons() {
        const wrap = document.getElementById('personRows');
        const tpl = document.getElementById('personTpl');
        const add = document.getElementById('addPerson');
        if (!wrap || !tpl || !add) return;

        let idx = parseInt(wrap.dataset.next || '0', 10);

        // Kolom cedera hanya relevan untuk korban.
        function applyRole(row) {
            const sel = row.querySelector('[data-role]');
            row.querySelectorAll('.victim-only').forEach((c) => c.classList.toggle('d-none', !sel || sel.value !== 'korban'));
        }

        function addRow(role) {
            const html = tpl.innerHTML.replace(/__i__/g, String(idx++));
            const holder = document.createElement('div');
            holder.innerHTML = html.trim();
            const row = holder.firstElementChild;
            if (role) { const r = row.querySelector('[data-role]'); if (r) r.value = role; }
            wrap.appendChild(row);
            row.querySelectorAll('[data-emp-picker]').forEach(initPicker);
            applyRole(row);
            refresh();
            return row;
        }

        function refresh() {
            const empty = document.getElementById('personEmpty');
            if (empty) empty.classList.toggle('d-none', wrap.children.length > 0);
        }

        add.addEventListener('click', () => addRow('korban'));
        document.getElementById('addWitness')?.addEventListener('click', () => addRow('saksi'));
        wrap.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-remove-person]');
            if (btn) { btn.closest('.person-row').remove(); refresh(); }
        });

        wrap.addEventListener('change', function (e) {
            if (e.target.matches('[data-role]')) applyRole(e.target.closest('.person-row'));
        });

        wrap.querySelectorAll('[data-emp-picker]').forEach(initPicker);
        wrap.querySelectorAll('.person-row').forEach(applyRole);
        refresh();
    }

    // --- Pemilih matriks risiko 5x5 -----------------------------------------------------------
    function initRiskPicker(root) {
        const levels = JSON.parse(root.dataset.levels || '{}');
        const likelihood = root.querySelector('input[name="likelihood"]');
        const consequence = root.querySelector('input[name="consequence"]');
        const out = root.querySelector('[data-risk-result]');
        const cells = root.querySelectorAll('[data-l]');
        const optional = root.dataset.optional === '1';

        function levelOf(score) {
            const keys = Object.keys(levels);
            for (let i = 0; i < keys.length; i++) if (score <= levels[keys[i]].max) return keys[i];
            return keys[keys.length - 1];
        }

        function paint() {
            const l = parseInt(likelihood.value, 10), c = parseInt(consequence.value, 10);
            cells.forEach(function (cell) {
                const on = parseInt(cell.dataset.l, 10) === l && parseInt(cell.dataset.c, 10) === c;
                cell.style.outline = on ? '3px solid #111827' : '';
                cell.style.outlineOffset = on ? '1px' : '';
                cell.setAttribute('aria-pressed', on ? 'true' : 'false');
            });

            if (!l || !c) {
                out.style.background = '#f1f5f9'; out.style.color = '#475569';
                out.textContent = optional ? 'Opsional: klik sel pada matriks untuk menilai potensi risiko terburuk.' : 'Klik sel pada matriks untuk memilih tingkat risiko.';
                return;
            }
            const score = l * c, key = levelOf(score), lv = levels[key];
            out.style.background = lv.bg; out.style.color = lv.color;
            out.innerHTML = '';
            const b = document.createElement('strong');
            b.textContent = 'Risiko ' + lv.label + ' (skor ' + score + ')';
            out.appendChild(b);
            out.appendChild(document.createTextNode(' - ' + lv.action + ' Target penyelesaian ' + lv.sla_days + ' hari.'));
        }

        cells.forEach(function (cell) {
            cell.addEventListener('click', function () {
                likelihood.value = cell.dataset.l; consequence.value = cell.dataset.c; paint();
            });
        });
        root.querySelector('[data-risk-clear]')?.addEventListener('click', function () { likelihood.value = ''; consequence.value = ''; paint(); });
        paint();
    }

    // --- Lain-lain ----------------------------------------------------------------------------
    function initMisc() {
        // Keparahan awal mengikuti jenis kejadian (selama belum diubah manual).
        const type = document.getElementById('incidentType');
        const sev = document.getElementById('incidentSeverity');
        if (type && sev) {
            const map = JSON.parse(type.dataset.severity || '{}');
            const injury = JSON.parse(type.dataset.injury || '{}');
            let touched = sev.dataset.touched === '1';
            sev.addEventListener('change', () => { touched = true; });
            const hint = document.getElementById('injuryHint');
            const sync = function () {
                if (!touched && map[type.value]) sev.value = map[type.value];
                if (hint) hint.classList.toggle('d-none', !injury[type.value]);
            };
            type.addEventListener('change', sync);
            sync();
        }

        // Konfirmasi sebelum tindakan yang tak bisa dibatalkan.
        document.addEventListener('submit', function (e) {
            const msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) e.preventDefault();
        });

        // Baris tabel yang bisa diklik.
        document.addEventListener('click', function (e) {
            const row = e.target.closest('tr[data-href]');
            if (row && !e.target.closest('a,button,input,select,textarea,label,form')) window.location = row.dataset.href;
        });

        // Cegah kirim ganda formulir.
        document.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            const form = e.target;
            if (form.dataset.noLock) return;
            form.querySelectorAll('button[type="submit"]').forEach(function (b) { setTimeout(() => { b.disabled = true; }, 0); });
        });

        // Tombol cetak
        document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-emp-picker]').forEach(function (p) { if (!p.closest('#personTpl')) initPicker(p); });
        document.querySelectorAll('[data-risk-picker]').forEach(initRiskPicker);
        initPersons();
        initMisc();
    });
})();
