<?php

function generate_xls(string $path, array $rows, array $header): void
{
    $output = implode("\t", $header) . "\n";
    foreach ($rows as $row) {
        $output .= implode("\t", $row) . "\n";
    }
    file_put_contents($path, $output);
}

function generate_pdf(string $path, string $title, array $rows, array $header): void
{
    $content = $title . "\n\n";
    $content .= implode(' | ', $header) . "\n";
    $content .= str_repeat('-', 80) . "\n";
    foreach ($rows as $row) {
        $content .= implode(' | ', $row) . "\n";
    }

    $pdf = "%PDF-1.4\n";
    $objects = [];

    $stream = "BT\n/F1 12 Tf\n72 720 Td\n";
    $lines = explode("\n", $content);
    foreach ($lines as $index => $line) {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        $yOffset = $index * 14;
        $stream .= "0 -14 Td ({$escaped}) Tj\n";
    }
    $stream .= "ET";

    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    $offsets = [];
    $currentOffset = strlen($pdf);
    foreach ($objects as $object) {
        $offsets[] = $currentOffset;
        $pdf .= $object;
        $currentOffset = strlen($pdf);
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

    file_put_contents($path, $pdf);
}
