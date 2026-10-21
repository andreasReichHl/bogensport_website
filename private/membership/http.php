<?php

require_once __DIR__ . '/config.php';

/**
 * Small helpers shared by the public JSON endpoints of the membership application.
 */

function membership_json_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

function membership_require_enabled(): void
{
    if (!membership_config()['enabled']) {
        membership_respond(404, ['error' => 'not_found']);
    }
}

function membership_respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Reads a JSON request body, enforcing POST + application/json (the content type also blocks
 * cross-site plain HTML form posts, which can't send it).
 */
function membership_read_json_post(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        membership_respond(405, ['error' => 'method_not_allowed']);
    }

    if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        membership_respond(415, ['error' => 'unsupported_media_type']);
    }

    $input = json_decode((string)file_get_contents('php://input', length: 64 * 1024), true);

    if (!is_array($input)) {
        membership_respond(400, ['error' => 'invalid_json']);
    }

    return $input;
}

function membership_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', membership_timezone());
}
