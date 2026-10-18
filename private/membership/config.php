<?php

/**
 * Configuration of the online membership application ("Online-Mitgliedsbeitritt").
 *
 * Lives in the same config file as the member area (config.local.php), under the
 * "membership" key. See config.local.example.php for all options.
 */

require_once __DIR__ . '/../member-area/db.php';

class MembershipConfigException extends RuntimeException
{
}

function membership_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $raw = member_area_config()['membership'] ?? [];

    $config = [
        // Version of the Satzung the applicant accepts - must match satzungMeta.version in
        // src/data/satzung.js, otherwise submissions are rejected (the form shows an outdated text).
        'satzung_version' => (string)($raw['satzung_version'] ?? '2009-10-02'),
        // 32-byte keys, base64 encoded. Generate with:
        //   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
        'data_key' => (string)($raw['data_key'] ?? ''),
        'signature_key' => (string)($raw['signature_key'] ?? ''),
        'confirm_ttl_hours' => (int)($raw['confirm_ttl_hours'] ?? 48),
        'minimum_age' => (int)($raw['minimum_age'] ?? 8),
        // One-time admission fee per easyVerein group id (Aufnahmegebühr), in EUR. Groups
        // without an entry are NOT offered in the form, so a group ticked in easyVerein can't
        // accidentally show up without a fee. 0 means "entfällt".
        'admission_fees' => array_map('floatval', $raw['admission_fees'] ?? []),
        'creditor_id' => (string)($raw['creditor_id'] ?? 'DE66ZZZ00001047545'),
        // Public base URL of the website, used for links in mails (no trailing slash).
        'site_url' => (string)($raw['site_url'] ?? 'https://badoldesloer-bogensport.de'),
        'board_email' => (string)($raw['board_email'] ?? ''),
        'mail' => $raw['mail'] ?? [],
        // Transfer of confirmed applications to easyVerein:
        //   'off'     - nothing is transferred, the board creates the member by hand
        //   'dry-run' - read-only lookups only, the planned requests are logged (local testing)
        //   'live'    - applications are created in easyVerein (production)
        'easyverein_sync' => in_array($raw['easyverein_sync'] ?? 'off', ['off', 'dry-run', 'live'], true)
            ? ($raw['easyverein_sync'] ?? 'off')
            : 'off',
        // After this many failed transfer attempts the application is marked sync_failed and
        // the board is alerted.
        'sync_max_attempts' => (int)($raw['sync_max_attempts'] ?? 5),
        // Days after the successful transfer until the local copy is deleted (the PDF and all
        // data are then only in easyVerein). Unconfirmed applications: confirm_ttl_hours.
        'retention_days' => (int)($raw['retention_days'] ?? 90),
    ];

    return $config;
}

/**
 * Decodes one of the base64-encoded 32-byte keys from the config, failing loudly instead of
 * silently falling back to a weak key.
 */
function membership_config_key(string $name): string
{
    $key = base64_decode(membership_config()[$name] ?? '', true);

    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new MembershipConfigException("membership.{$name} is missing or not a base64-encoded 32-byte key");
    }

    return $key;
}

/**
 * All membership timestamps are German local time - both stored in the DB and shown to people -
 * independent of the server's PHP default time zone.
 */
function membership_timezone(): DateTimeZone
{
    return new DateTimeZone('Europe/Berlin');
}
