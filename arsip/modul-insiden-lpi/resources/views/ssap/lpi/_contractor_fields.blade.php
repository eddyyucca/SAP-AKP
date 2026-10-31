@php($c = $c ?? null)
<div class="col-12 col-md-5"><label class="form-label small mb-1">Nama kontraktor</label><input type="text" name="name" value="{{ $c ? $c->name : old('name') }}" class="form-control form-control-sm" maxlength="160" required></div>
<div class="col-6 col-md-3"><label class="form-label small mb-1">Area izin</label>
    <select name="area_type" class="form-select form-select-sm">@foreach ($areas as $k => $a)<option value="{{ $k }}" @selected(($c->area_type ?? old('area_type', 'iujp')) === $k)>{{ $a['label'] }} - {{ $a['full'] }}</option>@endforeach</select>
</div>
<div class="col-6 col-md-4"><label class="form-label small mb-1">Nomor izin (opsional)</label><input type="text" name="permit_no" value="{{ $c->permit_no ?? old('permit_no') }}" class="form-control form-control-sm" maxlength="80"></div>
<div class="col-12 col-md-4"><label class="form-label small mb-1">PIC HSE kontraktor</label><input type="text" name="pic_name" value="{{ $c->pic_name ?? old('pic_name') }}" class="form-control form-control-sm" maxlength="120"></div>
<div class="col-6 col-md-4"><label class="form-label small mb-1">Email PIC (penerima permintaan LPI)</label><input type="email" name="pic_email" value="{{ $c->pic_email ?? old('pic_email') }}" class="form-control form-control-sm" maxlength="160"></div>
<div class="col-6 col-md-4"><label class="form-label small mb-1">Telepon</label><input type="text" name="pic_phone" value="{{ $c->pic_phone ?? old('pic_phone') }}" class="form-control form-control-sm" maxlength="40"></div>
