@extends('emails.layout')

@section('title', $notif->asunto)

@section('content')
  <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">{{ $notif->titulo ?? $notif->asunto }}</h1>
  <p style="margin:0 0 16px;">
    <span style="display:inline-block;padding:4px 9px;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;">
      {{ $notif->referencia_tipo }} #{{ $notif->referencia_id }}
    </span>
  </p>
  @if ($notif->cuerpo)
    <div style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#334155;white-space:pre-wrap;">{{ $notif->cuerpo }}</div>
  @endif
  <p style="margin:0;">
    <a href="{{ config('correo.frontend_url') }}/app/dashboard" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1d4ed8;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">
      Abrir {{ config('app.name') }}
    </a>
  </p>
  <p style="margin:22px 0 0;font-size:12px;color:#64748b;">
    Dependencia #{{ $notif->dependencia_id }} · {{ $notif->created_at }}
  </p>
@endsection
