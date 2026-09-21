<?php

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$file = $_FILES['file'] ?? [];
$received = ($file['error'] ?? null) === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name']);

header('Content-Type: application/json');
echo json_encode([
    'error' => $file['error'] ?? null,
    'size' => $file['size'] ?? null,
    'sha256' => $received ? hash_file('sha256', $file['tmp_name']) : null,
    'temporary_directory' => $received ? realpath(dirname($file['tmp_name'])) : null,
], JSON_THROW_ON_ERROR);
