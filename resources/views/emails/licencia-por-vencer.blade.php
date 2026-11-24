@extends('emails.layout')

@section('title', 'Tu licencia está por vencer')

@section('content')
  <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Tu licencia está por vencer</h1>
  <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">
    La licencia de <strong>{{ $dependencia->nombre }}</strong> vence en
    <strong>{{ $dias }} {{ $dias === 1 ? 'día' : 'días' }}</strong>, el {{ $fecha }}.
  </p>
  <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#334155;">
    Renueva a tiempo para evitar que se interrumpa el acceso a tickets, servicios y reportes.
  </p>
  <p style="margin:0 0 22px;">
    <a href="{{ config('correo.frontend_url') }}/app/licencia" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1d4ed8;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">
      Renovar licencia
    </a>
  </p>
  <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;">
    Si ya realizaste el pago, puedes ignorar este recordatorio.
  </p>
@endsection
