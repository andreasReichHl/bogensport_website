<?php

/**
 * CLI: checks the signature of an application, i.e. whether the data stored for it still
 * matches the checksum printed on its PDF.
 *
 *   php private/membership/verify.php OM-2026-XXXXXX
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/signature.php';

$reference = $argv[1] ?? '';
$statement = member_area_db()->prepare('SELECT id FROM membership_applications WHERE reference = ?');
$statement->execute([$reference]);
$id = $statement->fetchColumn();

if ($id === false) {
    fwrite(STDERR, "Antrag „{$reference}“ nicht gefunden.\n");
    exit(2);
}

$row = membership_load_application((int)$id);

if ($row['signature'] === null) {
    echo "Antrag {$reference} ist noch nicht bestätigt bzw. nicht signiert.\n";
    exit(1);
}

echo "Gespeicherte Prüfsumme: " . membership_format_signature($row['signature']) . "\n";

if (membership_signature_is_valid($row)) {
    echo "OK – die gespeicherten Daten passen zur Prüfsumme. Stimmt sie mit der im PDF überein, ist das PDF unverändert.\n";
    exit(0);
}

echo "FEHLER – die gespeicherten Daten passen NICHT zur Prüfsumme.\n";
exit(1);
