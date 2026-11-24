@extends('emails.layout')

@section('title', 'Restablece tu contraseña')

@section('content')
  <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Restablece tu contraseña</h1>
  <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">
    Hola {{ $usuario->nombre }}, recibimos una solicitud para restablecer la contraseña de tu cuenta.
  </p>
  <p style="margin:0 0 22px;">
    <a href="{{ $enlace }}" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1d4ed8;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">
      Crear una nueva contraseña
    </a>
  </p>
  <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#475569;">
    Este enlace vence en {{ $expiraMinutos }} minutos y solo puede usarse una vez.
  </p>
  <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;">
    Si no solicitaste este cambio, ignora este correo. Tu contraseña actual seguirá funcionando.
  </p>
@endsection
