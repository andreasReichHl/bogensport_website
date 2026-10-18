<?php

/**
 * POST (JSON): submits an online membership application.
 *
 * Validates and stores it as "pending"; it only becomes binding once the confirmation link
 * from the mail is used (double opt-in). Responses:
 *   201 {"ok": true}                       stored, confirmation mail sent
 *   422 {"errors": {"field.path": "..."}}  validation failed
 *   429 {"error": "rate_limited"}
 *   503 {"error": "unavailable"}
 */

require_once __DIR__ . '/mitglieder/bootstrap.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/validation.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/applications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function membership_respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    membership_respond(405, ['error' => 'method_not_allowed']);
}

if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    // Also blocks cross-site plain HTML form posts (they can't send application/json).
    membership_respond(415, ['error' => 'unsupported_media_type']);
}

$rawBody = file_get_contents('php://input', length: 64 * 1024);
$input = json_decode((string)$rawBody, true);

if (!is_array($input)) {
    membership_respond(400, ['error' => 'invalid_json']);
}

// Honeypot: a field hidden from humans. Bots that fill it get a fake success.
if (trim((string)($input['website'] ?? '')) !== '') {
    membership_respond(201, ['ok' => true]);
}

// Stored times are German local time (shown to the board and printed on the PDF), independent
// of the server's PHP default time zone.
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));

try {
    [$application, $errors] = membership_validate_application($input, $now);

    if ($application === null) {
        membership_respond(422, ['errors' => $errors]);
    }

    $stored = membership_store_application(
        $application,
        membership_confirmation_email($application),
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        $now
    );

    // TODO(OM Schritt 3): send the confirmation mail instead of logging the link.
    if (member_area_is_local_environment()) {
        error_log("OM {$stored['reference']}: /mitglied-werden/bestaetigen?token={$stored['token']}");
    }

    membership_respond(201, ['ok' => true]);
} catch (MembershipRateLimitException) {
    membership_respond(429, ['error' => 'rate_limited']);
} catch (EasyvereinTokenException | EasyvereinApiException | MemberAreaDatabaseException | PDOException | MembershipConfigException $error) {
    error_log('beitritt-absenden.php failed: ' . $error->getMessage());
    membership_respond(503, ['error' => 'unavailable']);
}
