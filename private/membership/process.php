<?php

/**
 * Everything that happens after an application was confirmed: sign it, render the PDF, mail
 * it to the applicant and to the board (later also: transfer to easyVerein).
 *
 * Each step is recorded in its own column and only done if not yet recorded, so this can be
 * run again any time (e.g. after a failed mail) without sending anything twice.
 */

require_once __DIR__ . '/pdf.php';

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

    if ($row['signature'] === null) {
        $row['signature'] = membership_compute_signature($row);
        membership_mark_step($id, 'signature', $row['signature']);
    }

    $pdf = [
        'content' => membership_render_pdf($row),
        'filename' => membership_pdf_filename($row['reference']),
        'type' => 'application/pdf',
    ];
    $now = membership_db_time(new DateTimeImmutable('now', membership_timezone()));

    foreach ([
        'applicant_mailed_at' => 'membership_send_applicant_pdf_mail',
        'board_mailed_at' => 'membership_send_board_mail',
    ] as $column => $send) {
        if ($row[$column] !== null) {
            continue;
        }

        try {
            $send($row, $pdf);
            membership_mark_step($id, $column, $now);
        } catch (MembershipMailException $error) {
            error_log("Membership application {$row['reference']}: {$column} failed: " . $error->getMessage());
            $failed[] = $column;
        }
    }

    return $failed;
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

function membership_send_board_mail(array $row, array $pdf): void
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
            ? '- Verwaltung: zusammen mit der Mitgliedschaft der/des Erziehungsberechtigten (Angabe muss geprüft werden).'
            : '- Verwaltung: eigenständige Mitgliedschaft.';
    }

    if (!$data['consents']['fotos']['accepted']) {
        $notes[] = '- KEINE Einwilligung zur Veröffentlichung von Fotos.';
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
