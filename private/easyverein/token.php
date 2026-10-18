<?php

/**
 * easyVerein API token management.
 *
 * Per https://easyverein.com/api/v3.0/documentation#/refresh-token/get_api_v3_0_refresh_token:
 * - Every easyVerein API call is authenticated with `Authorization: Bearer <token>`.
 * - A token is valid for EASYVEREIN_TOKEN_LIFETIME_DAYS (30) days and should be replaced
 *   after half that time (EASYVEREIN_TOKEN_REFRESH_AFTER_DAYS, 15 days).
 * - GET /api/v3.0/refresh-token, authenticated with the *current* token, returns a fresh
 *   token and invalidates the old one immediately. If no refresh is needed yet it returns
 *   the current token unchanged.
 * - There is no "create token" endpoint - the very first token must be generated manually
 *   in the easyVerein dashboard (Einstellungen -> easyVerein API) and seeded once via the
 *   EASYVEREIN_API_TOKEN environment variable.
 *
 * NOTE: the official spec does not document a response schema for GET /refresh-token at all
 * (no "responses" object on that operation). easyverein_extract_token() below is written
 * defensively to handle the most likely response shapes (bare JSON string, or an object with
 * a token/key/access_token field). Verify it against a real response once a token is
 * available and adjust the field list if easyVerein's actual response differs.
 */

require_once __DIR__ . '/../member-area/db.php';

const EASYVEREIN_API_BASE_URL = 'https://easyverein.com/api/v3.0';
const EASYVEREIN_TOKEN_LIFETIME_DAYS = 30;
const EASYVEREIN_TOKEN_REFRESH_AFTER_DAYS = 15;

class EasyvereinTokenException extends RuntimeException
{
}

function easyverein_ensure_token_table(): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    try {
        member_area_db()->query('SELECT 1 FROM easyverein_tokens LIMIT 1');
        $ensured = true;
        return;
    } catch (PDOException $error) {
        $errorInfo = $error->errorInfo;
        $sqlState = (string)($errorInfo[0] ?? $error->getCode());
        $driverErrorCode = (int)($errorInfo[1] ?? 0);

        if ($sqlState !== '42S02' && $driverErrorCode !== 1146) {
            throw $error;
        }
    }

    member_area_db()->exec(
        "CREATE TABLE IF NOT EXISTS easyverein_tokens (
          id TINYINT UNSIGNED PRIMARY KEY,
          token VARCHAR(255) NOT NULL,
          refreshed_at DATETIME NOT NULL,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;
}

function easyverein_seed_token_from_env(): ?string
{
    $token = trim((string)(getenv('EASYVEREIN_API_TOKEN') ?: ''));

    return $token !== '' ? $token : null;
}

function easyverein_store_token(PDO $pdo, string $token, DateTimeImmutable $refreshedAt): void
{
    $statement = $pdo->prepare(
        'INSERT INTO easyverein_tokens (id, token, refreshed_at) VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE token = VALUES(token), refreshed_at = VALUES(refreshed_at)'
    );
    $statement->execute([$token, $refreshedAt->format('Y-m-d H:i:s')]);
}

function easyverein_is_refresh_due(DateTimeImmutable $refreshedAt): bool
{
    return new DateTimeImmutable('now') >= $refreshedAt->modify('+' . EASYVEREIN_TOKEN_REFRESH_AFTER_DAYS . ' days');
}

/**
 * Extraction of the token value from a GET /refresh-token response body.
 *
 * Verified against a real response on 2026-10-15: the body is `{"Bearer":"<token>"}`. The
 * other field names are kept as a defensive fallback in case this changes with an API
 * version bump, but "Bearer" is the confirmed, authoritative shape.
 */
function easyverein_extract_token(string $body): ?string
{
    $decoded = json_decode($body, true);

    if (is_string($decoded) && trim($decoded) !== '') {
        return trim($decoded);
    }

    if (is_array($decoded)) {
        foreach (['Bearer', 'token', 'key', 'access_token', 'auth_token'] as $field) {
            if (isset($decoded[$field]) && is_string($decoded[$field]) && trim($decoded[$field]) !== '') {
                return trim($decoded[$field]);
            }
        }
    }

    $trimmedBody = trim($body);

    // Last-resort fallback: some endpoints return the bare token as plain text despite
    // declaring an application/json content type.
    if ($trimmedBody !== '' && !str_contains($trimmedBody, '{') && !str_contains($trimmedBody, '<')) {
        return $trimmedBody;
    }

    return null;
}

