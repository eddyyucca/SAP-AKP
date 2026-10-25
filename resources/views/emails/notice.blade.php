<!DOCTYPE html>
<html lang="id">
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Segoe UI,Arial,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:24px 12px;">
<tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e9edf5;">
        <tr><td style="background:{{ $tone ?? '#203868' }};padding:18px 24px;color:#ffffff;">
            <div style="font-size:12px;opacity:.8;letter-spacing:.04em;text-transform:uppercase;">SAP Safety &middot; AKP</div>
            <div style="font-size:19px;font-weight:700;margin-top:4px;">{{ $heading }}</div>
        </td></tr>
        <tr><td style="padding:22px 24px;font-size:14px;line-height:1.6;">
            @if (!empty($greeting))<p style="margin:0 0 12px;">{{ $greeting }}</p>@endif
            <p style="margin:0 0 16px;">{!! nl2br(e($intro)) !!}</p>

            @if (!empty($rows))
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e9edf5;border-radius:10px;border-collapse:separate;">
                    @foreach ($rows as $label => $value)
                        @if (filled($value))
                            <tr>
                                <td style="padding:8px 12px;width:150px;color:#6b7280;font-size:13px;border-bottom:1px solid #f1f5f9;vertical-align:top;">{{ $label }}</td>
                                <td style="padding:8px 12px;font-size:13px;border-bottom:1px solid #f1f5f9;vertical-align:top;">{!! nl2br(e($value)) !!}</td>
                            </tr>
                        @endif
                    @endforeach
                </table>
            @endif

            @if (!empty($url))
                <p style="text-align:center;margin:24px 0 8px;">
                    <a href="{{ $url }}" style="background:#203868;color:#ffffff;text-decoration:none;padding:11px 22px;border-radius:9px;font-weight:600;font-size:14px;display:inline-block;">{{ $cta ?? 'Buka SAP Safety' }}</a>
                </p>
            @endif
        </td></tr>
        <tr><td style="padding:14px 24px;background:#f8fafc;color:#6b7280;font-size:12px;border-top:1px solid #e9edf5;">
            Email otomatis dari Safety Accountability Program (SAP) &middot; {{ config('ssap.company') }}. Mohon tidak membalas email ini.
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
