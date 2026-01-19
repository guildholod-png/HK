<?php
require_once __DIR__ . '/../bootstrap.php';

$file = basename($_GET['file'] ?? '');
$path = __DIR__ . '/../storage/' . $file;

if (!$file || !file_exists($path)) {
    http_response_code(404);
    echo 'Файл не найден.';
    exit;
}

$extension = pathinfo($file, PATHINFO_EXTENSION);
$contentTypes = [
    'xls' => 'application/vnd.ms-excel',
    'pdf' => 'application/pdf',
];

header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $file . '"');
readfile($path);
