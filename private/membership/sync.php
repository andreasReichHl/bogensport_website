<?php

/**
 * Transfer of a confirmed application to easyVerein as a membership application
 * (member with is_application = true), which the board then accepts in easyVerein.
 *
 * Steps, each recorded in its own column so a retry continues where it stopped:
 *   1. pick the login address (easyVerein requires it to be unique, see membership_pick_login())
 *   2. POST /contact-details  (person, address, bank account, SEPA mandate date)
 *   3. POST /member           (login, is_application, link to a parent's account)
 *   4. POST /member-group-assignment (membership type -> fee)
 *   5. PATCH /member/{id}     (upload the application PDF and the SEPA mandate PDF, the latter
 *                              as declaration of consent and as SEPA mandate document)
 */

require_once __DIR__ . '/pdf.php';

const MEMBERSHIP_LOGIN_MAX_SUFFIX = 20;

class MembershipSyncException extends RuntimeException
{
}

/**
 * The address that becomes the easyVerein login: the applicant's own - for a minor without an
 * address of its own (or with the guardian's) the guardian's, see membership_login_first_suffix().
 */
function membership_login_base(array $data): string
{
    $guardian = $data['guardian'];
    $email = mb_strtolower($data['applicant']['email']);

    if ($guardian !== null && ($email === '' || $email === mb_strtolower($guardian['email']))) {
        return $guardian['email'];
    }

    return $data['applicant']['email'];
}

/**
 * A child on the guardian's address never gets the address itself, but starts at address_1 -
 * the plain address stays the parent's (own membership or own application, possibly not yet
 * transferred).
 */
function membership_login_first_suffix(array $data): int
{
    $guardian = $data['guardian'];

    return $guardian !== null && mb_strtolower(membership_login_base($data)) === mb_strtolower($guardian['email']) ? 1 : 0;
}

/**
 * "max@example.org", 2 -> "max_2@example.org" - the pattern the club already uses for family
 * members sharing one address.
 */
function membership_login_candidate(string $base, int $suffix): string
{
    if ($suffix === 0) {
        return $base;
    }

    [$local, $domain] = explode('@', $base, 2);

    return "{$local}_{$suffix}@{$domain}";
}

function membership_login_taken_in_easyverein(string $login): bool
{
    $result = easyverein_api_get('/member', ['email' => $login, 'query' => '{id}']);

    return ($result['results'] ?? []) !== [];
}

function membership_login_reserved_locally(string $login, int $applicationId): bool
{
    $statement = member_area_db()->prepare(
        'SELECT 1 FROM membership_applications WHERE easyverein_login = ? AND id <> ? LIMIT 1'
    );
    $statement->execute([$login, $applicationId]);

    return $statement->fetchColumn() !== false;
}

/**
 * First free login: the address itself, else address_1, address_2, … Free means: no member in
 * easyVerein has it, and no other local application (not yet transferred) has reserved it.
 */
function membership_pick_login(array $data, int $applicationId, int $startSuffix = 0): string
{
    $base = mb_strtolower(membership_login_base($data));

    for ($suffix = max($startSuffix, membership_login_first_suffix($data)); $suffix <= MEMBERSHIP_LOGIN_MAX_SUFFIX; $suffix++) {
        $candidate = membership_login_candidate($base, $suffix);

        if (!membership_login_reserved_locally($candidate, $applicationId)
            && !membership_login_taken_in_easyverein($candidate)) {
            return $candidate;
        }
    }

    throw new MembershipSyncException("No free login found for application {$applicationId}");
}

/**
 * For "managed by a parent": the parent's own application (adult, same address) if it is still
 * stored locally - parents may apply for themselves and then for their child right away.
 */
