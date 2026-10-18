<?php

/**
 * Storage of membership applications.
 *
 * The application data (incl. IBAN and date of birth) is stored encrypted (libsodium
 * secretbox, key membership.data_key). Only what's needed for workflow, rate limiting and
 * the confirmation proof is kept in plain columns.
 */

require_once __DIR__ . '/config.php';

const MEMBERSHIP_MAX_SUBMISSIONS_PER_IP_PER_HOUR = 5;
const MEMBERSHIP_MAX_SUBMISSIONS_PER_EMAIL_PER_DAY = 3;

class MembershipRateLimitException extends RuntimeException
{
}

function membership_ensure_applications_table(): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    member_area_db()->exec(
        "CREATE TABLE IF NOT EXISTS membership_applications (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          reference VARCHAR(20) NOT NULL UNIQUE,
          status ENUM('pending', 'confirmed', 'synced', 'sync_failed') NOT NULL DEFAULT 'pending',
          data_encrypted MEDIUMBLOB NOT NULL,
          email_hash CHAR(64) NOT NULL,
          confirm_token_hash CHAR(64) NULL UNIQUE,
          confirm_expires_at DATETIME NOT NULL,
          submitted_at DATETIME NOT NULL,
          submitted_ip VARCHAR(45) NOT NULL,
          submitted_user_agent VARCHAR(255) NOT NULL DEFAULT '',
          confirmed_at DATETIME NULL,
          confirmed_ip VARCHAR(45) NULL,
          confirmed_user_agent VARCHAR(255) NULL,
          signature CHAR(64) NULL,
          easyverein_contact_id BIGINT UNSIGNED NULL,
          easyverein_member_id BIGINT UNSIGNED NULL,
          sync_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
          last_error VARCHAR(500) NULL,
          synced_at DATETIME NULL,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_membership_applications_status (status, confirm_expires_at),
          INDEX idx_membership_applications_ip (submitted_ip, submitted_at),
          INDEX idx_membership_applications_email (email_hash, submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ensured = true;
}

function membership_encrypt(array $data): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    return $nonce . sodium_crypto_secretbox($json, $nonce, membership_config_key('data_key'));
}

function membership_decrypt(string $encrypted): array
{
    $nonce = substr($encrypted, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($encrypted, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $json = sodium_crypto_secretbox_open($ciphertext, $nonce, membership_config_key('data_key'));

    if ($json === false) {
        throw new RuntimeException('Membership application data could not be decrypted');
    }

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}

function membership_email_hash(string $email): string
{
    // Keyed, so the plain column can't be reversed by hashing known addresses.
    return hash_hmac('sha256', 'email|' . mb_strtolower($email), membership_config_key('signature_key'));
}

// All times are computed in PHP and passed in, never via SQL NOW(): PHP and MySQL may run in
// different time zones (locally PHP = UTC, the Docker MySQL = Europe/Berlin).
function membership_db_time(DateTimeImmutable $time): string
{
    return $time->format('Y-m-d H:i:s');
}

function membership_delete_expired_pending(DateTimeImmutable $now): void
{
    member_area_db()
        ->prepare("DELETE FROM membership_applications WHERE status = 'pending' AND confirm_expires_at < ?")
        ->execute([membership_db_time($now)]);
}

function membership_assert_rate_limit(string $ip, string $emailHash, DateTimeImmutable $now): void
{
    $statement = member_area_db()->prepare(
        'SELECT
           SUM(submitted_ip = ? AND submitted_at >= ?) AS ip_count,
           SUM(email_hash = ?) AS email_count
         FROM membership_applications
         WHERE (submitted_ip = ? OR email_hash = ?)
           AND submitted_at >= ?'
    );
    $statement->execute([
        $ip,
        membership_db_time($now->modify('-1 hour')),
        $emailHash,
        $ip,
        $emailHash,
        membership_db_time($now->modify('-1 day')),
    ]);
    $counts = $statement->fetch() ?: [];

    if ((int)($counts['ip_count'] ?? 0) >= MEMBERSHIP_MAX_SUBMISSIONS_PER_IP_PER_HOUR
        || (int)($counts['email_count'] ?? 0) >= MEMBERSHIP_MAX_SUBMISSIONS_PER_EMAIL_PER_DAY) {
        throw new MembershipRateLimitException('Too many membership applications');
    }
}

function membership_generate_reference(DateTimeImmutable $now): string
{
    return 'OM-' . $now->format('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Stores a validated application as "pending" and returns its reference and the plain
 * confirmation token (only its hash is stored - the token itself exists only in the mail).
 *
 * @return array{reference: string, token: string, expiresAt: DateTimeImmutable}
 */
function membership_store_application(
    array $application,
    string $confirmationEmail,
    string $ip,
    string $userAgent,
    DateTimeImmutable $now
): array {
    membership_ensure_applications_table();
    membership_delete_expired_pending($now);

    $emailHash = membership_email_hash($confirmationEmail);
    membership_assert_rate_limit($ip, $emailHash, $now);

    $token = bin2hex(random_bytes(32));
    $expiresAt = $now->modify('+' . membership_config()['confirm_ttl_hours'] . ' hours');
    $encrypted = membership_encrypt($application);

    $statement = member_area_db()->prepare(
        'INSERT INTO membership_applications
           (reference, data_encrypted, email_hash, confirm_token_hash, confirm_expires_at,
            submitted_at, submitted_ip, submitted_user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    for ($attempt = 1; ; $attempt++) {
        $reference = membership_generate_reference($now);

        try {
            $statement->execute([
                $reference,
                $encrypted,
                $emailHash,
                hash('sha256', $token),
                membership_db_time($expiresAt),
                membership_db_time($now),
                $ip,
                mb_substr($userAgent, 0, 255),
            ]);

            return ['reference' => $reference, 'token' => $token, 'expiresAt' => $expiresAt];
        } catch (PDOException $error) {
            // 23000 = duplicate reference (16.7M per year, so practically never) - retry.
            if ($error->getCode() !== '23000' || $attempt >= 3) {
                throw $error;
            }
        }
    }
}
