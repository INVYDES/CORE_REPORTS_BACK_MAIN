<?php

namespace App\Support;

/**
 * Escritor XLSX minimo sin dependencias externas.
 *
 * Genera un libro OOXML de una sola hoja usando entradas ZIP "stored" (sin
 * compresion), por lo que no requiere ext-zip. Pensado para exportaciones
 * tabulares simples: encabezado en negritas y filas con valores escalares.
 */
class XlsxWriter
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public static function build(string $sheetName, array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            return '';
        }

        self::stream($handle, $sheetName, $headers, $rows);
        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /**
     * Escribe el libro directamente a un recurso, sin cargar las filas en
     * memoria. Pensado para exportaciones grandes.
     *
     * @param  resource  $handle
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function stream($handle, string $sheetName, array $headers, iterable $rows): void
    {
        $zip = new ZipStreamWriter($handle);

        $zip->add('[Content_Types].xml', self::contentTypes());
        $zip->add('_rels/.rels', self::rootRels());
        $zip->add('xl/workbook.xml', self::workbook($sheetName));
        $zip->add('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->add('xl/styles.xml', self::styles());

        $columnCount = max(count($headers), 1);

        $zip->addStream('xl/worksheets/sheet1.xml', static function (callable $write) use ($headers, $rows, $columnCount): void {
            $write(self::sheetStart($columnCount));
            $write(self::row(1, $headers, 1));

            $rowNumber = 2;

            foreach ($rows as $row) {
                $write(self::row($rowNumber, $row, 0));
                $rowNumber++;
            }

            $write('</sheetData></worksheet>');
        });

        $zip->finish();
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        $name = self::escape(self::sanitizeSheetName($sheetName));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="2">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    private static function sheetStart(int $columnCount): string
    {
        $cols = '<cols>';
        for ($i = 1; $i <= $columnCount; $i++) {
            $cols .= '<col min="'.$i.'" max="'.$i.'" width="22" customWidth="1"/>';
        }
        $cols .= '</cols>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$cols
            .'<sheetData>';
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private static function row(int $number, array $values, int $style): string
    {
        $cells = '';

        foreach (array_values($values) as $index => $value) {
            $reference = self::columnLetter($index + 1).$number;
            $styleAttr = $style > 0 ? ' s="'.$style.'"' : '';

            if ($value === null || $value === '') {
                $cells .= '<c r="'.$reference.'"'.$styleAttr.'/>';

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$reference.'"'.$styleAttr.' t="n"><v>'.$value.'</v></c>';

                continue;
            }

            $text = self::escape((string) $value);
            $cells .= '<c r="'.$reference.'"'.$styleAttr.' t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
        }

        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    private static function columnLetter(int $number): string
    {
        $letter = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $number = intdiv($number - 1, 26);
        }

        return $letter;
    }

    private static function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name) ?? 'Hoja';
        $name = trim($name);

        return $name === '' ? 'Hoja' : mb_substr($name, 0, 31);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