function membership_find_parent_application(array $data, int $applicationId): ?array
{
    $guardian = $data['guardian'];

    if ($guardian === null || $guardian['managedBy'] !== 'eltern') {
        return null;
    }

    $statement = member_area_db()->prepare(
        'SELECT id FROM membership_applications WHERE email_hash = ? AND id <> ? ORDER BY id'
    );
    $statement->execute([membership_email_hash($guardian['email']), $applicationId]);

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $candidateId) {
        $candidate = membership_load_application((int)$candidateId);

        if ($candidate !== null && !$candidate['data']['isMinor']) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Whether the child's transfer has to wait for the parent's own application: still unconfirmed
 * or not yet in easyVerein, so there is nothing to link to yet. Not while it is sync_failed -
 * then the child goes ahead unlinked and the board is told.
 */
function membership_parent_application_pending(?array $parentApplication): bool
{
    return $parentApplication !== null
        && $parentApplication['easyverein_member_id'] === null
        && in_array($parentApplication['status'], ['pending', 'confirmed'], true);
}

/**
 * For "managed by a parent": the parent's member id - from the parent's own transferred
 * application, else found in easyVerein by the login address. Ownership of that address was
 * proven by the confirmation mail. null if there is no such member.
 */
function membership_find_parent_member_id(array $data, ?array $parentApplication): ?int
{
    $guardian = $data['guardian'];

    if ($guardian === null || $guardian['managedBy'] !== 'eltern') {
        return null;
    }

    if ($parentApplication !== null && $parentApplication['easyverein_member_id'] !== null) {
        return (int)$parentApplication['easyverein_member_id'];
    }

    $result = easyverein_api_get('/member', ['email' => $guardian['email'], 'query' => '{id}']);
    $members = $result['results'] ?? [];

    return count($members) === 1 ? (int)$members[0]['id'] : null;
}

function membership_contact_payload(array $row): array
{
    $data = $row['data'];
    $applicant = $data['applicant'];
    $guardian = $data['guardian'];
    $bank = $data['bank'];
    $applicantName = "{$applicant['firstName']} {$applicant['lastName']}";
    $group = $data['group'];

    $note = [
        "Online-Mitgliedsantrag {$row['reference']}, bestätigt am " . membership_format_datetime($row['confirmed_at'])
        . " (IP {$row['confirmed_ip']}).",
        'Aufnahmegebühr: ' . ($group['admissionFee'] > 0 ? membership_format_euro($group['admissionFee']) : 'entfällt') . '.',
        'Foto-Einwilligung: ' . ($data['consents']['fotos']['accepted'] ? 'ja' : 'NEIN') . '.',
    ];

    if ($guardian !== null) {
        $note[] = "Erziehungsberechtigte/r: {$guardian['firstName']} {$guardian['lastName']}, {$guardian['email']}"
            . ($guardian['phone'] !== '' ? ", {$guardian['phone']}" : '') . '.';
    }

    $note[] = 'Prüfsumme: ' . $row['signature'];

    // Empty optional values are left out instead of sent as "" - e.g. salutation only accepts
    // "Herr" or "Frau".
    return array_filter([
        'first_name' => $applicant['firstName'],
        'family_name' => $applicant['lastName'],
        'salutation' => $applicant['salutation'],
        'date_of_birth' => $applicant['dateOfBirth'],
        // The real address - the login may carry a _1 suffix and is then not deliverable.
        'private_email' => $applicant['email'] !== '' ? $applicant['email'] : $guardian['email'],
        'preferred_email_field' => 1,
        'preferred_communication_way' => 0,
        'mobile_phone' => $applicant['phone'] !== '' ? $applicant['phone'] : ($guardian['phone'] ?? ''),
        // Not present in applications submitted before the field existed.
        'private_phone' => $applicant['privatePhone'] ?? '',
        'street' => $applicant['street'],
        'zip' => $applicant['zip'],
        'city' => $applicant['city'],
        'country' => 'Deutschland',
        'country_code' => 'DE',
        'iban' => $bank['iban'],
        'bic' => $bank['bic'],
        'bank_account_owner' => strcasecmp($bank['accountHolder'], $applicantName) === 0 ? '' : $bank['accountHolder'],
        'method_of_payment' => 1,
        // No sepa_mandate: the club assigns the mandate reference only on admission.
        'sepa_date' => substr((string)$row['confirmed_at'], 0, 10),
        'internal_note' => implode("\n", $note),
    ], static fn(mixed $value): bool => $value !== '' && $value !== null);
}

function membership_member_payload(array $row, int $contactId, string $login, ?int $parentMemberId): array
{
    $gender = strtoupper($row['data']['applicant']['gender']);
    $payload = [
        'email_or_user_name' => $login,
        'contact_details' => $contactId,
        'is_application' => true,
        'integration_dosb_gender' => $gender,
        'integration_lsb_gender' => $gender,
    ];

    if ($parentMemberId !== null) {
        $payload['related_member'] = $parentMemberId;
        $payload['editable_by_related_members'] = true;
    }

    return $payload;
}

function membership_is_login_conflict(EasyvereinApiException $error): bool
{
    return $error instanceof EasyvereinApiRequestException
        && $error->status === 400
        && str_contains($error->responseBody, 'email_or_user_name');
}

/**
 * Runs the transfer (in the configured mode). Returns a short, human-readable outcome for the
 * board mail. Throws on failure - the caller counts attempts.
 */
function membership_sync_application(int $id): string
{
    $mode = membership_config()['easyverein_sync'];
    $row = membership_load_application($id);

    if ($row === null || $row['confirmed_at'] === null || $row['synced_at'] !== null) {
        return '';
    }

    if ($mode === 'off') {
        return 'Automatische Übertragung an easyVerein ist ausgeschaltet – bitte das Mitglied manuell anlegen.';
    }

    $data = $row['data'];
    $notes = [];
    $parentApplication = membership_find_parent_application($data, $id);

    if (membership_parent_application_pending($parentApplication)) {
        // Not a failure: no attempt is counted, the cron job simply tries again next run.
        return "Wartet auf die Übertragung des eigenen Antrags der/des Erziehungsberechtigten "
            . "({$parentApplication['reference']}) – wird danach automatisch übertragen und verknüpft.";
    }

    $parentMemberId = membership_find_parent_member_id($data, $parentApplication);

    if ($data['guardian'] !== null && $data['guardian']['managedBy'] === 'eltern') {
        $notes[] = $parentMemberId !== null
            ? 'Mit dem Mitglied der/des Erziehungsberechtigten verknüpft.'
            : 'ACHTUNG: Kein Mitglied mit der Adresse der/des Erziehungsberechtigten gefunden – nicht verknüpft, bitte prüfen.';
    }

    if ($mode === 'dry-run') {
        $login = $row['easyverein_login'] ?? membership_pick_login($data, $id);
        $contact = membership_contact_payload($row);
        $contact['iban'] = substr($contact['iban'], 0, 4) . '…';
        error_log("OM dry-run {$row['reference']}: POST /contact-details " . json_encode($contact, JSON_UNESCAPED_UNICODE));
        error_log("OM dry-run {$row['reference']}: POST /member " . json_encode(membership_member_payload($row, 0, $login, $parentMemberId)));
        error_log("OM dry-run {$row['reference']}: POST /member-group-assignment group {$data['group']['id']}, PATCH PDF upload");

        return trim("TESTMODUS (dry-run): nichts an easyVerein übertragen. Geplanter Login: {$login}. " . implode(' ', $notes));
    }

    // 1. Login - reserved locally before creating anything, so two applications sharing an
    //    address can't pick the same one.
    if ($row['easyverein_login'] === null) {
        $row['easyverein_login'] = membership_pick_login($data, $id);
        membership_mark_step($id, 'easyverein_login', $row['easyverein_login']);
    }

    // 2. Contact details
    if ($row['easyverein_contact_id'] === null) {
        $contact = easyverein_api_send('POST', '/contact-details', membership_contact_payload($row));
        $row['easyverein_contact_id'] = (int)$contact['id'];
        membership_mark_step($id, 'easyverein_contact_id', (string)$row['easyverein_contact_id']);
    }

    // 3. Member (= the application). On a login conflict the next free suffix is tried.
    if ($row['easyverein_member_id'] === null) {
        for ($attempt = 0; ; $attempt++) {
            try {
                $member = easyverein_api_send('POST', '/member', membership_member_payload(
                    $row,
                    (int)$row['easyverein_contact_id'],
                    $row['easyverein_login'],
                    $parentMemberId
                ));
                break;
            } catch (EasyvereinApiException $error) {
                if (!membership_is_login_conflict($error) || $attempt >= 3) {
                    throw $error;
                }

                $nextSuffix = membership_login_suffix($data, $row['easyverein_login']) + 1;
                $row['easyverein_login'] = membership_pick_login($data, $id, $nextSuffix);
                member_area_db()
                    ->prepare('UPDATE membership_applications SET easyverein_login = ? WHERE id = ?')
                    ->execute([$row['easyverein_login'], $id]);
            }
        }

        $row['easyverein_member_id'] = (int)$member['id'];
        membership_mark_step($id, 'easyverein_member_id', (string)$row['easyverein_member_id']);
    }

    $memberId = (int)$row['easyverein_member_id'];
    $now = membership_db_time(new DateTimeImmutable('now', membership_timezone()));

    // 4. Membership type
    if ($row['easyverein_group_assigned_at'] === null) {
        easyverein_api_send('POST', '/member-group-assignment', [
            'user_object' => $memberId,
            'member_group' => $data['group']['id'],
            'payment_active' => true,
        ]);
        membership_mark_step($id, 'easyverein_group_assigned_at', $now);
    }

    // 5. Application PDF as application document. The SEPA mandate PDF goes in as declaration of
    // consent, which easyVerein shows with the application, and as SEPA mandate document.
    if ($row['easyverein_files_uploaded_at'] === null) {
        easyverein_api_upload(
            "/member/{$memberId}",
            'declaration_of_application',
            membership_render_application_pdf($row),
            membership_application_pdf_filename($row['reference']),
            'application/pdf'
        );
        $sepaPdf = membership_render_sepa_pdf($row);

        foreach (['declaration_of_consent', 'sepa_mandate_file'] as $field) {
            easyverein_api_upload(
                "/member/{$memberId}",
                $field,
                $sepaPdf,
                membership_sepa_pdf_filename($row['reference']),
                'application/pdf'
            );
        }

        membership_mark_step($id, 'easyverein_files_uploaded_at', $now);
    }

    $outcome = trim("In easyVerein als Mitgliedsantrag angelegt (Login: {$row['easyverein_login']}). " . implode(' ', $notes));

    member_area_db()
        ->prepare("UPDATE membership_applications SET status = 'synced', synced_at = ?, sync_note = ?, last_error = NULL WHERE id = ?")
        ->execute([$now, mb_substr($outcome, 0, 500), $id]);

    return $outcome;
}

/**
 * Which suffix number $login is for this application's base address (0 = the address itself).
 */
function membership_login_suffix(array $data, string $login): int
{
    $base = mb_strtolower(membership_login_base($data));

    for ($suffix = 0; $suffix <= MEMBERSHIP_LOGIN_MAX_SUFFIX; $suffix++) {
        if (membership_login_candidate($base, $suffix) === $login) {
            return $suffix;
        }
    }

    return MEMBERSHIP_LOGIN_MAX_SUFFIX;
}
