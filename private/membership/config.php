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
        'creditor_id' => (string)($raw['creditor_id'] ?? ''),
        'board_email' => (string)($raw['board_email'] ?? ''),
        'mail' => $raw['mail'] ?? [],
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
