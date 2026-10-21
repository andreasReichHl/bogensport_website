<?php

/**
 * GET ?token=…: what the confirmation page shows before the applicant confirms - a short
 * summary of the application behind the mail link. Opening the link does NOT confirm anything
 * (mail scanners open links automatically); that needs the POST to beitritt-bestaetigen.php.
 *   200 {"status": "pending"|"confirmed", ...summary}
 *   404 {"error": "invalid"}   unknown or expired link
 */

require_once __DIR__ . '/mitglieder/bootstrap.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/http.php';
require_once dirname(MEMBER_AREA_PRIVATE_PATH) . '/membership/applications.php';

membership_json_headers();
membership_require_enabled();

try {
    $application = membership_find_by_token((string)($_GET['token'] ?? ''), membership_now());

    if ($application === null) {
        membership_respond(404, ['error' => 'invalid']);
    }

    $data = $application['data'];
    $iban = $data['bank']['iban'];

    membership_respond(200, [
        'status' => $application['status'] === 'pending' ? 'pending' : 'confirmed',
        'reference' => $application['reference'],
        'applicantName' => "{$data['applicant']['firstName']} {$data['applicant']['lastName']}",
        'isMinor' => $data['isMinor'],
        // Managed together with the parent's membership - the parent may have applied only just now.
        'managedByParent' => $data['isMinor'] && $data['guardian']['managedBy'] === 'eltern',
        'group' => $data['group'],
        // Only enough to recognise the account - the link may be forwarded or seen by others.
        'ibanMasked' => substr($iban, 0, 4) . ' **** ' . substr($iban, -4),
        'expiresAt' => (new DateTimeImmutable($application['confirm_expires_at'], membership_timezone()))->format(DATE_ATOM),
    ]);
} catch (MemberAreaDatabaseException | PDOException | MembershipConfigException $error) {
    error_log('beitritt-pruefen.php failed: ' . $error->getMessage());
    membership_respond(503, ['error' => 'unavailable']);
}
