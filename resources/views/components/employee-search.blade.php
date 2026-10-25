@props(['placeholder' => 'Cari NIK atau nama di HRIS...'])
{{-- Kotak cari karyawan HRIS. Mengisi kolom bertanda data-emp-field di dalam elemen data-emp-scope terdekat. --}}
<div class="emp-picker" data-emp-picker>
    <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control" data-emp-search placeholder="{{ $placeholder }}" autocomplete="off" aria-label="{{ $placeholder }}">
    </div>
    <div class="emp-results d-none" data-emp-results role="listbox"></div>
    <div class="emp-chosen" data-emp-chosen></div>
</div>
