{{-- Filter tahun + lokasi untuk dashboard. Butuh $year, $years, $locationId, $locations. $note opsional. --}}
<form method="GET" class="s-card p-3 mb-3 filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label small mb-1">Tahun</label>
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <label class="form-label small mb-1">Lokasi</label>
            <select name="location" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Semua lokasi</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected($l->id == $locationId)>{{ $l->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md ms-md-auto small text-muted"><i class="bi bi-info-circle me-1"></i>{{ $note ?? 'Angka periode mengikuti tahun & lokasi terpilih. Bagian "terkini" selalu kondisi saat ini.' }}</div>
    </div>
</form>
