<?php

require_once __DIR__ . '/mitglieder/bootstrap.php';
// private/easyverein is always a sibling of private/member-area under the same private/
// directory, so it's resolved from the already robustly-located MEMBER_AREA_PRIVATE_PATH
// rather than duplicating that path-detection logic for a second, independently-relocatable
// anchor (it isn't one).
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/easyverein/client.php';
// Reused for its DOM-based allow-list HTML sanitizer - event descriptions are authored by
// club admins in easyVerein (the same trust level as news posts), but are still run through
// the same sanitizer as defense-in-depth before being rendered as HTML on the public site.
require_once MEMBER_AREA_PRIVATE_PATH . '/news.php';

const EASYVEREIN_TERMINE_CALENDAR_NAME = 'Turniere';
const EASYVEREIN_TERMINE_ATTACHMENT_FIELD_NAME = 'Anhang';

header('Content-Type: application/json; charset=utf-8');

function easyverein_termine_is_expected_failure(Throwable $error): bool
{
    return $error instanceof EasyvereinTokenException
        || $error instanceof EasyvereinApiException
        || member_area_is_database_exception($error);
}

/**
 * Finds the custom-field-assignment (if any) on $event that corresponds to the "Anhang"
 * custom field, and returns the id of that assignment, or null if there is none.
 */
function easyverein_termine_find_attachment_assignment_id(array $event, ?int $attachmentFieldId): ?int
{
    if ($attachmentFieldId === null) {
        return null;
    }

    foreach ($event['custom_fields'] ?? [] as $assignmentReference) {
        $assignmentId = easyverein_id_from_reference($assignmentReference);

        if ($assignmentId === null) {
            continue;
        }

        try {
            $assignment = easyverein_api_get("/event-custom-field-assignment/{$assignmentId}");
        } catch (EasyvereinApiException) {
            continue;
        }

        if (easyverein_id_from_reference($assignment['custom_field'] ?? null) === $attachmentFieldId) {
            return $assignmentId;
        }
    }

    return null;
}

try {
    $calendars = easyverein_api_get_all('/calendar', ['name' => EASYVEREIN_TERMINE_CALENDAR_NAME]);
    $calendar = $calendars[0] ?? null;

    if ($calendar === null) {
        echo json_encode(['events' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $attachmentFields = easyverein_api_get_all('/custom-field', ['name' => EASYVEREIN_TERMINE_ATTACHMENT_FIELD_NAME]);
    $attachmentFieldId = isset($attachmentFields[0]['id']) ? (int)$attachmentFields[0]['id'] : null;

    $events = easyverein_api_get_all('/event', [
        'calendar' => $calendar['id'],
        'is_public' => true,
    ]);

    $result = array_map(
        static function (array $event) use ($attachmentFieldId): array {
            $attachmentAssignmentId = easyverein_termine_find_attachment_assignment_id($event, $attachmentFieldId);
            $icsUrl = (string)($event['ics_link'] ?? '');

            return [
                'id' => (int)$event['id'],
                'title' => (string)($event['name'] ?? ''),
                'start' => $event['start'] ?? null,
                'end' => $event['end'] ?? null,
                'allDay' => (bool)($event['all_day'] ?? false),
                'location' => (string)($event['location_name'] ?? ''),
                'description' => member_area_news_sanitize_html((string)($event['description'] ?? '')),
                'attachmentUrl' => $attachmentAssignmentId !== null
                    ? '/termine-attachment.php?assignment=' . $attachmentAssignmentId
                    : null,
                // Unlike the "Anhang" attachment, easyVerein's own per-event .ics export link
                // is publicly reachable without authentication (verified live), so it's handed
                // straight to the browser instead of being proxied through our own server.
                'icsUrl' => str_starts_with($icsUrl, 'https://easyverein.com/') ? $icsUrl : null,
            ];
        },
        $events
    );

    echo json_encode(['events' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    if (!easyverein_termine_is_expected_failure($error)) {
        throw $error;
    }

    error_log('termine-feed.php failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['events' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
