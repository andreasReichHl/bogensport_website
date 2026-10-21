<?php

/**
 * Everything that happens after an application was confirmed: sign it, render the PDFs, mail
 * them to the applicant and to the board (later also: transfer to easyVerein).
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

    $pdfs = membership_render_pdfs($row);

    if ($row['applicant_mailed_at'] === null) {
        try {
            membership_send_applicant_pdf_mail($row, $pdfs);
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
            membership_send_board_mail($row, $pdfs, $syncOutcome);
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
    $mail = [
        'title' => 'Übertragung an easyVerein fehlgeschlagen',
        'signature' => false,
        'blocks' => [
            ['text', "Der Online-Mitgliedsantrag {$row['reference']} konnte nach mehreren Versuchen nicht an "
                . 'easyVerein übertragen werden.'],
            ['text', 'Bitte das Mitglied anhand der PDFs aus der ersten Mail manuell in easyVerein anlegen oder '
                . 'nach Behebung des Fehlers erneut übertragen:'],
            ['code', "php private/membership/cron.php retry {$row['reference']}"],
            ['text', 'Letzter Fehler:'],
            ['code', $error],
        ],
    ];

    try {
        membership_send_mail($boardEmail, "Übertragung fehlgeschlagen: Mitgliedsantrag {$row['reference']}", $mail);
    } catch (MembershipMailException $mailError) {
        error_log("Membership application {$row['reference']}: sync-failed alert mail failed: " . $mailError->getMessage());
    }
}

function membership_send_applicant_pdf_mail(array $row, array $pdfs): void
{
    $data = $row['data'];
    $applicantName = "{$data['applicant']['firstName']} {$data['applicant']['lastName']}";
    $reference = $row['reference'];
    $facts = ['Antragsnummer' => $reference, 'Mitgliedsart' => $data['group']['name']];

    if ($data['isMinor']) {
        $guardian = $data['guardian'];
        $blocks = [
            ['text', "Guten Tag {$guardian['firstName']} {$guardian['lastName']},"],
            ['text', "vielen Dank! Der Mitgliedsantrag für {$applicantName} ist bestätigt und beim Vorstand "
                . 'eingegangen. Im Anhang finden Sie den Antrag und das SEPA-Lastschriftmandat als PDF – bitte '
                . 'bewahren Sie beides auf.'],
            ['facts', ['Antragsteller/in' => $applicantName] + $facts],
            ['text', 'Über die Aufnahme entscheidet der Vorstand; Sie erhalten dann eine Nachricht von uns. Mit der '
                . 'Annahme beginnt die vorläufige Mitgliedschaft (§ 4 der Satzung).'],
        ];

        if ($guardian['managedBy'] === 'eltern') {
            $blocks[] = ['text', 'Haben Sie Ihren eigenen Mitgliedsantrag erst kürzlich gestellt, entscheidet der '
                . 'Vorstand über diesen Antrag, sobald Ihr eigener Antrag angenommen ist.'];
        }

        $blocks[] = ['text', 'Bei Fragen antworten Sie einfach auf diese E-Mail.'];
        $mail = [
            'title' => 'Der Mitgliedsantrag ist eingegangen',
            'preview' => "Der Mitgliedsantrag für {$applicantName} ist bestätigt – die Unterlagen finden Sie im Anhang.",
            'blocks' => $blocks,
        ];
    } else {
        $mail = [
            'title' => 'Dein Mitgliedsantrag ist eingegangen',
            'preview' => 'Dein Antrag ist bestätigt – die Unterlagen findest du im Anhang.',
            'blocks' => [
                ['text', "Hallo {$data['applicant']['firstName']},"],
                ['text', 'vielen Dank! Dein Mitgliedsantrag ist bestätigt und beim Vorstand eingegangen. Im Anhang '
                    . 'findest du den Antrag und das SEPA-Lastschriftmandat als PDF – bitte bewahre beides auf.'],
                ['facts', $facts],
                ['text', 'Über die Aufnahme entscheidet der Vorstand; du erhältst dann eine Nachricht von uns. Mit '
                    . 'der Annahme beginnt die vorläufige Mitgliedschaft (§ 4 der Satzung).'],
                ['text', 'Bei Fragen antworte einfach auf diese E-Mail.'],
            ],
        ];
    }

    membership_send_mail(
        membership_confirmation_email($data),
        "Mitgliedsantrag {$reference} ist eingegangen",
        $mail,
        $pdfs
    );
}

function membership_send_board_mail(array $row, array $pdfs, string $syncOutcome): void
{
    $boardEmail = membership_config()['board_email'];

    if ($boardEmail === '') {
        throw new MembershipMailException('membership.board_email is not configured');
    }

    $data = $row['data'];
    $applicant = $data['applicant'];
    $notes = [];

    if ($data['isMinor']) {
        $guardian = $data['guardian'];
        $notes[] = "Minderjährig. Erziehungsberechtigte/r: {$guardian['firstName']} {$guardian['lastName']} "
            . "<{$guardian['email']}>";
        $notes[] = $guardian['managedBy'] === 'eltern'
            ? 'Verwaltung: zusammen mit der Mitgliedschaft der/des Erziehungsberechtigten. Ist deren/dessen'
                . ' eigener Antrag noch offen, bitte zuerst diesen annehmen.'
            : 'Verwaltung: eigenständige Mitgliedschaft.';
    }

    if (!$data['consents']['fotos']['accepted']) {
        $notes[] = 'KEINE Einwilligung zur Veröffentlichung von Fotos.';
    }

    $blocks = [
        ['facts', [
            'Antragsnummer' => $row['reference'],
            'Name' => "{$applicant['firstName']} {$applicant['lastName']}",
            'Geburtsdatum' => membership_format_date($applicant['dateOfBirth']),
        ] + membership_group_facts($data['group']) + [
            'Abgesendet' => membership_format_datetime($row['submitted_at']),
            'Bestätigt' => membership_format_datetime($row['confirmed_at']) . " (IP {$row['confirmed_ip']})",
        ]],
    ];

    if ($syncOutcome !== '') {
        $blocks[] = ['text', $syncOutcome];
    }

    if ($notes !== []) {
        $blocks[] = ['text', 'Hinweise:'];
        $blocks[] = ['list', $notes];
    }

    $blocks[] = ['text', 'Alle Angaben stehen in den beiden angehängten PDFs (Mitgliedsantrag und '
        . 'SEPA-Lastschriftmandat). Die Prüfsumme in den PDFs lässt sich auf dem Server prüfen mit:'];
    $blocks[] = ['code', "php private/membership/verify.php {$row['reference']}"];
    $blocks[] = ['text', 'Entscheidung in easyVerein unter den Mitgliedsanträgen: Bitte den Antrag ANNEHMEN oder '
        . 'ABLEHNEN, nicht löschen. Beim Ablehnen entfernt easyVerein Mitglied und Kontaktdaten sofort; beim '
        . 'Löschen landet das Mitglied 30 Tage im Papierkorb, und Adresse und Bankverbindung bleiben so lange '
        . 'gespeichert. Nach der Annahme erhält das neue Mitglied automatisch eine Willkommens-Mail von der Website.'];
    $blocks[] = ['note', 'Antworten auf diese E-Mail gehen direkt an den Antragsteller bzw. die Erziehungsberechtigten.'];

    membership_send_mail(
        $boardEmail,
        "Neuer Mitgliedsantrag {$row['reference']}: {$applicant['firstName']} {$applicant['lastName']}",
        ['title' => 'Neuer Mitgliedsantrag', 'signature' => false, 'blocks' => $blocks],
        $pdfs,
        membership_confirmation_email($data)
    );
}
