<?php
declare(strict_types=1);

namespace App\Application\Reporting;

final class ReportExportService
{
    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $rows
     */
    public function toSpreadsheetXml(array $columns, array $rows, string $title = 'Reporte'): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Worksheet ss:Name="' . $esc(mb_substr($title, 0, 31)) . '"><Table>' . "\n";

        $xml .= '<Row>';
        foreach ($columns as $col) {
            $xml .= '<Cell><Data ss:Type="String">' . $esc((string)$col) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($columns as $col) {
                $val = $row[$col] ?? '';
                $type = is_numeric($val) && $val !== '' ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="' . $type . '">' . $esc((string)$val) . '</Data></Cell>';
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $rows
     */
    public function toPdfHtml(array $columns, array $rows, string $title = 'Reporte'): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;font-size:10px;margin:24px;}'
            . 'h1{font-size:14px;margin-bottom:12px;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;}'
            . 'th{background:#f0f0f0;font-weight:bold;}'
            . '.meta{color:#666;font-size:9px;margin-bottom:8px;}'
            . '</style></head><body>';
        $html .= '<h1>' . $esc($title) . '</h1>';
        $html .= '<div class="meta">Generado: ' . $esc(date('Y-m-d H:i:s')) . '</div>';
        $html .= '<table><thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th>' . $esc((string)$col) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $html .= '<td>' . $esc((string)($row[$col] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        return $html;
    }

    public function renderPdf(string $html): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('Dompdf no está instalado. Ejecute: composer require dompdf/dompdf');
        }

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string)$dompdf->output();
    }
}
