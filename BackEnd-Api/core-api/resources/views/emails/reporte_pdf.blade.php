<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte de Servicio</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #1e293b;
      background-color: #f8fafc;
      margin: 0;
      padding: 0;
      line-height: 1.5;
    }
    .container {
      max-width: 600px;
      margin: 30px auto;
      background-color: #ffffff;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .header {
      background-color: #0f172a;
      color: #ffffff;
      padding: 24px;
      text-align: left;
    }
    .header h1 {
      margin: 0;
      font-size: 20px;
      font-weight: 700;
      letter-spacing: -0.5px;
    }
    .header p {
      margin: 4px 0 0;
      color: #94a3b8;
      font-size: 13px;
    }
    .content {
      padding: 24px;
    }
    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 9999px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-success {
      background-color: #dcfce7;
      color: #15803d;
    }
    .badge-info {
      background-color: #e0f2fe;
      color: #0369a1;
    }
    .badge-warning {
      background-color: #fef3c7;
      color: #b45309;
    }
    .meta-card {
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 16px;
      margin: 20px 0;
    }
    .meta-row {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      border-bottom: 1px solid #edf2f7;
      font-size: 13px;
    }
    .meta-row:last-child {
      border-bottom: none;
    }
    .meta-label {
      color: #64748b;
      font-weight: 600;
      width: 40%;
    }
    .meta-value {
      color: #0f172a;
      font-weight: 500;
      width: 60%;
      text-align: right;
    }
    .message-box {
      background-color: #eff6ff;
      border-left: 4px solid #3b82f6;
      padding: 12px 16px;
      margin: 20px 0;
      font-size: 13px;
      color: #1e40af;
      border-radius: 0 4px 4px 0;
    }
    .footer {
      background-color: #f8fafc;
      border-top: 1px solid #e2e8f0;
      padding: 16px 24px;
      text-align: center;
      font-size: 11px;
      color: #94a3b8;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>{{ $empresa->nombre ?? 'CoreReports' }}</h1>
      <p>Reporte Oficial de Trabajo y Mantenimiento</p>
    </div>

    <div class="content">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <span style="font-size: 18px; font-weight: 700; color: #0f172a;">Folio: {{ $reporte->folio }}</span>
        @if($reporte->estatus === 'finalizado')
          <span class="badge badge-success">Finalizado</span>
        @elseif($reporte->estatus === 'parcial' || $reporte->estatus === 'abierto')
          <span class="badge badge-warning">En Proceso</span>
        @else
          <span class="badge badge-info">{{ ucfirst($reporte->estatus ?? 'Generado') }}</span>
        @endif
      </div>

      <p style="margin-top: 0; font-size: 14px; color: #334155;">
        Se ha emitido el informe de actividades técnicas correspondiente a la orden de trabajo.
      </p>

      @if(!empty($comentario))
        <div class="message-box">
          <strong>Nota adicional:</strong> {{ $comentario }}
        </div>
      @endif

      <div class="meta-card">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
          <tr>
            <td style="padding: 5px 0; color: #64748b; font-weight: 600;">Fecha de Elaboración:</td>
            <td style="padding: 5px 0; text-align: right; color: #0f172a;">
              {{ $reporte->fecha_inicio ? $reporte->fecha_inicio->format('d/m/Y') : ($reporte->created_at ? $reporte->created_at->format('d/m/Y') : now()->format('d/m/Y')) }}
            </td>
          </tr>
          <tr>
            <td style="padding: 5px 0; color: #64748b; font-weight: 600;">Categoría:</td>
            <td style="padding: 5px 0; text-align: right; color: #0f172a;">
              {{ ucfirst($reporte->categoria ?? 'Servicio General') }}
            </td>
          </tr>
          @if($reporte->area)
          <tr>
            <td style="padding: 5px 0; color: #64748b; font-weight: 600;">Área / Ubicación:</td>
            <td style="padding: 5px 0; text-align: right; color: #0f172a;">
              {{ $reporte->area->nombre }}
            </td>
          </tr>
          @endif
          @if($reporte->creador)
          <tr>
            <td style="padding: 5px 0; color: #64748b; font-weight: 600;">Técnico Responsable:</td>
            <td style="padding: 5px 0; text-align: right; color: #0f172a;">
              {{ trim($reporte->creador->nombre . ' ' . $reporte->creador->apellidos) }}
            </td>
          </tr>
          @endif
          @if($reporte->ticket)
          <tr>
            <td style="padding: 5px 0; color: #64748b; font-weight: 600;">Ticket Vinculado:</td>
            <td style="padding: 5px 0; text-align: right; color: #0f172a;">
              {{ $reporte->ticket->folio }} - {{ $reporte->ticket->asunto }}
            </td>
          </tr>
          @elseif($reporte->servicio)
          <tr>
            <td style="padding: 5px 0; color: #64748b; font-weight: 600;">Servicio Vinculado:</td>
            <td style="padding: 5px 0; text-align: right; color: #0f172a;">
              {{ $reporte->servicio->folio }} - {{ $reporte->servicio->titulo }}
            </td>
          </tr>
          @endif
        </table>
      </div>

      <div style="background-color: #f1f5f9; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 8px;">
        <span>📎 <strong>Documento Adjunto:</strong> Encontrarás adjunto el archivo PDF con el reporte completo y la evidencia fotográfica correspondiente.</span>
      </div>
    </div>

    <div class="footer">
      Este es un correo automático emitido desde la plataforma CoreReports.<br>
      © {{ date('Y') }} {{ $empresa->nombre ?? 'CoreReports' }}. Todos los derechos reservados.
    </div>
  </div>
</body>
</html>

