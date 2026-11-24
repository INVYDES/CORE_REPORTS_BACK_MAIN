@extends('emails.layout')

@section('title', 'Tu licencia venció')

@section('content')
  <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Tu licencia venció</h1>
  <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">
    La licencia de <strong>{{ $dependencia->nombre }}</strong> venció el {{ $fecha }} y el acceso a la operación quedó suspendido.
  </p>
  <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#334155;">
    Puedes renovarla en cualquier momento; tu información se conserva.
  </p>
  <p style="margin:0;">
    <a href="{{ config('correo.frontend_url') }}/app/licencia" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1d4ed8;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">
      Renovar ahora
    </a>
  </p>
@endsection
