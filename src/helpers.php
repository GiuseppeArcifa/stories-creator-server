<?php

declare(strict_types=1);

/**
 * Sends a JSON response with the given HTTP status code.
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Sends a JSON error response using a consistent payload.
 */
function jsonError(string $message, int $statusCode = 400): void
{
    jsonResponse(['error' => $message], $statusCode);
}

/**
 * Parses the JSON body of the incoming request and returns it as an array.
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Adds very small CORS support for browsers.
 */
function handleCors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

