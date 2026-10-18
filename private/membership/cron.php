<?php

/**
 * Maintenance of online membership applications - run regularly (e.g. hourly) as a cron job:
 *
 *   php private/membership/cron.php            retry open steps (mails, easyVerein transfer),
 *                                              delete expired and outdated applications
 *   php private/membership/cron.php status     list all stored applications (no personal data)
 *   php private/membership/cron.php retry OM-2026-XXXXXX
 *                                              transfer a "sync_failed" application again
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/process.php';

membership_ensure_applications_table();
$now = new DateTimeImmutable('now', membership_timezone());
$command = $argv[1] ?? 'run';

if ($command === 'status') {
    $rows = member_area_db()->query(
        'SELECT reference, status, submitted_at, confirmed_at, synced_at, sync_attempts,
                applicant_mailed_at IS NOT NULL AS applicant_mailed, board_mailed_at IS NOT NULL AS board_mailed,
                easyverein_member_id, last_error
         FROM membership_applications ORDER BY id'
    )->fetchAll();

    foreach ($rows as $row) {
        printf(
            "%s  %-11s  abgesendet %s  bestätigt %s  übertragen %s  Mails A/V %s/%s  Versuche %d%s%s\n",
            $row['reference'],
            $row['status'],
            $row['submitted_at'],
            $row['confirmed_at'] ?? '-',
            $row['synced_at'] ?? '-',
            $row['applicant_mailed'] ? 'ja' : 'nein',
            $row['board_mailed'] ? 'ja' : 'nein',
            $row['sync_attempts'],
            $row['easyverein_member_id'] ? "  easyVerein #{$row['easyverein_member_id']}" : '',
            $row['last_error'] ? "\n    Fehler: {$row['last_error']}" : ''
        );
    }

    echo count($rows) . " Antrag/Anträge gespeichert.\n";
    exit(0);
}

if ($command === 'retry') {
    $statement = member_area_db()->prepare(
        "UPDATE membership_applications SET status = 'confirmed', sync_attempts = 0
         WHERE reference = ? AND status = 'sync_failed'"
    );
    $statement->execute([$argv[2] ?? '']);

    if ($statement->rowCount() !== 1) {
        fwrite(STDERR, "Kein Antrag mit Status sync_failed und dieser Nummer gefunden.\n");
        exit(2);
    }
}

if (!in_array($command, ['run', 'retry'], true)) {
    fwrite(STDERR, "Unbekannter Befehl „{$command}“ (run, status, retry <Antragsnummer>).\n");
    exit(2);
}

// 1. Unconfirmed applications past their deadline.
membership_delete_expired_pending($now);

// 2. Open steps of confirmed applications (failed mails, transfer to easyVerein).
$open = member_area_db()->query(
    "SELECT id, reference FROM membership_applications
     WHERE status = 'confirmed' OR (status = 'synced' AND (applicant_mailed_at IS NULL OR board_mailed_at IS NULL))
        OR (status = 'sync_failed' AND (applicant_mailed_at IS NULL OR board_mailed_at IS NULL))
     ORDER BY id"
)->fetchAll();

foreach ($open as $application) {
    // Without live transfer there is nothing left to do once both mails are out (dry-run would
    // only log the same planned requests again and again).
    if (membership_config()['easyverein_sync'] !== 'live') {
        $row = membership_load_application((int)$application['id']);

        if ($row['applicant_mailed_at'] !== null && $row['board_mailed_at'] !== null) {
            continue;
        }
    }

    $failed = membership_process_confirmed((int)$application['id']);
    echo $application['reference'] . ': ' . ($failed === [] ? 'erledigt' : 'offen: ' . implode(', ', $failed)) . "\n";
}

// 3. Retention: the local copy is deleted retention_days after the transfer - from then on the
//    application (incl. PDF) exists only in easyVerein. With the transfer switched off, the
//    deadline counts from the confirmation and the board has the PDF from its mail.
$cutoff = membership_db_time($now->modify('-' . membership_config()['retention_days'] . ' days'));
$statement = member_area_db()->prepare(
    "DELETE FROM membership_applications
     WHERE applicant_mailed_at IS NOT NULL AND board_mailed_at IS NOT NULL
       AND ((status = 'synced' AND synced_at < ?) OR (status = 'confirmed' AND ? = 'off' AND confirmed_at < ?))"
);
$statement->execute([$cutoff, membership_config()['easyverein_sync'], $cutoff]);

if ($statement->rowCount() > 0) {
    echo $statement->rowCount() . " Antrag/Anträge nach Ablauf der Aufbewahrungsfrist gelöscht.\n";
}
