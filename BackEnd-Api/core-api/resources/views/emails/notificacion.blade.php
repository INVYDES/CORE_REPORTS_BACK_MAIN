<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;color:#1e293b} .header{background:#0f172a;color:#fff;padding:16px} .badge{display:inline-block;background:#3b82f6;color:#fff;padding:4px 8px;border-radius:4px;font-size:12px}</style></head>
<body>
<div class="header"><strong>coreReports</strong> - {{ $notif->tipo }}</div>
<div style="padding:20px">
<h2>{{ $notif->titulo ?? $notif->asunto }}</h2>
<p><span class="badge">{{ $notif->referencia_tipo }} #{{ $notif->referencia_id }}</span></p>
<div style="margin:16px 0;white-space:pre-wrap">{{ $notif->cuerpo }}</div>
<p style="color:#64748b;font-size:12px">Dependencia #{{ $notif->dependencia_id }} - {{ $notif->created_at }}</p>
</div>
</body>
</html>
