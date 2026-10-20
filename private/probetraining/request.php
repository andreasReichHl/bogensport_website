<?php

/**
 * Probetraining request form: validation, rate limit and the mail to the club. Sent via the
 * same SMTP mailbox as the membership application (membership.mail) - nothing is stored except a
 * keyed hash of the sender's IP for the rate limit, deleted after a day.
 */

require_once __DIR__ . '/../membership/applications.php';
require_once __DIR__ . '/../membership/mail.php';

const PROBETRAINING_MAX_REQUESTS_PER_IP_PER_HOUR = 5;
// Across all senders - caps the damage of a bot spreading requests over many IPs.
const PROBETRAINING_MAX_REQUESTS_PER_HOUR = 30;
// Humans need longer than this to fill in the form; bots submit right away.
const PROBETRAINING_MIN_FILL_TIME_MS = 3000;
const PROBETRAINING_EXPERIENCE_LEVELS = ['Keine Erfahrung', 'Ein wenig Erfahrung', 'Fortgeschritten'];

class ProbetrainingRateLimitException extends RuntimeException
{
}

function probetraining_recipient(): string
{
    return (string)(member_area_config()['membership']['trial_training_email'] ?? 'info@badoldesloer-bogensport.de');
}

/**
 * Bot check without telling the bot: true if the honeypot is filled or the form was submitted
 * too fast after loading (fillTimeMs is measured in the browser - missing means a direct POST).
 */
function probetraining_is_bot(array $input): bool
{
    if (trim((string)($input['website'] ?? '')) !== '') {
        return true;
    }

    $fillTimeMs = $input['fillTimeMs'] ?? null;

    return !is_int($fillTimeMs) || $fillTimeMs < PROBETRAINING_MIN_FILL_TIME_MS;
}

function probetraining_contains_link(string $value): bool
{
    return preg_match('~https?://|www\.|\[url|<a\s~i', $value) === 1;
}

function probetraining_text(array $input, string $key, int $maxLength = 128): string
{
    $value = $input[$key] ?? '';

    return is_string($value) ? mb_substr(trim($value), 0, $maxLength) : '';
}

/**
 * @return array{0: ?array, 1: array<string,string>} [normalized request, errors]
 */
function probetraining_validate(array $input): array
{
    $errors = [];
    $request = [
        'firstName' => membership_string($input, 'firstName'),
        'lastName' => membership_string($input, 'lastName'),
        'age' => filter_var($input['age'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]),
        'email' => mb_strtolower(membership_string($input, 'email')),
        'phone' => membership_string($input, 'phone', 50),
        'forChild' => ($input['forChild'] ?? false) === true,
        'guardianName' => membership_string($input, 'guardianName'),
        'experience' => membership_string($input, 'experience', 32),
        'ownEquipment' => $input['ownEquipment'] ?? null,
        // Multi-line - keep the line breaks.
        'message' => probetraining_text($input, 'message', 3000),
    ];

    foreach (['firstName' => 'Vorname', 'lastName' => 'Nachname'] as $field => $label) {
        if ($request[$field] === '') {
            $errors[$field] = "{$label} fehlt.";
        }
    }

    // Spam almost always carries links, real requests practically never.
    foreach (['firstName', 'lastName', 'guardianName', 'message'] as $field) {
        if (!isset($errors[$field]) && probetraining_contains_link($request[$field])) {
            $errors[$field] = 'Bitte keine Links eintragen.';
        }
    }

    if ($request['age'] === false) {
        $errors['age'] = 'Bitte ein Alter zwischen 1 und 120 angeben.';
    }

    if (!filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    }

    if ($request['forChild'] && $request['guardianName'] === '') {
        $errors['guardianName'] = 'Name des Erziehungsberechtigten fehlt.';
    }

    if (!in_array($request['experience'], PROBETRAINING_EXPERIENCE_LEVELS, true)) {
        $errors['experience'] = 'Bitte die Erfahrung im Bogensport auswählen.';
    }

    if (!is_bool($request['ownEquipment'])) {
        $errors['ownEquipment'] = 'Bitte angeben, ob eigene Ausrüstung vorhanden ist.';
    }

    if (($input['consentPrivacy'] ?? false) !== true || ($input['consentContact'] ?? false) !== true) {
        $errors['consents'] = 'Bitte beide Zustimmungen bestätigen.';
    }

    return $errors === [] ? [$request, []] : [null, $errors];
}

function probetraining_ensure_table(): void
{
    member_area_db()->exec(
        'CREATE TABLE IF NOT EXISTS probetraining_requests (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          ip_hash CHAR(64) NOT NULL,
          created_at DATETIME NOT NULL,
          INDEX idx_probetraining_requests_ip (ip_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * Counts the request against the sender's IP (keyed hash, not the address itself) and throws
 * if there were too many from it - or from everyone together - in the last hour. Entries older
 * than a day are deleted.
 */
function probetraining_assert_rate_limit(string $ip, DateTimeImmutable $now): void
{
    probetraining_ensure_table();
    $db = member_area_db();
    $ipHash = hash_hmac('sha256', 'probetraining-ip|' . $ip, membership_config_key('signature_key'));

    $db->prepare('DELETE FROM probetraining_requests WHERE created_at < ?')
        ->execute([membership_db_time($now->modify('-1 day'))]);

    $since = membership_db_time($now->modify('-1 hour'));
    $statement = $db->prepare(
        'SELECT COUNT(*) AS total, COALESCE(SUM(ip_hash = ?), 0) AS from_ip
         FROM probetraining_requests WHERE created_at >= ?'
    );
    $statement->execute([$ipHash, $since]);
    $counts = $statement->fetch(PDO::FETCH_ASSOC);

    if ((int)$counts['from_ip'] >= PROBETRAINING_MAX_REQUESTS_PER_IP_PER_HOUR) {
        throw new ProbetrainingRateLimitException('Too many Probetraining requests from this IP');
    }

    if ((int)$counts['total'] >= PROBETRAINING_MAX_REQUESTS_PER_HOUR) {
        throw new ProbetrainingRateLimitException('Too many Probetraining requests in total');
    }

    $db->prepare('INSERT INTO probetraining_requests (ip_hash, created_at) VALUES (?, ?)')
        ->execute([$ipHash, membership_db_time($now)]);
}

function probetraining_send_mail(array $request): void
{
    $or = static fn(string $value): string => $value !== '' ? $value : '-';
    $name = "{$request['firstName']} {$request['lastName']}";
    $participant = $request['forChild'] ? 'Anmeldung für ein Kind' : 'Meldet sich selbst an';
    $guardian = $request['forChild'] ? $request['guardianName'] : '-';
    $equipment = $request['ownEquipment'] ? 'Ja' : 'Nein';
    $phone = $or($request['phone']);
    $message = $or($request['message']);

    $body = <<<TEXT
        Neue Anfrage für ein Probetraining über die Website.

        Persönliche Daten
        Name: {$name}
        Alter: {$request['age']}
        E-Mail: {$request['email']}
        Telefon: {$phone}

        Teilnehmer
        {$participant}
        Name des Erziehungsberechtigten: {$guardian}

        Erfahrung & Ausrüstung
        Erfahrung im Bogensport: {$request['experience']}
        Eigene Ausrüstung vorhanden: {$equipment}

        Nachricht / Fragen:
        {$message}

        Datenschutzerklärung akzeptiert und Kontaktaufnahme erlaubt: Ja

        Zum Antworten einfach auf diese E-Mail antworten - sie geht an {$request['email']}.
        TEXT;

    membership_send_mail(probetraining_recipient(), "Probetraining-Anfrage: {$name}", $body, [], $request['email']);
}
