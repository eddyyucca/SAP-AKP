@extends('layouts.app')

@section('title', 'Inspeksi Baru')
@section('page-title', 'Inspeksi Rutin')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ssap.inspections.index') }}">Inspeksi</a></li>
    <li class="breadcrumb-item active">Baru</li>
@endsection

@section('content')
@if (! $template)
    {{-- Langkah 1: pilih template --}}
    <div class="mb-3"><h2 class="h5 fw-bold mb-1">Pilih jenis inspeksi</h2><div class="small text-muted">Pilih checklist yang akan Anda isi.</div></div>
    <div class="row g-3">
        @forelse ($templates as $t)
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route('ssap.inspections.create', ['template' => $t->id]) }}" class="quick-btn">
                    <span class="qi tone-primary"><i class="bi bi-clipboard2-check"></i></span>
                    <span><span class="fw-bold d-block">{{ $t->name }}</span><span class="small text-muted">{{ $t->items_count }} butir &middot; target {{ $t->target_per_month }}x/bulan</span></span>
                </a>
            </div>
        @empty
            <div class="col-12"><div class="alert alert-warning">Belum ada template inspeksi aktif. Administrator/HSE dapat membuatnya di menu <strong>Template Inspeksi</strong>.</div></div>
        @endforelse
    </div>
@else
    @if ($locations->isEmpty())
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Belum ada lokasi. Minta Administrator menambahkannya di menu <strong>Lokasi / Site</strong>.</div>
    @endif
    <form method="POST" action="{{ route('ssap.inspections.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        <input type="hidden" name="template_id" value="{{ $template->id }}">
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="s-card mb-3">
                    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-clipboard2-check"></i>{{ $template->name }}</h3><a href="{{ route('ssap.inspections.create') }}" class="small">Ganti template</a></div>
                    <div class="s-card-body">
                        @error('result')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                        @error('note')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                        <p class="small text-muted">Nilai setiap butir: <strong class="text-success">Sesuai</strong>, <strong class="text-danger">Tidak sesuai</strong> (wajib catatan, otomatis menjadi laporan hazard), atau <strong>N/A</strong> bila tidak berlaku.</p>

                        @foreach ($template->items as $n => $item)
                            @php($val = old('result.'.$item->id))
                            <div class="border rounded-3 p-3 mb-2" data-item>
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div class="fw-semibold" style="flex:1;min-width:220px;">{{ $n + 1 }}. {{ $item->question }}</div>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check" name="result[{{ $item->id }}]" id="r{{ $item->id }}ok" value="ok" @checked($val === 'ok') required>
                                        <label class="btn btn-outline-success" for="r{{ $item->id }}ok"><i class="bi bi-check-lg"></i> Sesuai</label>
                                        <input type="radio" class="btn-check" name="result[{{ $item->id }}]" id="r{{ $item->id }}nok" value="nok" @checked($val === 'nok')>
                                        <label class="btn btn-outline-danger" for="r{{ $item->id }}nok"><i class="bi bi-x-lg"></i> Tidak sesuai</label>
                                        <input type="radio" class="btn-check" name="result[{{ $item->id }}]" id="r{{ $item->id }}na" value="na" @checked($val === 'na')>
                                        <label class="btn btn-outline-secondary" for="r{{ $item->id }}na">N/A</label>
                                    </div>
                                </div>
                                <div class="mt-2 {{ $val === 'nok' ? '' : 'd-none' }}" data-note>
                                    <input type="text" name="note[{{ $item->id }}]" value="{{ old('note.'.$item->id) }}" class="form-control form-control-sm" maxlength="1000" placeholder="Apa yang ditemukan? (wajib untuk temuan)">
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-3">
                            <label class="form-label" for="notes">Catatan umum</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control" maxlength="3000" placeholder="Kesimpulan atau hal lain yang perlu dicatat">{{ old('notes') }}</textarea>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="files">Foto temuan / dokumentasi</label>
                            <input type="file" name="files[]" id="files" class="form-control" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <div class="form-text">Maksimal {{ config('ssap.attachments.max_files') }} berkas, {{ config('ssap.attachments.max_kb') / 1024 }} MB per berkas.</div>
                            @error('files')<div class="text-danger small">{{ $message }}</div>@enderror
                            @error('files.*')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="s-card mb-3">
                    <div class="s-card-head"><h3 class="s-card-title"><i class="bi bi-geo-alt"></i>Lokasi &amp; waktu</h3></div>
                    <div class="s-card-body">
                        <div class="mb-2"><label class="form-label small mb-1 req" for="location_id">Lokasi / site</label>
                            <select name="location_id" id="location_id" class="form-select form-select-sm @error('location_id') is-invalid @enderror" required>
                                <option value="">Pilih lokasi...</option>
                                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected((int) old('location_id') === $l->id)>{{ $l->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-2"><label class="form-label small mb-1" for="area_detail">Detail area</label><input type="text" name="area_detail" id="area_detail" value="{{ old('area_detail') }}" class="form-control form-control-sm" maxlength="160" placeholder="mis. Workshop Bay 2"></div>
                        <div class="mb-2"><label class="form-label small mb-1 req" for="inspected_at">Waktu inspeksi</label><input type="datetime-local" name="inspected_at" id="inspected_at" value="{{ old('inspected_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control form-control-sm @error('inspected_at') is-invalid @enderror" required></div>
                        <div><label class="form-label small mb-1" for="inspector_department">Departemen inspektor</label><input type="text" name="inspector_department" id="inspector_department" value="{{ old('inspector_department', $me['department'] ?? '') }}" class="form-control form-control-sm" maxlength="120"></div>
                        <div class="small text-muted mt-2">Inspektor: <strong>{{ $me['name'] ?? $portalUser->name }}</strong></div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Simpan inspeksi</button>
                    <a href="{{ route('ssap.inspections.index') }}" class="btn btn-outline-secondary">Batal</a>
                </div>
            </div>
        </div>
    </form>
    <script>
        document.addEventListener('change', function (e) {
            if (!e.target.matches('input[type=radio][name^="result"]')) return;
            const box = e.target.closest('[data-item]');
            const note = box.querySelector('[data-note]');
            note.classList.toggle('d-none', e.target.value !== 'nok');
            if (e.target.value === 'nok') note.querySelector('input').focus();
        });
    </script>
@endif
@endsection
