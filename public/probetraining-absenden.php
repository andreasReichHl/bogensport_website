<?php

/**
 * POST (JSON): sends a Probetraining request as mail to the club (trial_training_email).
 *   201 {"ok": true}
 *   422 {"errors": {"field": "..."}}
 *   429 {"error": "rate_limited"}
 *   503 {"error": "unavailable"}
 */

require_once __DIR__ . '/mitglieder/bootstrap.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/http.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/probetraining/request.php';

membership_json_headers();
$input = membership_read_json_post();

// Honeypot or submitted too fast: bots get a fake success, no mail.
if (probetraining_is_bot($input)) {
    membership_respond(201, ['ok' => true]);
}

try {
    [$request, $errors] = probetraining_validate($input);

    if ($request === null) {
        membership_respond(422, ['errors' => $errors]);
    }

    probetraining_assert_rate_limit((string)($_SERVER['REMOTE_ADDR'] ?? ''), membership_now());
    probetraining_send_mail($request);
    membership_respond(201, ['ok' => true]);
} catch (ProbetrainingRateLimitException $error) {
    // Logged so a flood shows up in the server log.
    error_log('probetraining-absenden.php: ' . $error->getMessage());
    membership_respond(429, ['error' => 'rate_limited']);
} catch (MemberAreaDatabaseException | PDOException | MembershipConfigException | MembershipMailException $error) {
    error_log('probetraining-absenden.php failed: ' . $error->getMessage());
    membership_respond(503, ['error' => 'unavailable']);
}
