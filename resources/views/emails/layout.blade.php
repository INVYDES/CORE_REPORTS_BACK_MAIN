<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
          <tr>
            <td style="background:#0f172a;padding:22px 28px;">
              <div style="font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-0.4px;">{{ config('app.name') }}</div>
              <div style="margin-top:4px;font-size:12px;color:#94a3b8;">Gestión de servicios y reportes</div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              @yield('content')
            </td>
          </tr>
          <tr>
            <td style="padding:18px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;line-height:1.5;">
              Este correo se envió automáticamente. Si no reconoces esta actividad, puedes ignorarlo o contactar a soporte.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
