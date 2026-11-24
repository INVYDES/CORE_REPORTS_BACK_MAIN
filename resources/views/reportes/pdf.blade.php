<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $reporte->folio }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
        .header { width: 100%; border-bottom: 2px solid #1d4ed8; padding-bottom: 10px; margin-bottom: 14px; }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo { max-height: 58px; max-width: 150px; }
        .company-name { font-size: 16px; font-weight: bold; color: #0f172a; }
        .company-meta { font-size: 9px; color: #64748b; margin-top: 2px; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 16px; color: #1d4ed8; }
        .doc-title .folio { font-size: 11px; font-weight: bold; color: #0f172a; }
        .meta-table, .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .meta-table td { width: 25%; padding: 6px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        .meta-table .label { display: block; font-size: 8px; text-transform: uppercase; color: #64748b; letter-spacing: .04em; }
        .meta-table .value { font-size: 10px; font-weight: bold; color: #0f172a; }
        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #1d4ed8; margin: 14px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        .box { border: 1px solid #e2e8f0; padding: 8px; min-height: 34px; white-space: pre-wrap; line-height: 1.45; }
        .data-table th { background: #f1f5f9; color: #334155; font-size: 8px; text-transform: uppercase; padding: 6px; border: 1px solid #e2e8f0; text-align: left; }
        .data-table td { padding: 5px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
        .data-table .right { text-align: right; }
        .totals { width: 40%; margin-left: 60%; border-collapse: collapse; }
        .totals td { padding: 4px 6px; border: 1px solid #e2e8f0; }
        .totals .label { color: #475569; }
        .totals .value { text-align: right; font-weight: bold; }
        .evidence-grid { width: 100%; border-collapse: collapse; }
        .evidence-grid td { width: 50%; padding: 5px; vertical-align: top; }
        .evidence-grid img { width: 100%; max-height: 230px; border: 1px solid #e2e8f0; }
        .evidence-caption { font-size: 8px; color: #64748b; margin-top: 2px; }
        .doc-list { margin: 0; padding-left: 16px; }
        .doc-list li { margin-bottom: 3px; }
        .signatures { width: 100%; margin-top: 34px; border-collapse: collapse; }
        .signatures td { width: 50%; text-align: center; padding-top: 26px; }
        .sign-line { border-top: 1px solid #475569; margin: 0 20px; padding-top: 4px; font-size: 9px; color: #475569; }
        .footer { position: fixed; bottom: -8px; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; }
        .muted { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    @if($logo)
                        <img src="{{ $logo }}" alt="Logo" class="logo">
                    @endif
                    <div class="company-name">{{ $empresa?->nombre ?? 'CoreReports' }}</div>
                    <div class="company-meta">
                        @if($empresa?->rfc) RFC: {{ $empresa->rfc }} @endif
                        @if($empresa?->telefono) &nbsp;·&nbsp; Tel: {{ $empresa->telefono }} @endif
                    </div>
                </td>
                <td class="doc-title">
                    <h1>Reporte de Trabajo</h1>
                    <div class="folio">{{ $reporte->folio }}</div>
                    <div class="company-meta">{{ $reporte->tipo }} · {{ $reporte->categoria }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td><span class="label">Estatus</span><span class="value">{{ $reporte->estatus }}</span></td>
            <td><span class="label">Origen</span><span class="value">{{ $reporte->ticket?->folio ?? $reporte->servicio?->folio ?? 'Reporte libre' }}</span></td>
            <td><span class="label">Area</span><span class="value">{{ $reporte->area?->nombre ?? '—' }}</span></td>
            <td><span class="label">Equipo</span><span class="value">{{ $reporte->equipo?->nombre ?? '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Responsable</span><span class="value">{{ $reporte->responsable ? trim($reporte->responsable->nombre.' '.$reporte->responsable->apellidos) : '—' }}</span></td>
            <td><span class="label">Elaboro</span><span class="value">{{ $reporte->creador ? trim($reporte->creador->nombre.' '.$reporte->creador->apellidos) : '—' }}</span></td>
            <td><span class="label">Inicio</span><span class="value">{{ $reporte->fecha_inicio?->format('d/m/Y H:i') ?? '—' }}</span></td>
            <td><span class="label">Fin</span><span class="value">{{ $reporte->fecha_fin?->format('d/m/Y H:i') ?? '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Salida a sitio</span><span class="value">{{ $reporte->hora_salida?->format('d/m/Y H:i') ?? '—' }}</span></td>
            <td><span class="label">Llegada a sitio</span><span class="value">{{ $reporte->hora_llegada?->format('d/m/Y H:i') ?? '—' }}</span></td>
            <td><span class="label">Regreso a base</span><span class="value">{{ $reporte->hora_regreso?->format('d/m/Y H:i') ?? '—' }}</span></td>
            <td><span class="label">Retrabajo</span><span class="value">{{ $reporte->es_retrabajo ? 'Si' : 'No' }}</span></td>
        </tr>
    </table>

    <h2>Actividades realizadas</h2>
    <div class="box">{{ $reporte->desarrollo ?: 'Sin descripcion registrada.' }}</div>

    <h2>Personal ejecutor</h2>
    <div class="box">
        @php
            $ejecutores = $reporte->ejecutores
                ->map(fn ($e) => $e->usuario ? trim($e->usuario->nombre.' '.$e->usuario->apellidos) : null)
                ->filter()
                ->implode(', ');
        @endphp
        {{ $ejecutores !== '' ? $ejecutores : 'Sin ejecutores registrados.' }}
    </div>

    <h2>Materiales utilizados</h2>
    @if($reporte->materiales->isNotEmpty())
        <table class="data-table">
            <thead>
                <tr>
                    <th>Material</th>
                    <th class="right">Cantidad</th>
                    <th class="right">Costo unitario</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reporte->materiales as $item)
                    <tr>
                        <td>{{ $item->material?->nombre ?? 'Material #'.$item->material_id }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 2, '.', ','), '0'), '.') }}</td>
                        <td class="right">${{ number_format((float) $item->costo_unitario, 2, '.', ',') }}</td>
                        <td class="right">${{ number_format((float) $item->subtotal, 2, '.', ',') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="box muted">No se registraron materiales.</div>
    @endif

    <table class="totals">
        <tr><td class="label">Mano de obra</td><td class="value">${{ number_format((float) $reporte->costo_mano_obra, 2, '.', ',') }}</td></tr>
        <tr><td class="label">Materiales</td><td class="value">${{ number_format((float) $reporte->costo_materiales, 2, '.', ',') }}</td></tr>
        <tr><td class="label"><strong>Total</strong></td><td class="value">${{ number_format((float) $reporte->costo_mano_obra + (float) $reporte->costo_materiales, 2, '.', ',') }}</td></tr>
    </table>

    <h2>Evidencia fotografica</h2>
    @php
        // Solo se incrustan imagenes leidas del disco propio (data-URI); las
        // referencias remotas se omiten para no descargar URLs desde el PDF.
        $imagenes = $reporte->evidencias
            ->map(fn ($e) => $e->pdfImage())
            ->filter()
            ->values();
        $documentos = $reporte->evidencias->reject(fn ($e) => $e->isImageEvidence())->values();
    @endphp

    @if($imagenes->isNotEmpty())
        <table class="evidence-grid">
            @foreach($imagenes->chunk(2) as $fila)
                <tr>
                    @foreach($fila as $imagen)
                        <td>
                            <img src="{{ $imagen }}" alt="Evidencia">
                        </td>
                    @endforeach
                    @if($fila->count() === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @else
        <div class="box muted">No se adjuntaron fotografias.</div>
    @endif

    @if($documentos->isNotEmpty())
        <h2>Documentacion adjunta</h2>
        <ul class="doc-list">
            @foreach($documentos as $documento)
                <li>{{ basename(parse_url($documento->url, PHP_URL_PATH) ?: $documento->url) }} ({{ $documento->tipo_archivo ?: 'archivo' }})</li>
            @endforeach
        </ul>
    @endif

    <h2>Conformidad</h2>
    <table class="meta-table">
        <tr>
            <td><span class="label">Estatus</span><span class="value">{{ $reporte->conformidad_estatus ?: 'Pendiente' }}</span></td>
            <td><span class="label">Firmado por</span><span class="value">{{ $reporte->conformidad_firmado_por ?: '—' }}</span></td>
            <td><span class="label">Fecha</span><span class="value">{{ $reporte->conformidad_fecha?->format('d/m/Y H:i') ?? '—' }}</span></td>
            <td><span class="label">FTFR</span><span class="value">{{ $reporte->ftfr ? 'Si' : 'No' }}</span></td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="sign-line">Firma del responsable</div></td>
            <td><div class="sign-line">Firma del cliente</div></td>
        </tr>
    </table>

    <div class="footer">
        Generado por CoreReports el {{ $generado->format('d/m/Y H:i') }}
    </div>
</body>
</html>
