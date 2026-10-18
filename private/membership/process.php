<?php

/**
 * Everything that happens after an application was confirmed: sign it, render the PDF, mail
 * it to the applicant and to the board (later also: transfer to easyVerein).
 *
 * Each step is recorded in its own column and only done if not yet recorded, so this can be
 * run again any time (e.g. after a failed mail) without sending anything twice.
 */

require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/sync.php';

/**
 * @return list<string> steps that failed (empty = everything done)
 */
function membership_process_confirmed(int $id): array
{
    $row = membership_load_application($id);

    if ($row === null || $row['confirmed_at'] === null) {
        return [];
    }

    $failed = [];
    $now = membership_db_time(new DateTimeImmutable('now', membership_timezone()));

    if ($row['signature'] === null) {
        $row['signature'] = membership_compute_signature($row);
        membership_mark_step($id, 'signature', $row['signature']);
    }

    $pdf = [
        'content' => membership_render_pdf($row),
        'filename' => membership_pdf_filename($row['reference']),
        'type' => 'application/pdf',
    ];

    if ($row['applicant_mailed_at'] === null) {
        try {
            membership_send_applicant_pdf_mail($row, $pdf);
            membership_mark_step($id, 'applicant_mailed_at', $now);
        } catch (MembershipMailException $error) {
            error_log("Membership application {$row['reference']}: applicant mail failed: " . $error->getMessage());
            $failed[] = 'applicant_mailed_at';
        }
    }

    // Transfer before the board mail, so the board mail can say how it went.
    $syncOutcome = $row['status'] === 'confirmed' ? membership_try_sync($row) : (string)$row['sync_note'];

    if ($syncOutcome === null) {
        $failed[] = 'sync';
        $syncOutcome = 'Übertragung an easyVerein ist fehlgeschlagen und wird automatisch wiederholt.';
    }

    if ($row['board_mailed_at'] === null) {
        try {
            membership_send_board_mail($row, $pdf, $syncOutcome);
            membership_mark_step($id, 'board_mailed_at', $now);
        } catch (MembershipMailException $error) {
            error_log("Membership application {$row['reference']}: board mail failed: " . $error->getMessage());
            $failed[] = 'board_mailed_at';
        }
    }

    return $failed;
}

/**
 * One transfer attempt. Returns the outcome text, or null if it failed - after
 * sync_max_attempts failures the application is marked sync_failed and the board alerted.
 */
function membership_try_sync(array $row): ?string
{
    try {
        return membership_sync_application((int)$row['id']);
    } catch (EasyvereinTokenException | EasyvereinApiException | MembershipSyncException $error) {
        $attempts = (int)$row['sync_attempts'] + 1;
        $giveUp = $attempts >= membership_config()['sync_max_attempts'];

        error_log("Membership application {$row['reference']}: sync attempt {$attempts} failed: " . $error->getMessage());
        member_area_db()
            ->prepare('UPDATE membership_applications SET sync_attempts = ?, last_error = ?, status = ? WHERE id = ?')
            ->execute([$attempts, mb_substr($error->getMessage(), 0, 500), $giveUp ? 'sync_failed' : 'confirmed', $row['id']]);

        if ($giveUp) {
            membership_send_sync_failed_mail($row, $error->getMessage());
        }

        return null;
    }
}

function membership_send_sync_failed_mail(array $row, string $error): void
{
    $boardEmail = membership_config()['board_email'];
    $body = <<<TEXT
        Der Online-Mitgliedsantrag {$row['reference']} konnte nach mehreren Versuchen nicht an easyVerein
        übertragen werden. Bitte das Mitglied anhand des PDFs aus der ersten Mail manuell in easyVerein anlegen
        oder nach Behebung des Fehlers erneut übertragen:

            php private/membership/cron.php retry {$row['reference']}

        Letzter Fehler:
        {$error}
        TEXT;

    try {
        membership_send_mail($boardEmail, "Übertragung fehlgeschlagen: Mitgliedsantrag {$row['reference']}", $body);
    } catch (MembershipMailException $mailError) {
        error_log("Membership application {$row['reference']}: sync-failed alert mail failed: " . $mailError->getMessage());
    }
}

