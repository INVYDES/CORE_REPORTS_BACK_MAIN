@extends('emails.layout')

@section('title', 'Verifica tu correo')

@section('content')
  <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Verifica tu correo electrónico</h1>
  <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">
    Hola {{ $usuario->nombre }}, usa el siguiente código para confirmar que este correo te pertenece.
  </p>
  <div style="margin:22px 0;padding:18px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff;text-align:center;">
    <div style="font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#1d4ed8;">Código de verificación</div>
    <div style="margin-top:8px;font-size:34px;font-weight:800;letter-spacing:0.28em;color:#0f172a;">{{ $codigo }}</div>
    <div style="margin-top:8px;font-size:12px;color:#64748b;">Vence el {{ $expira->format('d/m/Y H:i') }}</div>
  </div>
  <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">
    También puedes verificar tu cuenta con un solo clic:
  </p>
  <p style="margin:0 0 22px;">
    <a href="{{ $enlace }}" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1d4ed8;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">
      Verificar mi correo
    </a>
  </p>
  <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;">
    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
    <span style="word-break:break-all;color:#1d4ed8;">{{ $enlace }}</span>
  </p>
@endsection
