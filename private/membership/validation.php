<?php

/**
 * Server-side validation and normalization of a submitted membership application.
 *
 * The browser form validates too, but only this is authoritative. Error keys are dotted field
 * paths ("applicant.iban") so the form can show each message next to its field.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/groups.php';
require_once __DIR__ . '/texts.php';

const MEMBERSHIP_SALUTATIONS = ['', 'Herr', 'Frau'];
const MEMBERSHIP_GENDERS = ['m', 'w', 'd'];
const MEMBERSHIP_REQUIRED_CONSENTS = ['satzung', 'beitrag', 'datenschutz', 'sepa'];
// Who manages a minor's membership in easyVerein: linked to the guardian's existing account,
// or the child gets an account of its own.
const MEMBERSHIP_MANAGED_BY = ['eltern', 'kind'];

function membership_string(array $source, string $key, int $maxLength = 128): string
{
    $value = $source[$key] ?? '';

    if (!is_string($value)) {
        return '';
    }

    // Collapse whitespace (incl. line breaks) - none of the fields is multi-line.
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

    return mb_substr($value, 0, $maxLength);
}

function membership_normalize_iban(string $iban): string
{
    return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
}

function membership_is_valid_iban(string $iban): bool
{
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
        return false;
    }

    if (str_starts_with($iban, 'DE') && strlen($iban) !== 22) {
        return false;
    }

    // ISO 13616 check: move the first four characters to the end, letters -> numbers, mod 97 == 1.
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';

    foreach (str_split($rearranged) as $character) {
        $numeric .= ctype_alpha($character) ? (string)(ord($character) - 55) : $character;
    }

    $remainder = 0;

    foreach (str_split($numeric, 7) as $chunk) {
        $remainder = (int)($remainder . $chunk) % 97;
    }

    return $remainder === 1;
}

function membership_age_on(DateTimeImmutable $birthDate, DateTimeImmutable $date): int
{
    return $birthDate->diff($date)->y;
}

/**
 * @return array{0: ?array, 1: array<string,string>} [normalized application, errors]
 */
