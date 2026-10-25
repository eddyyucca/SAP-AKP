@php($t = $t ?? null)
<div class="col-12 col-md-5"><label class="form-label small mb-1">Nama template</label><input type="text" name="name" value="{{ $t ? $t->name : old('name') }}" class="form-control form-control-sm" maxlength="120" required></div>
<div class="col-12 col-md-4"><label class="form-label small mb-1">Kategori hazard bila ada temuan</label>
    <select name="category" class="form-select form-select-sm">
        @foreach (config('ssap.hazard_categories') as $k => $label)<option value="{{ $k }}" @selected(($t->category ?? old('category', 'unsafe_condition')) === $k)>{{ $label }}</option>@endforeach
    </select>
</div>
<div class="col-6 col-md-3"><label class="form-label small mb-1">Target per bulan</label><input type="number" min="0" max="500" name="target_per_month" value="{{ $t->target_per_month ?? old('target_per_month', 1) }}" class="form-control form-control-sm" required></div>
<div class="col-12"><label class="form-label small mb-1">Keterangan</label><input type="text" name="description" value="{{ $t->description ?? old('description') }}" class="form-control form-control-sm" maxlength="255"></div>
<div class="col-12"><label class="form-label small mb-1">Butir pemeriksaan <span class="text-muted">(satu per baris)</span></label>
    <textarea name="items" rows="{{ $t ? min(12, max(5, $t->items->count() + 1)) : 5 }}" class="form-control form-control-sm" required>{{ $t ? $t->items->pluck('question')->implode("\n") : old('items') }}</textarea>
</div>
