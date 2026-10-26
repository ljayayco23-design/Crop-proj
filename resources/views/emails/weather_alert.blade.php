@php
    $official   = ($alert['source'] ?? '') === 'pagasa';
    $fieldNames = collect($alert['fields'] ?? [])->pluck('name')->filter()->implode(', ');
    $expires    = !empty($alert['expires_at'])
        ? \Carbon\Carbon::parse($alert['expires_at'])->timezone(config('app.timezone'))->format('M j, Y g:i A')
        : null;
@endphp
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;">
      <tr><td style="background:#0f172a;color:#ffffff;padding:16px 24px;font-size:16px;font-weight:bold;">RICEGUARD AI &middot; Weather Alert</td></tr>
      <tr><td style="padding:24px;">
        @if($isTest)
          <p style="margin:0 0 12px;padding:8px 12px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;font-size:12px;">This is a TEST email. No farmer received it.</p>
        @endif

        <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:bold;background:{{ $official ? '#dbeafe' : '#fef3c7' }};color:{{ $official ? '#1d4ed8' : '#92400e' }};">{{ $alert['label'] ?? 'Alert' }}</span>
        @if(!empty($alert['level']))
          <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:bold;background:#fee2e2;color:#b91c1c;margin-left:4px;">{{ $alert['level'] }}</span>
        @endif

        <h2 style="margin:14px 0 6px;font-size:18px;">{{ $alert['headline'] ?? '' }}</h2>

        @if($fieldNames)
          <p style="margin:0 0 14px;font-size:13px;color:#475569;"><strong>Your affected field(s):</strong> {{ $fieldNames }}</p>
        @endif

        @if(!empty($alert['body']))
          <div style="font-size:14px;line-height:1.55;margin-bottom:14px;">{!! nl2br(e($alert['body'])) !!}</div>
        @endif

        @if(!empty($alert['instruction']))
          <div style="font-size:14px;line-height:1.55;padding:12px;background:#f8fafc;border-left:4px solid #3b82f6;margin-bottom:14px;">{!! nl2br(e($alert['instruction'])) !!}</div>
        @endif

        @if($expires)
          <p style="margin:0 0 6px;font-size:12px;color:#64748b;">Valid until: {{ $expires }}</p>
        @endif

        <p style="margin:12px 0 0;font-size:11px;color:#94a3b8;">
          @if($official)
            Source: PAGASA-DOST public alert feed (publicalert.pagasa.dost.gov.ph).
          @else
            This is a forecast-based notice generated from weather forecast data. It is not an official PAGASA warning.
          @endif
          You can also see recent alerts on your Field Map page.
        </p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>