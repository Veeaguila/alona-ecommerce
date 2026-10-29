<?php

namespace App\Services;

use RuntimeException;
use XMLWriter;
use ZipArchive;

class LogisticsReportExport
{
    public const COLUMNS = ['parcel_id' => 'Parcel ID', 'order_id' => 'Tracking reference', 'buyer_name' => 'Buyer',
        'seller_name' => 'Seller', 'rider_name' => 'Delivery Rider', 'status' => 'Status',
        'delivery_province' => 'Province', 'delivery_municipality' => 'City', 'sorted_at' => 'Sorted at', 'delivered_at' => 'Delivered at'];

    public function write($output, iterable $records, string $format, array $columns = self::COLUMNS, string $sheetName = 'Deliveries'): void
    {
        $rows = (function () use ($records, $columns) {
            yield array_values($columns);
            foreach ($records as $record) yield array_map(fn ($field) => (string) ($record[$field] ?? ''), array_keys($columns));
        })();
        if ($format === 'csv') {
            foreach ($rows as $row) {
                // Spreadsheet applications must treat customer-controlled fields as text.
                $fields = array_keys($columns);
                $row = array_map(function ($value, $index) use ($fields) {
                    // Verified decimal cash fields are numbers; preserve a signed shortfall without a text prefix.
                    if (in_array($fields[$index], ['collected_amount', 'submitted_amount', 'received_amount', 'discrepancy_difference'], true)
                        && preg_match('/\A-?[0-9]+(?:\.[0-9]{1,2})?\z/', $value)) return $value;
                    return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'".$value : $value;
                }, $row, array_keys($row));
                fputcsv($output, $row, ',', '"', '', "\r\n");
            }
            return;
        }
        $sheet = tempnam(sys_get_temp_dir(), 'alona-sheet-');
        $archive = tempnam(sys_get_temp_dir(), 'alona-xlsx-');
        if (!$sheet || !$archive) throw new RuntimeException('Unable to prepare the export.');
        try {
            $xml = new XMLWriter();
            if (!$xml->openUri($sheet)) throw new RuntimeException('Unable to prepare the worksheet.');
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElementNS(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->startElement('sheetData');
            $number = 0;
            foreach ($rows as $row) {
                $xml->startElement('row'); $xml->writeAttribute('r', (string) ++$number);
                foreach ($row as $index => $value) {
                    $xml->startElement('c'); $xml->writeAttribute('r', chr(65 + $index).$number); $xml->writeAttribute('t', 'inlineStr');
                    $xml->startElement('is'); $xml->startElement('t'); $xml->writeAttribute('xml:space', 'preserve');
                    $xml->text(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value));
                    $xml->endElement(); $xml->endElement(); $xml->endElement();
                }
                $xml->endElement();
            }
            $xml->endElement(); $xml->endElement(); $xml->endDocument(); $xml->flush();
            unset($xml);
            $zip = new ZipArchive();
            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to prepare the workbook.');
            try {
                $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
                $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
                $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="1" r:id="rId1"/></sheets></workbook>');
                $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
                $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
            } finally { $zip->close(); }
            $input = fopen($archive, 'rb');
            if (!$input) throw new RuntimeException('Unable to read the workbook.');
            try { stream_copy_to_stream($input, $output); } finally { fclose($input); }
        } finally {
            unset($xml);
            if (is_file($sheet)) unlink($sheet);
            if (is_file($archive)) unlink($archive);
        }
    }
}
