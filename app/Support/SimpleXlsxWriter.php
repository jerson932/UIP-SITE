<?php

namespace App\Support;

use ZipArchive;

/**
 * Generador mínimo de archivos .xlsx (Office Open XML) SIN depender de
 * PhpSpreadsheet/Maatwebsite: este entorno de desarrollo no tiene acceso a
 * Packagist para instalar esas librerías (ni sus varias dependencias
 * transitivas), así que este writer arma a mano el ZIP + XML que Excel
 * espera, usando solo ext-zip (ya incluida en PHP). Soporta una sola hoja,
 * encabezado en negrita y celdas de texto/número — suficiente para un
 * reporte tabular como el de "Reportes". Si en algún momento se agrega
 * PhpSpreadsheet al proyecto (por ejemplo desde una máquina con acceso
 * normal a Packagist), este archivo puede sustituirse sin tocar el resto
 * del código: ReporteController solo llama a generar().
 */
class SimpleXlsxWriter
{
    /**
     * Crea el archivo .xlsx en $rutaDestino.
     *
     * @param  string[]  $encabezados  Nombres de columna (fila 1, en negrita).
     * @param  iterable<array<int, string|int|float|null>>  $filas  Cada fila es un array indexado en el mismo orden que $encabezados. Los valores int/float se escriben como número; todo lo demás como texto.
     */
    public static function generar(string $rutaDestino, array $encabezados, iterable $filas): void
    {
        $zip = new ZipArchive();
        $zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::relsRaiz());
        $zip->addFromString('docProps/core.xml', self::docPropsCore());
        $zip->addFromString('docProps/app.xml', self::docPropsApp());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::hoja($encabezados, $filas));

        $zip->close();
    }

    private static function columna(int $indiceCero): string
    {
        $letras = '';
        $n = $indiceCero + 1;
        while ($n > 0) {
            $resto = ($n - 1) % 26;
            $letras = chr(65 + $resto).$letras;
            $n = intdiv($n - 1, 26);
        }

        return $letras;
    }

    /**
     * Limpia caracteres de control no válidos en XML 1.0 (deja tab/LF/CR) y
     * escapa &, <, > para poder incrustar el texto dentro de <t>...</t>.
     */
    private static function textoSeguro(string $valor): string
    {
        $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $valor) ?? $valor;

        return htmlspecialchars($valor, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function hoja(array $encabezados, iterable $filas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        // Fila 1: encabezado, estilo s="1" (negrita, ver styles()).
        $xml .= '<row r="1">';
        foreach (array_values($encabezados) as $i => $texto) {
            $ref = self::columna($i).'1';
            $xml .= '<c r="'.$ref.'" t="inlineStr" s="1"><is><t xml:space="preserve">'.self::textoSeguro((string) $texto).'</t></is></c>';
        }
        $xml .= '</row>';

        $numFila = 2;
        foreach ($filas as $fila) {
            $xml .= '<row r="'.$numFila.'">';
            foreach (array_values($fila) as $i => $valor) {
                $ref = self::columna($i).$numFila;
                if ($valor === null || $valor === '') {
                    // Celda vacía: se omite (Excel la trata como en blanco).
                    continue;
                }
                if (is_int($valor) || is_float($valor)) {
                    $xml .= '<c r="'.$ref.'"><v>'.$valor.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.self::textoSeguro((string) $valor).'</t></is></c>';
                }
            }
            $xml .= '</row>';
            $numFila++;
        }

        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
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
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private static function relsRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private static function docPropsCore(): string
    {
        $fecha = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>UIP MINGOB</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$fecha.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$fecha.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private static function docPropsApp(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            .'<Application>UIP MINGOB</Application>'
            .'</Properties>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>'
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
        // s="0": estilo por defecto. s="1": negrita, usado en el encabezado.
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
