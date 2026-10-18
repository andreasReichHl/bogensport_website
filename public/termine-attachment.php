<?php

require_once __DIR__ . '/mitglieder/bootstrap.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/easyverein/client.php';

const EASYVEREIN_TERMINE_ATTACHMENT_FIELD_NAME = 'Anhang';

function easyverein_termine_attachment_reject(int $statusCode): never
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

function easyverein_termine_attachment_filename(string $fileUrl): string
{
    $query = [];
    parse_str((string)parse_url($fileUrl, PHP_URL_QUERY), $query);
    $path = (string)($query['path'] ?? '');
    $filename = preg_replace('/^\d+-/', '', basename($path)) ?: 'anhang.pdf';

    // Keep this header-safe: strip anything that isn't a reasonably plain filename character.
    return preg_replace('/[^A-Za-z0-9 ._\-]/', '_', $filename);
}

$assignmentId = filter_input(INPUT_GET, 'assignment', FILTER_VALIDATE_INT);

if (!$assignmentId || $assignmentId <= 0) {
    easyverein_termine_attachment_reject(400);
}

function easyverein_termine_is_expected_failure(Throwable $error): bool
{
    return $error instanceof EasyvereinTokenException
        || $error instanceof EasyvereinApiException
        || member_area_is_database_exception($error);
}

// Resolving and validating the assignment is a separate step from fetching the file itself:
// any failure here - a bad/unknown id, the wrong custom field, a non-public event - is
// indistinguishable from "not found" to the caller, so none of it should surface as anything
// other than a 404 (an attacker probing ids should not be able to tell "doesn't exist" apart
// from "exists but denied" or "upstream lookup briefly failed").
try {
    $assignment = easyverein_api_get("/event-custom-field-assignment/{$assignmentId}");
    $attachmentFields = easyverein_api_get_all('/custom-field', ['name' => EASYVEREIN_TERMINE_ATTACHMENT_FIELD_NAME]);
    $attachmentFieldId = isset($attachmentFields[0]['id']) ? (int)$attachmentFields[0]['id'] : null;

    if (
        $attachmentFieldId === null
        || easyverein_id_from_reference($assignment['custom_field'] ?? null) !== $attachmentFieldId
    ) {
        easyverein_termine_attachment_reject(404);
    }

    $eventId = easyverein_id_from_reference($assignment['event_object'] ?? null);
    $event = $eventId !== null ? easyverein_api_get("/event/{$eventId}") : null;

    if (($event['is_public'] ?? false) !== true) {
        easyverein_termine_attachment_reject(404);
    }

    $fileUrl = (string)($assignment['value'] ?? '');

    // Defense in depth against SSRF: only ever forward our Bearer token to easyVerein's own
    // domain, never wherever an (unexpectedly tampered or malformed) API response might point.
    if ($fileUrl === '' || !str_starts_with($fileUrl, 'https://easyverein.com/')) {
        easyverein_termine_attachment_reject(404);
    }
} catch (Throwable $error) {
    if (!easyverein_termine_is_expected_failure($error)) {
        throw $error;
    }

    error_log('termine-attachment.php lookup failed: ' . $error->getMessage());
    easyverein_termine_attachment_reject(404);
}

// Only a genuine failure to fetch the already-validated file is a real upstream problem (502),
// as opposed to the access-control checks above.
try {
    $response = easyverein_http_get($fileUrl, easyverein_get_valid_token());

    if ($response['status'] !== 200) {
        throw new EasyvereinApiException("Unexpected status {$response['status']} fetching attachment file");
    }
} catch (Throwable $error) {
    if (!easyverein_termine_is_expected_failure($error)) {
        throw $error;
    }

    error_log('termine-attachment.php file fetch failed: ' . $error->getMessage());
    easyverein_termine_attachment_reject(502);
}

$contentType = $response['headers']['content-type'] ?? 'application/octet-stream';
$filename = easyverein_termine_attachment_filename($fileUrl);

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: public, max-age=3600');
echo $response['body'];
