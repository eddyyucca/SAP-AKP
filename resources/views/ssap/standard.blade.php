@extends('layouts.app')

@section('title', 'Standar Pelaporan')
@section('page-title', 'Standar Pelaporan')
@section('breadcrumb')
    <li class="breadcrumb-item active">Standar</li>
@endsection

@section('content')
<div class="alert alert-primary small"><i class="bi bi-journal-check me-1"></i><strong>Safety Accountability Program (SAP)</strong> {{ config('ssap.company') }}: pelapor adalah PIC hazardnya; perbaikan dikerjakan secara manual di lapangan, lalu pelapor mencatat tindakan perbaikan yang menutup laporan. Tim safety hanya memantau. Inspeksi rutin memastikan area kerja diperiksa terjadwal, dan setiap temuan langsung menjadi hazard.</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-1-circle"></i>Alur hazard</h3></div>
            <div class="s-card-body small">
                <ol class="mb-2">
                    <li><strong>Dilaporkan</strong> oleh siapa saja (jenis TTA atau KTA, keterangan, dan foto). Tenggat awal {{ $slaDays }} hari.</li>
                    <li><strong>Pelapor otomatis menjadi PIC</strong> laporan itu. Perbaikan dikerjakan secara manual di lapangan; tidak ada penugasan lewat sistem.</li>
                    <li>Pelapor dapat menandai <em>Sedang diperbaiki</em> (status <em>Dalam perbaikan</em>).</li>
                    <li>Setelah perbaikan selesai, pelapor <strong>mencatat tindakan perbaikan</strong> (dengan foto bukti) dan laporan otomatis <strong>Selesai</strong>. Bila belum tuntas, pelapor dapat membukanya kembali.</li>
                </ol>
                <div class="text-muted">Tepat waktu = hazard ditutup pada atau sebelum tanggal tenggat. Hazard terbuka yang lewat tenggat berstatus <em>terlambat</em>.</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-2-circle"></i>Jenis hazard: TTA &amp; KTA</h3></div>
            <div class="s-card-body small">
                @foreach ($categories as $k => $label)
                    <div class="mb-2"><x-cat :category="$k" :long="true" /> <span class="text-muted">{{ $k === 'tta' ? 'Perilaku atau tindakan orang yang tidak aman, misalnya tidak memakai APD atau melanggar prosedur.' : 'Kondisi lokasi, peralatan, atau lingkungan yang tidak aman, misalnya jalan licin atau kabel terkelupas.' }}</span></div>
                @endforeach
                <div class="text-muted">Temuan &ldquo;Tidak sesuai&rdquo; dari inspeksi otomatis tercatat sebagai KTA.</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-3-circle"></i>Tenggat penyelesaian</h3></div>
            <div class="s-card-body small">Setiap hazard baru diberi tenggat <strong>{{ $slaDays }} hari</strong> sejak dilaporkan. Tenggat inilah yang dipakai untuk menghitung <em>tepat waktu</em> dan <em>terlambat</em>. Tim safety (HSE) hanya <strong>memantau</strong> lewat dashboard, laporan, dan email ringkasan; tidak ada langkah persetujuan oleh orang lain.</div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-4-circle"></i>Inspeksi rutin</h3></div>
            <div class="s-card-body small">
                <ul class="mb-2">
                    <li>Inspektor mengisi checklist: <strong>Sesuai</strong>, <strong>Tidak sesuai</strong> (wajib catatan), atau <strong>N/A</strong>.</li>
                    <li>Setiap butir <em>Tidak sesuai</em> otomatis menjadi <strong>laporan hazard</strong> (jenis KTA) lengkap dengan tenggat dan PIC.</li>
                    <li>Skor = butir sesuai / (sesuai + tidak sesuai) x 100%; N/A tidak dihitung. <strong>&ge; {{ $scoreGood }}%</strong> baik, <strong>{{ $scoreFair }}-{{ $scoreGood - 1 }}%</strong> cukup, <strong>&lt; {{ $scoreFair }}%</strong> perlu perbaikan.</li>
                    <li>Kepatuhan jadwal = inspeksi terlaksana / (target bulanan template x bulan berjalan).</li>
                </ul>
                <table class="table table-sm table-ssap mb-0">
                    <thead><tr><th>Template aktif</th><th class="num">Butir</th></tr></thead>
                    <tbody>@forelse ($templates as $t)<tr><td>{{ $t->name }}<div class="small text-muted">Target {{ $t->target_per_month }}x per bulan</div></td><td class="num">{{ $t->items_count }}</td></tr>@empty<tr><td colspan="2" class="text-muted">Belum ada template.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="s-card h-100">
            <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-5-circle"></i>Akuntabilitas &amp; indikator</h3></div>
            <div class="s-card-body small">
                <dl class="def-list" style="grid-template-columns:150px 1fr;">
                    <dt>Penyelesaian</dt><dd>hazard selesai / seluruh hazard pada periode.</dd>
                    <dt>Tepat waktu (SLA)</dt><dd>hazard selesai sebelum/pada tenggat / seluruh hazard selesai.</dd>
                    <dt>Rata-rata selesai</dt><dd>rata-rata hari sejak dilaporkan sampai ditutup.</dd>
                    <dt>Umur hazard</dt><dd>hari sejak dilaporkan untuk hazard yang masih terbuka (0-3, 4-7, 8-14, 15-30, &gt; 30 hari).</dd>
                    <dt>Papan pelapor (PIC)</dt><dd>jumlah hazard, terbuka, terlambat, selesai, % tepat waktu per pelapor dan per departemen.</dd>
                    <dt>Pengingat</dt><dd>Pelapor (PIC) menerima email pengingat tiap pagi sampai hazardnya ditutup; HSE menerima ringkasan pantauan harian.</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