function membership_send_applicant_pdf_mail(array $row, array $pdf): void
{
    $data = $row['data'];
    $applicantName = "{$data['applicant']['firstName']} {$data['applicant']['lastName']}";
    $club = MEMBERSHIP_CLUB_NAME;
    $reference = $row['reference'];

    if ($data['isMinor']) {
        $guardian = $data['guardian'];
        $body = <<<TEXT
            Hallo {$guardian['firstName']} {$guardian['lastName']},

            vielen Dank! Der Mitgliedsantrag {$reference} für {$applicantName} ist bestätigt und beim Vorstand
            eingegangen. Im Anhang finden Sie alle Angaben als PDF - bitte bewahren Sie es auf.

            Über die Aufnahme entscheidet der Vorstand. Mit Annahme des Antrags beginnt die vorläufige
            Mitgliedschaft (§ 4 der Satzung). Bei Fragen antworten Sie einfach auf diese E-Mail.

            Viele Grüße
            {$club}
            TEXT;
    } else {
        $body = <<<TEXT
            Hallo {$applicantName},

            vielen Dank! Dein Mitgliedsantrag {$reference} ist bestätigt und beim Vorstand eingegangen. Im Anhang
            findest du alle Angaben als PDF - bitte bewahre es auf.

            Über die Aufnahme entscheidet der Vorstand. Mit Annahme des Antrags beginnt die vorläufige
            Mitgliedschaft (§ 4 der Satzung). Bei Fragen antworte einfach auf diese E-Mail.

            Viele Grüße
            {$club}
            TEXT;
    }

    membership_send_mail(
        membership_confirmation_email($data),
        "Mitgliedsantrag {$reference} ist eingegangen",
        $body,
        [$pdf]
    );
}

function membership_send_board_mail(array $row, array $pdf, string $syncOutcome): void
{
    $boardEmail = membership_config()['board_email'];

    if ($boardEmail === '') {
        throw new MembershipMailException('membership.board_email is not configured');
    }

    $data = $row['data'];
    $applicant = $data['applicant'];
    $groupSummary = membership_group_summary($data['group']);
    $submitted = membership_format_datetime($row['submitted_at']);
    $confirmed = membership_format_datetime($row['confirmed_at']);
    $notes = [];

    if ($data['isMinor']) {
        $guardian = $data['guardian'];
        $notes[] = "- Minderjährig. Erziehungsberechtigte/r: {$guardian['firstName']} {$guardian['lastName']} "
            . "<{$guardian['email']}>";
        $notes[] = $guardian['managedBy'] === 'eltern'
            ? '- Verwaltung: zusammen mit der Mitgliedschaft der/des Erziehungsberechtigten.'
            : '- Verwaltung: eigenständige Mitgliedschaft.';
    }

    if (!$data['consents']['fotos']['accepted']) {
        $notes[] = '- KEINE Einwilligung zur Veröffentlichung von Fotos.';
    }

    if ($syncOutcome !== '') {
        $notes[] = "- easyVerein: {$syncOutcome}";
    }

    $notesText = $notes !== [] ? "Hinweise:\n" . implode("\n", $notes) . "\n\n" : '';

    $body = <<<TEXT
        Neuer Online-Mitgliedsantrag {$row['reference']}

        Name: {$applicant['firstName']} {$applicant['lastName']}
        Geburtsdatum: {$applicant['dateOfBirth']}
        {$groupSummary}

        Abgesendet: {$submitted}
        Bestätigt:  {$confirmed} (IP {$row['confirmed_ip']})

        {$notesText}Alle Angaben inkl. SEPA-Mandat stehen im angehängten PDF. Die Prüfsumme im PDF lässt sich auf dem
        Server prüfen mit: php private/membership/verify.php {$row['reference']}

        Entscheidung in easyVerein unter den Mitgliedsanträgen: Bitte den Antrag ANNEHMEN oder ABLEHNEN,
        nicht löschen. Beim Ablehnen entfernt easyVerein Mitglied und Kontaktdaten sofort; beim Löschen
        landet das Mitglied 30 Tage im Papierkorb und Adresse und Bankverbindung bleiben so lange gespeichert.
        Nach der Annahme erhält das neue Mitglied automatisch eine Willkommens-Mail von der Website.

        Antworten auf diese E-Mail gehen direkt an den Antragsteller bzw. die Erziehungsberechtigten.
        TEXT;

    membership_send_mail(
        $boardEmail,
        "Neuer Mitgliedsantrag {$row['reference']}: {$applicant['firstName']} {$applicant['lastName']}",
        $body,
        [$pdf],
        membership_confirmation_email($data)
    );
}
