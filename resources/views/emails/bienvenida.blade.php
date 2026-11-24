@extends('emails.layout')

@section('title', 'Bienvenido')

@section('content')
  <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Bienvenido a {{ config('app.name') }}</h1>
  <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">
    Hola {{ $usuario->nombre }}, tu cuenta ya está creada.
  </p>
  @if ($licencia)
    <div style="margin:20px 0;padding:16px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#64748b;">Plan activo</div>
      <div style="margin-top:6px;font-size:17px;font-weight:700;color:#0f172a;">{{ $licencia->nombre }}</div>
      <div style="margin-top:4px;font-size:13px;color:#475569;">
        {{ $licencia->max_usuarios }} usuarios · {{ $licencia->max_reportes_mensuales }} reportes al mes
      </div>
    </div>
  @endif
  <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#334155;">
    Ya puedes administrar tickets, servicios, reportes y a tu equipo desde el panel.
  </p>
  <p style="margin:0;">
    <a href="{{ config('correo.frontend_url') }}/login" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1d4ed8;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">
      Ir al panel
    </a>
  </p>
@endsection
