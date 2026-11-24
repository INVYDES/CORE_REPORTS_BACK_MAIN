@extends('emails.layout')

@section('title', 'Pago confirmado')

@section('content')
  <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Pago confirmado</h1>
  <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">
    Hola {{ $usuario->nombre }}, recibimos correctamente el pago de tu licencia.
  </p>
  <div style="margin:20px 0;padding:16px;border:1px solid #bbf7d0;border-radius:10px;background:#f0fdf4;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#334155;">
      <tr>
        <td style="padding:4px 0;">Plan</td>
        <td style="padding:4px 0;text-align:right;font-weight:700;color:#0f172a;">{{ $licencia->nombre }}</td>
      </tr>
      <tr>
        <td style="padding:4px 0;">Monto</td>
        <td style="padding:4px 0;text-align:right;font-weight:700;color:#0f172a;">${{ number_format($monto, 2) }} MXN</td>
      </tr>
      <tr>
        <td style="padding:4px 0;">Vigencia hasta</td>
        <td style="padding:4px 0;text-align:right;font-weight:700;color:#0f172a;">{{ $dependencia->fecha_expiracion?->format('d/m/Y') ?? 'Sin fecha' }}</td>
      </tr>
      @if ($referencia)
        <tr>
          <td style="padding:4px 0;">Referencia</td>
          <td style="padding:4px 0;text-align:right;color:#475569;">{{ $referencia }}</td>
        </tr>
      @endif
    </table>
  </div>
  <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#334155;">
    Tu licencia ya está activa. Gracias por confiar en {{ config('app.name') }}.
  </p>
  <p style="margin:0;">
    <a href="{{ config('correo.frontend_url') }}/app/licencia" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1d4ed8;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">
      Ver mi licencia
    </a>
  </p>
@endsection
