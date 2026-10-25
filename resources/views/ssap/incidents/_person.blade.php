@php($p = $p ?? [])
<div class="person-row mb-2" data-emp-scope>
    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <div class="d-flex flex-wrap align-items-center gap-2" style="min-width:0;flex:1;">
            <select name="persons[{{ $i }}][role]" class="form-select form-select-sm" style="width:auto;" data-role>
                @foreach (config('ssap.person_roles') as $k => $label)
                    <option value="{{ $k }}" @selected(($p['role'] ?? 'korban') === $k)>{{ $label }}</option>
                @endforeach
            </select>
            <div style="min-width:min(100%,200px);flex:1;"><x-employee-search placeholder="Cari karyawan HRIS (opsional)" /></div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-person title="Hapus baris"><i class="bi bi-trash"></i></button>
    </div>
    <div class="row g-2">
        <div class="col-6 col-md-2"><input type="text" name="persons[{{ $i }}][nik]" value="{{ $p['nik'] ?? '' }}" class="form-control form-control-sm" placeholder="NIK" data-emp-field="nik" maxlength="30"></div>
        <div class="col-6 col-md-4"><input type="text" name="persons[{{ $i }}][name]" value="{{ $p['name'] ?? '' }}" class="form-control form-control-sm" placeholder="Nama lengkap *" data-emp-field="name" maxlength="120"></div>
        <div class="col-6 col-md-3"><input type="text" name="persons[{{ $i }}][department]" value="{{ $p['department'] ?? '' }}" class="form-control form-control-sm" placeholder="Departemen" data-emp-field="department" maxlength="120"></div>
        <div class="col-6 col-md-3"><input type="text" name="persons[{{ $i }}][position]" value="{{ $p['position'] ?? '' }}" class="form-control form-control-sm" placeholder="Jabatan" data-emp-field="position" maxlength="120"></div>
        <div class="col-12 col-md-4"><input type="text" name="persons[{{ $i }}][company]" value="{{ $p['company'] ?? '' }}" class="form-control form-control-sm" placeholder="Perusahaan (isi bila kontraktor/tamu)" maxlength="120"></div>
        <div class="col-6 col-md-4 victim-only"><select name="persons[{{ $i }}][injury_type]" class="form-select form-select-sm">
            <option value="">Jenis cedera</option>
            @foreach (config('ssap.injury_types') as $t)<option @selected(($p['injury_type'] ?? '') === $t)>{{ $t }}</option>@endforeach
        </select></div>
        <div class="col-6 col-md-4 victim-only"><select name="persons[{{ $i }}][body_part]" class="form-select form-select-sm">
            <option value="">Bagian tubuh</option>
            @foreach (config('ssap.body_parts') as $t)<option @selected(($p['body_part'] ?? '') === $t)>{{ $t }}</option>@endforeach
        </select></div>
        <div class="col-6 col-md-3 victim-only"><input type="number" min="0" max="36500" name="persons[{{ $i }}][lost_days]" value="{{ $p['lost_days'] ?? '' }}" class="form-control form-control-sm" placeholder="Hari kerja hilang"></div>
        <div class="col-12 col-md-9 victim-only"><input type="text" name="persons[{{ $i }}][treatment]" value="{{ $p['treatment'] ?? '' }}" class="form-control form-control-sm" placeholder="Penanganan medis / pertolongan yang diberikan" maxlength="1000"></div>
    </div>
</div>
