<?php

declare(strict_types=1);

// Router for PHP's built-in server: echoes the request so StreamTransport can be tested offline.
$status = is_string($_GET['status'] ?? null) ? (int) $_GET['status'] : 200;
http_response_code($status);
header('Content-Type: text/plain');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'soapAction' => $_SERVER['HTTP_SOAPACTION'] ?? null,
    'contentType' => $_SERVER['CONTENT_TYPE'] ?? null,
    'body' => file_get_contents('php://input'),
]);
