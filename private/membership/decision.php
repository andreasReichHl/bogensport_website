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

/**
 * The SEPA mandate reference as entered in easyVerein - the club assigns it only on admission,
 * so it may still be empty right after the acceptance. '' if it can't be read: the welcome
 * mail then goes out without it rather than not at all.
 */
function membership_easyverein_mandate_reference(int $memberId): string
{
    try {
        $member = easyverein_api_get("/member/{$memberId}", ['query' => '{contact_details{sepa_mandate}}']);
    } catch (EasyvereinApiException $error) {
        error_log("easyVerein member {$memberId}: mandate reference not readable: " . $error->getMessage());

        return '';
    }

    return trim((string)($member['contact_details']['sepa_mandate'] ?? ''));
}

function membership_send_welcome_mail(array $row, string $mandateReference): void
{
    $data = $row['data'];
    $applicant = $data['applicant'];
    $facts = membership_group_facts($data['group']) + [
        'Zahlungsweise' => 'SEPA-Lastschrift',
        'Gläubiger-ID' => membership_config()['creditor_id'] ?: 'folgt',
    ];

    if ($mandateReference !== '') {
        $facts['Mandatsreferenz'] = $mandateReference;
    }

    $announced = $mandateReference !== '' ? 'Betrag und Termin' : 'Betrag, Termin und Mandatsreferenz';
    $admission = 'Mit der Annahme beginnt die vorläufige Mitgliedschaft; nach drei Monaten erfolgt die endgültige '
        . 'Aufnahme durch den Vorstand (§ 4 Abs. 5 der Satzung).';

    if ($data['isMinor']) {
        $guardian = $data['guardian'];
        $mail = [
            'title' => 'Willkommen im Verein!',
            'preview' => "Der Mitgliedsantrag für {$applicant['firstName']} {$applicant['lastName']} wurde angenommen.",
            'blocks' => [
                ['text', "Guten Tag {$guardian['firstName']} {$guardian['lastName']},"],
                ['text', "der Vorstand hat den Mitgliedsantrag für {$applicant['firstName']} {$applicant['lastName']} "
                    . "angenommen – herzlich willkommen, {$applicant['firstName']}!"],
                ['text', $admission],
                ['facts', $facts],
                ['text', "Vor der ersten Abbuchung informieren wir Sie über {$announced}."],
                ['text', "Bei Fragen antworten Sie einfach auf diese E-Mail. Wir freuen uns auf {$applicant['firstName']}!"],
            ],
        ];
    } else {
        $mail = [
            'title' => 'Willkommen im Verein!',
            'preview' => 'Dein Mitgliedsantrag wurde angenommen – schön, dass du dabei bist.',
            'blocks' => [
                ['text', "Hallo {$applicant['firstName']},"],
                ['text', 'der Vorstand hat deinen Mitgliedsantrag angenommen – schön, dass du dabei bist!'],
                ['text', $admission],
                ['facts', $facts],
                ['text', "Vor der ersten Abbuchung informieren wir dich über {$announced}."],
                ['text', 'Bei Fragen antworte einfach auf diese E-Mail. Wir freuen uns auf dich!'],
            ],
        ];
    }

    membership_send_mail(
        membership_confirmation_email($data),
        'Willkommen im ' . MEMBERSHIP_CLUB_NAME . '!',
        $mail,
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
                membership_send_welcome_mail(
                    $row,
                    membership_easyverein_mandate_reference((int)$row['easyverein_member_id'])
                );
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
