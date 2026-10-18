<?php

/**
 * POST (JSON): submits an online membership application.
 *
 * Validates and stores it as "pending" and mails the confirmation link; it only becomes
 * binding once that link is used (double opt-in). Responses:
 *   201 {"ok": true}                       stored, confirmation mail sent
 *   422 {"errors": {"field.path": "..."}}  validation failed
 *   429 {"error": "rate_limited"}
 *   503 {"error": "unavailable"}
 */

require_once __DIR__ . '/mitglieder/bootstrap.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/http.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/validation.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/applications.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/mail.php';

membership_json_headers();
$input = membership_read_json_post();

// Honeypot: a field hidden from humans. Bots that fill it get a fake success.
if (trim((string)($input['website'] ?? '')) !== '') {
    membership_respond(201, ['ok' => true]);
}

$now = membership_now();

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

    try {
        membership_send_confirmation_mail($application, $stored['reference'], $stored['token'], $stored['expiresAt']);
    } catch (MembershipMailException $error) {
        // Without the mail the application can never be confirmed - drop it so the applicant
        // can simply try again instead of being blocked by the rate limit.
        membership_delete_application($stored['reference']);
        throw $error;
    }

    membership_respond(201, ['ok' => true]);
} catch (MembershipRateLimitException) {
    membership_respond(429, ['error' => 'rate_limited']);
} catch (EasyvereinTokenException | EasyvereinApiException | MemberAreaDatabaseException | PDOException | MembershipConfigException | MembershipMailException $error) {
    error_log('beitritt-absenden.php failed: ' . $error->getMessage());
    membership_respond(503, ['error' => 'unavailable']);
}
