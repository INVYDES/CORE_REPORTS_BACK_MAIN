<?php

namespace App\Services;

use App\Models\Reporte;
use Symfony\Component\HttpFoundation\Response;

class ReportePdfService
{
    private array $pages = [];
    private int $currentPage = -1;
    private float $pageWidth = 612.0;
    private float $pageHeight = 792.0;
    private float $marginLeft = 36.0;
    private float $contentWidth = 540.0;
    private ?string $cachedPdf = null;

    public function __construct(private Reporte $reporte) {}

    public function output(): string
    {
        if ($this->cachedPdf !== null) {
            return $this->cachedPdf;
        }

        $this->build();
        $this->cachedPdf = $this->compile();

        return $this->cachedPdf;
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        $content = $this->output();

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($content),
        ]);
    }

    private function newPage(): void
    {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
    }

    private function append(string $cmd): void
    {
        if ($this->currentPage < 0) {
            $this->newPage();
        }
        $this->pages[$this->currentPage] .= $cmd."\n";
    }

    private function escape(string $text): string
    {
        // Convertir a Windows-1252 para fuentes Type 1 (Helvetica) con soporte de acentos en español
        $win = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        if ($win === false || $win === '') {
            $win = utf8_decode($text);
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $win);
    }

    private function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lw = 1.0): void
    {
        $cmd = 'q ';
        $hasFill = is_array($fill) && count($fill) >= 3;
        $hasStroke = is_array($stroke) && count($stroke) >= 3;

        if ($hasStroke) {
            $cmd .= sprintf('%.3F %.3F %.3F RG %.2F w ', $stroke[0], $stroke[1], $stroke[2], $lw);
        }
        if ($hasFill) {
            $cmd .= sprintf('%.3F %.3F %.3F rg ', $fill[0], $fill[1], $fill[2]);
        }
        $cmd .= sprintf('%.2F %.2F %.2F %.2F re ', $x, $y, $w, $h);

        if ($hasFill && $hasStroke) {
            $cmd .= 'B Q';
        } elseif ($hasFill) {
            $cmd .= 'f Q';
        } elseif ($hasStroke) {
            $cmd .= 'S Q';
        } else {
            $cmd .= 'n Q';
        }

        $this->append($cmd);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $stroke = [0, 0, 0], float $lw = 1.0): void
    {
        $cmd = sprintf('q %.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S Q',
            $stroke[0], $stroke[1], $stroke[2], $lw, $x1, $y1, $x2, $y2);
        $this->append($cmd);
    }

    private function text(float $x, float $y, string $str, string $font = 'F1', float $size = 9.0, array $rgb = [0, 0, 0]): void
    {
        $escaped = $this->escape($str);
        $cmd = sprintf('BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $font, $size, $rgb[0], $rgb[1], $rgb[2], $x, $y, $escaped);
        $this->append($cmd);
    }

    private function wrapText(string $text, int $maxChars = 100): array
    {
        $lines = [];
        $paragraphs = preg_split('/\r?\n+/', trim($text));
        $first = true;
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') continue;
            if (!$first) {
                $lines[] = '';
            }
            $first = false;
            $words = preg_split('/\s+/', $p);
            $cur = '';
            foreach ($words as $w) {
                if ($w === '') continue;
                if ($cur === '') {
                    $cur = $w;
                } elseif (mb_strlen($cur . ' ' . $w) <= $maxChars) {
                    $cur .= ' ' . $w;
                } else {
                    $lines[] = $cur;
                    $cur = $w;
                }
            }
            if ($cur !== '') {
                $lines[] = $cur;
            }
        }
        return $lines;
    }

    private function drawSectionBox(string $title, string $content, float &$y, float $minY = 70.0): void
    {
        $raw = trim($content);
        if ($raw === '') {
            $raw = 'Sin información.';
        }

        $paragraphs = preg_split('/\r?\n+/', $raw);
        $paragraphs = array_filter(array_map('trim', $paragraphs));
        if (empty($paragraphs)) {
            $paragraphs = ['Sin información.'];
        }

        $allLines = [];
        $charsPerLine = 100;
        foreach ($paragraphs as $p) {
            $pLines = $this->wrapText($p, $charsPerLine);
            foreach ($pLines as $l) {
                $allLines[] = $l;
            }
            $allLines[] = ''; // Espacio entre párrafos
        }
        if (!empty($allLines) && end($allLines) === '') {
            array_pop($allLines);
        }

        $lineHeight = 12.5;
        $totalHeight = 18.0 + (count($allLines) * $lineHeight) + 8.0;

        // Si no cabe en la página actual, saltar a nueva página
        if ($y - $totalHeight < $minY) {
            $this->newPage();
            $y = 740.0;
        }

        $x = $this->marginLeft;

        // Título de la sección en negrita negro mayúsculas (igual que en Vista Previa)
        $this->text($x, $y - 12.0, mb_strtoupper($title), 'F2', 9.5, [0, 0, 0]);
        $y -= 16.0;

        // Párrafos limpios con texto oscuro sin recuadros azules
        $textY = $y - 10.0;
        foreach ($allLines as $line) {
            if ($textY < $minY) {
                $this->newPage();
                $y = 740.0;
                $textY = $y - 10.0;
            }
            if ($line !== '') {
                $this->text($x, $textY, $line, 'F1', 8.5, [0.12, 0.16, 0.23]);
            }
            $textY -= $lineHeight;
        }

        $y = $textY - 6.0;
    }

    private function build(): void
    {
        $this->reporte->loadMissing([
            'creador', 'responsable', 'ticket', 'servicio', 'area', 'equipo',
            'ejecutores.usuario', 'materiales.material', 'evidencias', 'dependencia'
        ]);

        $rawDesarrollo = (string) ($this->reporte->desarrollo ?? '');
        $meta = [];
        if (preg_match('/<!--CORE_META:([\s\S]*?)-->\s*$/', $rawDesarrollo, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $cleanDesarrollo = trim((string) preg_replace('/\n*<!--CORE_META:([\s\S]*?)-->\s*$/', '', $rawDesarrollo));

        $empresa = $this->reporte->dependencia;
        $empresaNombre = $empresa?->nombre ?: 'CoreReports';
        $folio = (string) preg_replace('/^(TKT|REP|SRV)-20(\d{2}\d*-)/i', '$1-$2', (string) ($this->reporte->folio ?: 'REP-000'));
        $creador = $this->reporte->creador ? trim($this->reporte->creador->nombre.' '.$this->reporte->creador->apellidos) : '—';
        $puesto = $meta['cargo'] ?? ($this->reporte->creador?->puesto ?? 'Especialista Técnico');
        $numEmpleado = $this->reporte->creador?->numero_empleado ?? str_pad((string) ($this->reporte->creador?->id ?? '001'), 3, '0', STR_PAD_LEFT);
        $correo = $this->reporte->creador?->email ?? $empresa?->email ?? '—';
        $fecha = $this->reporte->fecha_inicio ? $this->reporte->fecha_inicio->format('Y-m-d') : ($this->reporte->created_at ? $this->reporte->created_at->format('Y-m-d') : '—');
        $origenFolioRaw = preg_replace('/^(Ticket|Servicio):\s*/i', '', (string) ($this->reporte->ticket?->folio ?? $this->reporte->servicio?->folio ?? '0000'));
        $origenFolio = (string) preg_replace('/^(TKT|REP|SRV)-20(\d{2}\d*-)/i', '$1-$2', (string) $origenFolioRaw);
        $areaNombre = $meta['areaTexto'] ?? ($this->reporte->area?->nombre ?? 'Área General');
        $responsableArea = $meta['responsable'] ?? ($this->reporte->responsable ? trim($this->reporte->responsable->nombre.' '.$this->reporte->responsable->apellidos) : '—');

        $categoria = ucfirst((string) ($this->reporte->categoria ?? 'General'));
        $falla = $meta['falla'] ?? $meta['fallaLibre'] ?? ($this->reporte->ticket?->asunto ?? 'Sin falla reportada.');
        $conclusion = $meta['conclusion'] ?? ($this->reporte->estatus === 'finalizado' ? 'Trabajo finalizado satisfactoriamente.' : 'Trabajo en proceso, pendiente de finalización.');

        $inicio = $this->reporte->fecha_inicio ? $this->reporte->fecha_inicio->format('H:i') : '--:--';
        $fin = $this->reporte->fecha_fin ? $this->reporte->fecha_fin->format('H:i') : '--:--';
        $totalHoras = '0h 0m';
        if ($this->reporte->fecha_inicio && $this->reporte->fecha_fin) {
            $diffMins = $this->reporte->fecha_inicio->diffInMinutes($this->reporte->fecha_fin);
            $h = floor($diffMins / 60);
            $m = $diffMins % 60;
            $totalHoras = "{$h}h {$m}m";
        }
        $esEnTercero = isset($meta['esEnTercero'])
            ? (bool) $meta['esEnTercero']
            : (bool) ($this->reporte->hora_salida || $this->reporte->hora_llegada || $this->reporte->hora_regreso);
        $salidaBase = $this->reporte->hora_salida ? $this->reporte->hora_salida->format('H:i') : '--:--';
        $llegadaSitio = $this->reporte->hora_llegada ? $this->reporte->hora_llegada->format('H:i') : '--:--';
        $regresoBase = $this->reporte->hora_regreso ? $this->reporte->hora_regreso->format('H:i') : '--:--';
        $arrastreMins = 0;
        if ($this->reporte->hora_salida && $this->reporte->hora_llegada && $this->reporte->hora_llegada->gt($this->reporte->hora_salida)) {
            $arrastreMins += $this->reporte->hora_salida->diffInMinutes($this->reporte->hora_llegada);
        }
        if ($this->reporte->fecha_fin && $this->reporte->hora_regreso && $this->reporte->hora_regreso->gt($this->reporte->fecha_fin)) {
            $arrastreMins += $this->reporte->fecha_fin->diffInMinutes($this->reporte->hora_regreso);
        }
        $tiempoArrastre = sprintf('%dh %dm', (int) floor($arrastreMins / 60), $arrastreMins % 60);

        $this->newPage();
        $x = $this->marginLeft;
        $w = $this->contentWidth;
        $y = 756.0;

        // ================= HEADER INSTITUCIONAL (IDÉNTICO A VISTA PREVIA) =================
        $headerH = 58.0;
        $headerY = $y - $headerH;
        $logoColW = 140.0;

        // Marco exterior negro 2px
        $this->rect($x, $headerY, $w, $headerH, [1, 1, 1], [0, 0, 0], 2.0);
        // Separador vertical entre celda de logo y datos
        $this->line($x + $logoColW, $headerY, $x + $logoColW, $headerY + $headerH, [0, 0, 0], 2.0);

        // Logo o iniciales en celda izquierda
        $this->text($x + 35.0, $headerY + 22.0, mb_substr($empresaNombre, 0, 4), 'F2', 18.0, [0, 0, 0]);

        // Celda derecha:
        $infoX = $x + $logoColW;
        $infoW = $w - $logoColW;

        // 1. Título de la empresa en negro negrita mayúsculas
        $this->text($infoX + 16.0, $headerY + 43.0, mb_strtoupper($empresaNombre), 'F2', 11.5, [0, 0, 0]);
        $this->line($infoX, $headerY + 37.0, $x + $w, $headerY + 37.0, [0, 0, 0], 1.5);

        // 2. Barra de folio en gris #9ca3af (RGB: 0.61, 0.64, 0.69) con texto blanco
        $this->rect($infoX, $headerY + 19.0, $infoW, 18.0, [0.61, 0.64, 0.69], [0, 0, 0], 1.5);
        $this->text($infoX + 16.0, $headerY + 24.5, $folio, 'F2', 10.5, [1, 1, 1]);

        // 3. Subtítulo del reporte
        $this->text($infoX + 16.0, $headerY + 6.0, "REPORTE DE MANTENIMIENTO / ORDEN DE TRABAJO ({$categoria})", 'F2', 8.0, [0, 0, 0]);

        $y = $headerY - 14.0;

        // ================= DATOS GENERALES (SIN RECUADROS AZULES) =================
        $this->text($x, $y - 10.0, 'DATOS GENERALES', 'F2', 10.0, [0, 0, 0]);
        $y -= 16.0;

        $col1 = $x;
        $col2 = $x + 270.0;
        $lineY = $y - 8.0;
        $dy = 13.0;

        $this->text($col1, $lineY, 'Responsable: ' . $creador, 'F1', 8.5, [0, 0, 0]);
        $this->text($col2, $lineY, 'OT/OC/Ticket: ' . $origenFolio, 'F1', 8.5, [0, 0, 0]);
        $lineY -= $dy;

        $this->text($col1, $lineY, 'Puesto: ' . $puesto, 'F1', 8.5, [0, 0, 0]);
        $this->text($col2, $lineY, 'Fecha de solicitud: ' . $fecha, 'F1', 8.5, [0, 0, 0]);
        $lineY -= $dy;

        $this->text($col1, $lineY, 'No. Empleado: ' . $numEmpleado, 'F1', 8.5, [0, 0, 0]);
        $this->text($col2, $lineY, 'Área: ' . $areaNombre, 'F1', 8.5, [0, 0, 0]);
        $lineY -= $dy;

        $this->text($col1, $lineY, 'Fecha: ' . $fecha, 'F1', 8.5, [0, 0, 0]);
        $this->text($col2, $lineY, 'Responsable de Área: ' . $responsableArea, 'F1', 8.5, [0, 0, 0]);
        $lineY -= $dy;

        $this->text($col1, $lineY, 'Correos: ' . $correo, 'F1', 8.5, [0, 0, 0]);

        $y = $lineY - 8.0;

        // Línea separadora horizontal negra (igual a Vista Previa)
        $this->line($x, $y, $x + $w, $y, [0, 0, 0], 1.5);
        $y -= 12.0;

        // ================= SECCIONES DE TEXTO =================
        $this->drawSectionBox('DESCRIPCIÓN DE LA FALLA REPORTADA', $falla, $y);
        $this->drawSectionBox('DESCRIPCIÓN DEL SERVICIO', $cleanDesarrollo ?: 'Sin descripción de actividades.', $y);
        $this->drawSectionBox('CONCLUSIÓN DEL SERVICIO', $conclusion, $y);

        // ================= TIEMPOS =================
        if ($y - 36.0 < 70.0) {
            $this->newPage();
            $y = 740.0;
        }

        if ($esEnTercero) {
            $strTiempos1 = "Tiempos:   Salida Base: {$salidaBase}   Llegada a Sitio: {$llegadaSitio}   Inicio: {$inicio}   Fin: {$fin}   Regreso a Base: {$regresoBase}";
            $strTiempos2 = "Trabajo Neto: {$totalHoras}     Tiempo de Arrastre: {$tiempoArrastre}";
            $this->text($x, $y - 12.0, $strTiempos1, 'F2', 8.5, [0, 0, 0]);
            $this->text($x, $y - 26.0, $strTiempos2, 'F2', 8.5, [0, 0, 0]);
            $y -= 36.0;
        } else {
            $strTiempos = "Tiempos:     Inicio: {$inicio}     Fin: {$fin}     Total: {$totalHoras}";
            $this->text($x, $y - 12.0, $strTiempos, 'F2', 9.0, [0, 0, 0]);
            $y -= 22.0;
        }

        // ================= MATERIALES (SI TIENE) =================
        if ($this->reporte->materiales->isNotEmpty()) {
            $matRows = count($this->reporte->materiales);
            $matTotalH = 16.0 + 14.0 + ($matRows * 12.0);

            if ($y - $matTotalH < 70.0) {
                $this->newPage();
                $y = 740.0;
            }

            // Título
            $this->text($x, $y - 12.0, 'MATERIALES UTILIZADOS', 'F2', 9.5, [0, 0, 0]);
            $y -= 16.0;

            // Encabezado de tabla en gris claro con borde negro
            $this->rect($x, $y - 13.0, $w, 13.0, [0.94, 0.96, 0.98], [0, 0, 0], 1.0);
            $this->text($x + 6.0, $y - 9.5, 'Item', 'F2', 7.5, [0, 0, 0]);
            $this->text($x + 40.0, $y - 9.5, 'Descripción del Material', 'F2', 7.5, [0, 0, 0]);
            $this->text($x + 380.0, $y - 9.5, 'Cantidad', 'F2', 7.5, [0, 0, 0]);
            $this->text($x + 460.0, $y - 9.5, 'Unidad', 'F2', 7.5, [0, 0, 0]);
            $y -= 13.0;

            // Filas con borde negro fino
            foreach ($this->reporte->materiales as $idx => $mat) {
                $itemNum = (string) ($idx + 1);
                $matNom = (string) ($mat->material?->nombre ?? 'Material');
                $matCant = (string) $mat->cantidad;
                $matUni = (string) ($mat->material?->unidad_medida ?? 'Pza');

                $fillRow = ($idx % 2 === 1) ? [0.98, 0.98, 0.99] : [1, 1, 1];
                $this->rect($x, $y - 12.0, $w, 12.0, $fillRow, [0.75, 0.75, 0.75], 0.5);

                $this->text($x + 6.0, $y - 9.0, $itemNum, 'F1', 7.5, [0, 0, 0]);
                $this->text($x + 40.0, $y - 9.0, mb_strimwidth($matNom, 0, 60, '...'), 'F1', 7.5, [0, 0, 0]);
                $this->text($x + 380.0, $y - 9.0, $matCant, 'F1', 7.5, [0, 0, 0]);
                $this->text($x + 460.0, $y - 9.0, $matUni, 'F1', 7.5, [0, 0, 0]);

                $y -= 12.0;
            }
            $y -= 12.0;
        }

        // ================= FIRMAS =================
        if ($y - 75.0 < 55.0) {
            $this->newPage();
            $y = 740.0;
        }

        $sigY = $y - 60.0;
        $sigW = 200.0;

        // Firma Reviso
        $this->text($x + 20.0, $sigY + 38.0, 'Revisó:', 'F2', 9.0, [0, 0, 0]);
        $this->line($x + 20.0, $sigY + 12.0, $x + 20.0 + $sigW, $sigY + 12.0, [0, 0, 0], 1.0);
        $this->text($x + 65.0, $sigY + 2.0, 'NOMBRE Y FIRMA', 'F2', 7.5, [0, 0, 0]);

        // Firma Elaboro
        $sigX2 = $x + $w - $sigW - 20.0;
        $this->text($sigX2, $sigY + 38.0, 'Elaboró:', 'F2', 9.0, [0, 0, 0]);
        $this->line($sigX2, $sigY + 12.0, $sigX2 + $sigW, $sigY + 12.0, [0, 0, 0], 1.0);
        $this->text($sigX2 + 45.0, $sigY + 2.0, 'NOMBRE Y FIRMA', 'F2', 7.5, [0, 0, 0]);

        // ================= FOOTERS =================
        $totalPages = count($this->pages);
        for ($i = 0; $i < $totalPages; $i++) {
            $this->currentPage = $i;
            $pageNumStr = sprintf('Página %d de %d', $i + 1, $totalPages);
            $footerStr = "Generado con CoreReports para {$empresaNombre} — Folio: {$folio}";

            $this->line($x, 32.0, $x + $w, 32.0, [0.85, 0.88, 0.92], 0.8);
            $this->text($x, 22.0, $footerStr, 'F3', 7.0, [0.50, 0.55, 0.60]);
            $this->text($x + $w - 65.0, 22.0, $pageNumStr, 'F3', 7.0, [0.50, 0.55, 0.60]);
        }
    }

    private function compile(): string
    {
        $totalPages = count($this->pages);
        $offsets = [];
        $buf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";

        // Objeto 1: Catálogo
        $offsets[1] = strlen($buf);
        $buf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Objeto 4..6: Fuentes estándar Helvetica
        $offsets[4] = strlen($buf);
        $buf .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        $offsets[5] = strlen($buf);
        $buf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $offsets[6] = strlen($buf);
        $buf .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Páginas y streams:
        // Cada página 'i' (0-indexed) usará:
        // PageObj = 7 + (i * 2)
        // StreamObj = 8 + (i * 2)
        $kids = [];
        for ($i = 0; $i < $totalPages; $i++) {
            $pageObjId = 7 + ($i * 2);
            $kids[] = "{$pageObjId} 0 R";
        }

        // Objeto 2: Pages tree
        $offsets[2] = strlen($buf);
        $kidsStr = implode(' ', $kids);
        $buf .= "2 0 obj\n<< /Type /Pages /Kids [{$kidsStr}] /Count {$totalPages} >>\nendobj\n";

        // Objeto 3: Resources dict común
        $offsets[3] = strlen($buf);
        $buf .= "3 0 obj\n<< /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R >> /ProcSet [/PDF /Text] >>\nendobj\n";

        for ($i = 0; $i < $totalPages; $i++) {
            $pageObjId = 7 + ($i * 2);
            $streamObjId = 8 + ($i * 2);
            $streamContent = $this->pages[$i];
            $streamLen = strlen($streamContent);

            // Page Object
            $offsets[$pageObjId] = strlen($buf);
            $buf .= "{$pageObjId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources 3 0 R /Contents {$streamObjId} 0 R >>\nendobj\n";

            // Stream Object
            $offsets[$streamObjId] = strlen($buf);
            $buf .= "{$streamObjId} 0 obj\n<< /Length {$streamLen} >>\nstream\n{$streamContent}\nendstream\nendobj\n";
        }

        $totalObjs = 6 + ($totalPages * 2);
        $xrefOffset = strlen($buf);

        $buf .= "xref\n0 " . ($totalObjs + 1) . "\n";
        $buf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjs; $i++) {
            $buf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $buf .= "trailer\n<< /Size " . ($totalObjs + 1) . " /Root 1 0 R >>\n";
        $buf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $buf;
    }
}

