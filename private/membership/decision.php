<?php

/**
 * Follows up on transferred applications: easyVerein has no webhooks, so the cron job asks
 * for each transferred application whether the board has decided in the meantime.
 *
 *   accepted (is_application = false)  -> welcome mail, then the local copy is deleted
 *   rejected / deleted (404)           -> local copy deleted, no mail (the board informs in
 *                                         person; a 404 may also mean "deleted as duplicate")
 *   still open                         -> nothing
 */

require_once __DIR__ . '/process.php';

/**
 * @return 'accepted'|'rejected'|'open'
 */
function membership_easyverein_decision(int $memberId): string
{
    try {
        $member = easyverein_api_get("/member/{$memberId}", ['query' => '{id,is_application}']);
    } catch (EasyvereinApiException $error) {
        if (str_contains($error->getMessage(), 'status 404')) {
            return 'rejected';
        }

        throw $error;
    }

    return ($member['is_application'] ?? true) === false ? 'accepted' : 'open';
}

function membership_send_welcome_mail(array $row): void
{
    $data = $row['data'];
    $group = $data['group'];
    $club = MEMBERSHIP_CLUB_NAME;
    $applicantName = "{$data['applicant']['firstName']} {$data['applicant']['lastName']}";
    $mandateReference = membership_mandate_reference($row['reference']);
    $creditorId = membership_config()['creditor_id'] ?: '[Gläubiger-ID folgt]';
    $fees = 'Beitrag: ' . ($group['amount'] !== null
            ? membership_format_euro($group['amount']) . ' ' . membership_format_interval($group['intervalMonths'])
            : '-')
        . "\nAufnahmegebühr: " . ($group['admissionFee'] > 0 ? membership_format_euro($group['admissionFee']) . ' einmalig' : 'entfällt');

    if ($data['isMinor']) {
        $guardian = $data['guardian'];
        $body = <<<TEXT
            Hallo {$guardian['firstName']} {$guardian['lastName']},

            der Vorstand hat den Mitgliedsantrag {$row['reference']} angenommen – herzlich willkommen,
            {$applicantName}, im {$club}!

            Mit der Annahme beginnt die vorläufige Mitgliedschaft; nach drei Monaten erfolgt die endgültige
            Aufnahme durch den Vorstand (§ 4 Abs. 5 der Satzung).

            Mitgliedsart: {$group['name']}
            {$fees}

            Die Beiträge ziehen wir per SEPA-Lastschrift ein (Gläubiger-ID {$creditorId}, Mandatsreferenz
            {$mandateReference}). Vor der ersten Abbuchung informieren wir Sie über Betrag und Termin.

            Bei Fragen antworten Sie einfach auf diese E-Mail. Wir freuen uns auf {$data['applicant']['firstName']}!

            Viele Grüße
            {$club}
            TEXT;
    } else {
        $body = <<<TEXT
            Hallo {$applicantName},

            der Vorstand hat deinen Mitgliedsantrag {$row['reference']} angenommen – herzlich willkommen im {$club}!

            Mit der Annahme beginnt die vorläufige Mitgliedschaft; nach drei Monaten erfolgt die endgültige
            Aufnahme durch den Vorstand (§ 4 Abs. 5 der Satzung).

            Mitgliedsart: {$group['name']}
            {$fees}

            Die Beiträge ziehen wir per SEPA-Lastschrift ein (Gläubiger-ID {$creditorId}, Mandatsreferenz
            {$mandateReference}). Vor der ersten Abbuchung informieren wir dich über Betrag und Termin.

            Bei Fragen antworte einfach auf diese E-Mail. Wir freuen uns auf dich!

            Viele Grüße
            {$club}
            TEXT;
    }

    membership_send_mail(
        membership_confirmation_email($data),
        "Willkommen im {$club}!",
        $body,
        [],
        membership_config()['board_email'] ?: null
    );
}

/**
 * Checks all transferred applications. Returns one line per application with a result.
 *
 * @return list<string>
 */
function membership_follow_up_decisions(): array
{
    $log = [];
    $rows = member_area_db()->query(
        "SELECT id FROM membership_applications WHERE status = 'synced' AND easyverein_member_id IS NOT NULL"
    )->fetchAll();

    foreach ($rows as $item) {
        $row = membership_load_application((int)$item['id']);
        $decision = membership_easyverein_decision((int)$row['easyverein_member_id']);

        if ($decision === 'open') {
            continue;
        }

        if ($decision === 'accepted') {
            try {
                membership_send_welcome_mail($row);
            } catch (MembershipMailException $error) {
                // Keep the application, the next run tries again.
                error_log("Membership application {$row['reference']}: welcome mail failed: " . $error->getMessage());
                $log[] = "{$row['reference']}: angenommen, Willkommens-Mail fehlgeschlagen (wird wiederholt)";
                continue;
            }
        }

        membership_delete_application($row['reference']);
        $log[] = $decision === 'accepted'
            ? "{$row['reference']}: angenommen, Willkommens-Mail verschickt, lokale Kopie gelöscht"
            : "{$row['reference']}: abgelehnt bzw. in easyVerein gelöscht, lokale Kopie gelöscht";
    }

    return $log;
}
