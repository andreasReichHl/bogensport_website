<?php

/**
 * POST (JSON) {"token": "…"}: confirms an application (double opt-in) and records the proof -
 * time, IP address and browser of the confirmation.
 *   200 {"status": "confirmed", "reference": "…"}           just confirmed
 *   200 {"status": "already_confirmed", "reference": "…"}   confirmed before (e.g. second click)
 *   404 {"error": "invalid"}                                  unknown or expired link
 */

require_once __DIR__ . '/mitglieder/bootstrap.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/http.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/process.php';

membership_json_headers();
$input = membership_read_json_post();
$now = membership_now();

try {
    $application = membership_find_by_token((string)($input['token'] ?? ''), $now);

    if ($application === null) {
        membership_respond(404, ['error' => 'invalid']);
    }

    $confirmed = $application['status'] === 'pending' && membership_confirm_application(
        (int)$application['id'],
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        $now
    );

    // Sign, PDF to the applicant and the board. Idempotent, so a second click retries only steps
    // that failed before. A failure here must not undo or hide the confirmation itself - it is
    // logged and retried later.
    try {
        $failedSteps = membership_process_confirmed((int)$application['id']);

        if ($failedSteps !== []) {
            error_log("beitritt-bestaetigen.php: {$application['reference']} pending steps: " . implode(', ', $failedSteps));
        }
    } catch (Throwable $error) {
        error_log("beitritt-bestaetigen.php: processing {$application['reference']} failed: " . $error->getMessage());
    }

    membership_respond(200, [
        'status' => $confirmed ? 'confirmed' : 'already_confirmed',
        'reference' => $application['reference'],
    ]);
} catch (MemberAreaDatabaseException | PDOException | MembershipConfigException $error) {
    error_log('beitritt-bestaetigen.php failed: ' . $error->getMessage());
    membership_respond(503, ['error' => 'unavailable']);
}