function easyverein_http_get(string $url, string $bearerToken): array
{
    $responseHeaders = [];
    $collectHeader = static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
        $parts = explode(':', $headerLine, 2);

        if (count($parts) === 2) {
            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }

        return strlen($headerLine);
    };

    if (function_exists('curl_init')) {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $bearerToken,
                'Accept: application/json',
            ],
            CURLOPT_HEADERFUNCTION => $collectHeader,
        ]);

        $body = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);

        if ($body === false) {
            throw new EasyvereinTokenException('easyVerein request failed: ' . ($error ?: 'unknown error'));
        }

        return ['status' => (int)$statusCode, 'headers' => $responseHeaders, 'body' => (string)$body];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => "Authorization: Bearer {$bearerToken}\r\nAccept: application/json\r\n",
        ],
    ]);

    $body = file_get_contents($url, false, $context);

    if ($body === false) {
        throw new EasyvereinTokenException('easyVerein request failed');
    }

    $statusCode = 0;

    foreach ($http_response_header ?? [] as $headerLine) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $statusMatch)) {
            $statusCode = (int)$statusMatch[1];
            continue;
        }

        $collectHeader(null, $headerLine);
    }

    return ['status' => $statusCode, 'headers' => $responseHeaders, 'body' => (string)$body];
}

function easyverein_call_refresh_endpoint(string $currentToken): string
{
    $response = easyverein_http_get(EASYVEREIN_API_BASE_URL . '/refresh-token', $currentToken);

    if ($response['status'] !== 200) {
        throw new EasyvereinTokenException(
            "easyVerein refresh-token request failed with status {$response['status']}"
        );
    }

    $token = easyverein_extract_token($response['body']);

    if ($token === null) {
        throw new EasyvereinTokenException('Could not parse token from easyVerein refresh-token response');
    }

    return $token;
}

/**
 * The main entry point other code should use: returns a token that is guaranteed to still
 * be valid, transparently seeding (first run) or refreshing (every ~15 days) it as needed.
 *
 * Uses SELECT ... FOR UPDATE so concurrent callers (e.g. multiple PHP-FPM workers) can't
 * both decide a refresh is due and race each other - easyVerein invalidates the old token
 * the moment a new one is issued, so a race would otherwise risk one worker storing a token
 * that the other worker's concurrent refresh immediately invalidated.
 */
function easyverein_get_valid_token(): string
{
    easyverein_ensure_token_table();

    $pdo = member_area_db();
    $pdo->beginTransaction();
    $previousToken = null;
    $previousTokenExpiresAt = null;

    try {
        $statement = $pdo->prepare('SELECT token, refreshed_at FROM easyverein_tokens WHERE id = 1 LIMIT 1 FOR UPDATE');
        $statement->execute();
        $row = $statement->fetch();

        if ($row === false) {
            $seedToken = easyverein_seed_token_from_env();

            if ($seedToken === null) {
                $pdo->rollBack();

                throw new EasyvereinTokenException(
                    'No easyVerein API token stored yet. Generate one in the easyVerein dashboard ' .
                    '(Einstellungen -> easyVerein API) and set it once via the EASYVEREIN_API_TOKEN ' .
                    'environment variable to bootstrap it.'
                );
            }

            easyverein_store_token($pdo, $seedToken, new DateTimeImmutable('now'));
            $pdo->commit();

            return $seedToken;
        }

        $refreshedAt = new DateTimeImmutable($row['refreshed_at']);
        $previousToken = $row['token'];
        $previousTokenExpiresAt = $refreshedAt->modify('+' . EASYVEREIN_TOKEN_LIFETIME_DAYS . ' days');

        if (!easyverein_is_refresh_due($refreshedAt)) {
            $pdo->commit();

            return $previousToken;
        }

        $newToken = easyverein_call_refresh_endpoint($previousToken);
        easyverein_store_token($pdo, $newToken, new DateTimeImmutable('now'));
        $pdo->commit();

        return $newToken;
    } catch (EasyvereinTokenException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Refreshing is recommended, not mandatory, until the token actually expires - if
        // the stored token is still within its 30-day lifetime, keep using it instead of
        // hard-failing the caller over e.g. a transient network error.
        if ($previousToken !== null && new DateTimeImmutable('now') < $previousTokenExpiresAt) {
            error_log('easyVerein token refresh failed, continuing with still-valid token: ' . $error->getMessage());

            return $previousToken;
        }

        throw $error;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}

function easyverein_auth_header(): string
{
    return 'Authorization: Bearer ' . easyverein_get_valid_token();
}
