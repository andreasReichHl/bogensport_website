<?php

/**
 * Tamper evidence for confirmed applications.
 *
 * The signature is an HMAC-SHA256 over a canonical JSON form of everything the applicant
 * declared plus the confirmation proof, keyed with membership.signature_key. It is printed on
 * the PDF and stored in the DB. A plain hash would not do: whoever alters the data could simply
 * compute a new one - without the server key that is not possible.
 *
 * Verify an application with: php private/membership/verify.php OM-2026-XXXXXX
 */

require_once __DIR__ . '/applications.php';
require_once __DIR__ . '/validation.php';

// 2: without the mandate reference (assigned by the club only on admission, not by the form).
const MEMBERSHIP_SIGNATURE_VERSION = 2;

/**
 * JSON with object keys sorted recursively, so the same data always yields the same bytes.
 */
function membership_canonical_json(mixed $value): string
{
    $normalize = static function (mixed $value) use (&$normalize): mixed {
        if (!is_array($value)) {
            return $value;
        }

        $value = array_map($normalize, $value);

        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    };

    return json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/**
 * Everything the signature covers - the application as declared plus the confirmation proof.
 */
function membership_signed_payload(array $row): array
{
    return [
        'version' => MEMBERSHIP_SIGNATURE_VERSION,
        'reference' => $row['reference'],
        'submittedAt' => $row['submitted_at'],
        'confirmedAt' => $row['confirmed_at'],
        'confirmedIp' => $row['confirmed_ip'],
        'confirmationEmail' => membership_confirmation_email($row['data']),
        'application' => $row['data'],
    ];
}

function membership_compute_signature(array $row): string
{
    if ($row['confirmed_at'] === null) {
        throw new LogicException('Only confirmed applications can be signed');
    }

    return hash_hmac(
        'sha256',
        membership_canonical_json(membership_signed_payload($row)),
        membership_config_key('signature_key')
    );
}

function membership_signature_is_valid(array $row): bool
{
    return $row['signature'] !== null && hash_equals($row['signature'], membership_compute_signature($row));
}

/**
 * Signature as printed: groups of 8 characters for readability.
 */
function membership_format_signature(string $signature): string
{
    return implode(' ', str_split($signature, 8));
}