function membership_validate_application(array $input, DateTimeImmutable $now): array
{
    $errors = [];
    $config = membership_config();

    if (($input['satzungVersion'] ?? null) !== $config['satzung_version']
        || ($input['textsVersion'] ?? null) !== MEMBERSHIP_TEXTS_VERSION) {
        // The page was loaded before the Satzung or the declarations changed.
        return [null, ['form' => 'Das Formular ist nicht mehr aktuell. Bitte lade die Seite neu.']];
    }

    $applicantInput = is_array($input['applicant'] ?? null) ? $input['applicant'] : [];
    $guardianInput = is_array($input['guardian'] ?? null) ? $input['guardian'] : [];
    $bankInput = is_array($input['bank'] ?? null) ? $input['bank'] : [];
    $consentInput = is_array($input['consents'] ?? null) ? $input['consents'] : [];

    $applicant = [
        'salutation' => membership_string($applicantInput, 'salutation', 16),
        'firstName' => membership_string($applicantInput, 'firstName'),
        'lastName' => membership_string($applicantInput, 'lastName'),
        'dateOfBirth' => membership_string($applicantInput, 'dateOfBirth', 10),
        'gender' => membership_string($applicantInput, 'gender', 1),
        'street' => membership_string($applicantInput, 'street'),
        'zip' => membership_string($applicantInput, 'zip', 5),
        'city' => membership_string($applicantInput, 'city', 100),
        'email' => mb_strtolower(membership_string($applicantInput, 'email')),
        'phone' => membership_string($applicantInput, 'phone', 50),
    ];

    foreach (['firstName' => 'Vorname', 'lastName' => 'Nachname', 'street' => 'Straße und Hausnummer', 'city' => 'Ort'] as $field => $label) {
        if ($applicant[$field] === '') {
            $errors["applicant.{$field}"] = "{$label} fehlt.";
        }
    }

    if (!in_array($applicant['salutation'], MEMBERSHIP_SALUTATIONS, true)) {
        $errors['applicant.salutation'] = 'Ungültige Anrede.';
    }

    if (!in_array($applicant['gender'], MEMBERSHIP_GENDERS, true)) {
        $errors['applicant.gender'] = 'Bitte Geschlecht auswählen (wird für die Meldung an die Sportverbände benötigt).';
    }

    if (!preg_match('/^\d{5}$/', $applicant['zip'])) {
        $errors['applicant.zip'] = 'Bitte eine fünfstellige Postleitzahl angeben.';
    }

    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $applicant['dateOfBirth']) ?: null;
    $age = null;

    if ($birthDate === null || $birthDate->format('Y-m-d') !== $applicant['dateOfBirth'] || $birthDate > $now) {
        $errors['applicant.dateOfBirth'] = 'Bitte ein gültiges Geburtsdatum angeben.';
    } else {
        $age = membership_age_on($birthDate, $now);

        if ($age < $config['minimum_age']) {
            $errors['applicant.dateOfBirth'] = "Eine Mitgliedschaft ist ab {$config['minimum_age']} Jahren möglich.";
        } elseif ($age > 120) {
            $errors['applicant.dateOfBirth'] = 'Bitte ein gültiges Geburtsdatum angeben.';
        }
    }

    $isMinor = $age !== null && $age < 18;

    if ($applicant['email'] !== '' && !filter_var($applicant['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['applicant.email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    } elseif ($applicant['email'] === '' && !$isMinor) {
        $errors['applicant.email'] = 'E-Mail-Adresse fehlt.';
    }

    $guardian = null;

    if ($isMinor) {
        $guardian = [
            'firstName' => membership_string($guardianInput, 'firstName'),
            'lastName' => membership_string($guardianInput, 'lastName'),
            'email' => mb_strtolower(membership_string($guardianInput, 'email')),
            'phone' => membership_string($guardianInput, 'phone', 50),
        ];

        foreach (['firstName' => 'Vorname', 'lastName' => 'Nachname'] as $field => $label) {
            if ($guardian[$field] === '') {
                $errors["guardian.{$field}"] = "{$label} des/der Erziehungsberechtigten fehlt.";
            }
        }

        if (!filter_var($guardian['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['guardian.email'] = 'Bitte eine gültige E-Mail-Adresse des/der Erziehungsberechtigten angeben.';
        }

        // Deliberately NOT checked here whether the guardian really is a member: answering that
        // would let anyone probe who is in the club. Ownership of the address is proven by the
        // confirmation mail going there; the easyVerein lookup happens when transferring.
        $guardian['managedBy'] = in_array($input['managedBy'] ?? null, MEMBERSHIP_MANAGED_BY, true)
            ? $input['managedBy']
            : null;

        if ($guardian['managedBy'] === null) {
            $errors['managedBy'] = 'Bitte auswählen, wie die Mitgliedschaft verwaltet werden soll.';
        }
    }

    $groupId = filter_var($input['groupId'] ?? null, FILTER_VALIDATE_INT);
    $group = $groupId !== false ? membership_find_group($groupId) : null;

    if ($group === null) {
        $errors['groupId'] = 'Bitte eine Mitgliedsart auswählen.';
    } elseif ($age !== null && $group['maxAge'] !== null && $age >= $group['maxAge']) {
        $errors['groupId'] = "Die Mitgliedsart „{$group['name']}“ ist nur bis {$group['maxAge']} Jahre möglich.";
    }

    $bank = [
        'accountHolder' => membership_string($bankInput, 'accountHolder'),
        'iban' => membership_normalize_iban(membership_string($bankInput, 'iban', 50)),
        'bic' => strtoupper(membership_string($bankInput, 'bic', 11)),
    ];

    if ($bank['accountHolder'] === '') {
        $errors['bank.accountHolder'] = 'Kontoinhaber/in fehlt.';
    }

    if (!membership_is_valid_iban($bank['iban'])) {
        $errors['bank.iban'] = 'Bitte eine gültige IBAN angeben.';
    }

    if ($bank['bic'] !== '' && !preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bank['bic'])) {
        $errors['bank.bic'] = 'Bitte eine gültige BIC angeben oder das Feld leer lassen.';
    }

    $requiredConsents = $isMinor
        ? [...MEMBERSHIP_REQUIRED_CONSENTS, 'erziehungsberechtigt']
        : MEMBERSHIP_REQUIRED_CONSENTS;

    foreach ($requiredConsents as $consent) {
        if (($consentInput[$consent] ?? false) !== true) {
            $errors["consents.{$consent}"] = 'Bitte bestätigen.';
        }
    }

    if ($errors !== []) {
        return [null, $errors];
    }

    $texts = membership_texts();
    $consents = [];

    // Store the exact wording agreed to, not just a flag.
    foreach ([...$requiredConsents, 'fotos'] as $consent) {
        $consents[$consent] = [
            'accepted' => ($consentInput[$consent] ?? false) === true,
            'text' => $texts[$consent],
        ];
    }

    if ($guardian !== null) {
        // Keep the wording of the chosen option, like the declarations.
        $guardian['managedByText'] = $texts['verwaltung_' . $guardian['managedBy']];
    }

    return [[
        'group' => $group,
        'applicant' => $applicant,
        'isMinor' => $isMinor,
        'guardian' => $guardian,
        'bank' => $bank,
        'consents' => $consents,
        'satzungVersion' => $config['satzung_version'],
        'textsVersion' => MEMBERSHIP_TEXTS_VERSION,
    ], []];
}

/**
 * The address the confirmation link goes to: for minors the legal guardian, who is the one
 * actually making the binding declaration.
 */
function membership_confirmation_email(array $application): string
{
    return $application['isMinor'] ? $application['guardian']['email'] : $application['applicant']['email'];
}
