<?php

/**
 * Generic, authenticated access to the easyVerein REST API, built on top of the token
 * management in token.php. This is the shared entry point other easyVerein features
 * (not just the calendar/Termine integration) should use.
 */

require_once __DIR__ . '/token.php';

const EASYVEREIN_DEFAULT_PAGE_LIMIT = 100; // API default is 5 per page, max is 100.

class EasyvereinApiException extends RuntimeException
{
}

/**
 * GET a single easyVerein API path (e.g. "/event/123" or "/calendar"), authenticated with
 * the current (auto-refreshed) token. Returns the json_decode()'d response body.
 *
 * $query is merged into the URL as query parameters (arrays/booleans are coerced to the
 * string form the API expects, e.g. true -> "true").
 */
function easyverein_api_get(string $path, array $query = []): array
{
    $queryString = easyverein_build_query_string($query);
    $url = EASYVEREIN_API_BASE_URL . $path . ($queryString !== '' ? '?' . $queryString : '');

    $response = easyverein_http_get($url, easyverein_get_valid_token());

    if ($response['status'] !== 200) {
        throw new EasyvereinApiException(
            "easyVerein API request to {$path} failed with status {$response['status']}"
        );
    }

    $decoded = json_decode($response['body'], true);

    if (!is_array($decoded)) {
        throw new EasyvereinApiException("easyVerein API returned invalid JSON for {$path}");
    }

    return $decoded;
}

/**
 * Like easyverein_api_get(), but for paginated list endpoints: follows the response's
 * "next" link until exhausted and returns the concatenated "results" across all pages.
 */
function easyverein_api_get_all(string $path, array $query = []): array
{
    $query += ['limit' => EASYVEREIN_DEFAULT_PAGE_LIMIT];
    $page = easyverein_api_get($path, $query);
    $results = is_array($page['results'] ?? null) ? $page['results'] : [];

    while (!empty($page['next']) && is_string($page['next'])) {
        $nextPath = easyverein_relative_api_path($page['next']);

        if ($nextPath === null) {
            break;
        }

        $page = easyverein_api_get($nextPath);
        $results = [...$results, ...(is_array($page['results'] ?? null) ? $page['results'] : [])];
    }

    return $results;
}

/**
 * Resolves an easyVerein "URL reference" field (e.g. "https://easyverein.com/api/v3.0/event/123")
 * to its numeric id, or null if it isn't one.
 */
function easyverein_id_from_reference(mixed $reference): ?int
{
    if (is_int($reference)) {
        return $reference;
    }

    if (is_string($reference) && preg_match('#/(\d+)/?$#', $reference, $match)) {
        return (int)$match[1];
    }

    return null;
}

function easyverein_relative_api_path(string $url): ?string
{
    $prefix = EASYVEREIN_API_BASE_URL;

    return str_starts_with($url, $prefix) ? substr($url, strlen($prefix)) : null;
}

function easyverein_build_query_string(array $query): string
{
    $parts = [];

    foreach ($query as $key => $value) {
        if ($value === null) {
            continue;
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
    }

    return implode('&', $parts);
}

/**
 * Write request (POST / PATCH / DELETE) with a JSON body. Returns the decoded response body
 * (empty array for 204). Throws EasyvereinApiException on any non-2xx status; the exception
 * carries the status and the (truncated) error body, which names the offending fields.
 */
function easyverein_api_send(string $method, string $path, ?array $body = null): array
{
    $headers = ['Accept: application/json'];
    $payload = null;

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    return easyverein_api_write_request($method, $path, $headers, $payload);
}

/**
 * Uploads a file into a file field of a resource (e.g. "declaration_of_application" of
 * /member/{id}). Per the API docs this is a PATCH with a Content-Disposition header and the
 * field name as multipart key.
 */
function easyverein_api_upload(string $path, string $field, string $content, string $filename, string $mimeType): array
{
    $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

    return easyverein_api_write_request(
        'PATCH',
        $path,
        ['Accept: application/json', "Content-Disposition: attachment; name=\"{$field}\"; filename=\"{$safeFilename}\""],
        [$field => new CURLStringFile($content, $safeFilename, $mimeType)]
    );
}

class EasyvereinApiRequestException extends EasyvereinApiException
{
    public function __construct(string $message, public readonly int $status, public readonly string $responseBody)
    {
        parent::__construct($message);
    }
}

function easyverein_api_write_request(string $method, string $path, array $headers, string|array|null $payload): array
{
    $curl = curl_init(EASYVEREIN_API_BASE_URL . $path);
    $headers[] = 'Authorization: Bearer ' . easyverein_get_valid_token();

    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if ($payload !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
    }

    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    if ($body === false) {
        throw new EasyvereinApiException("easyVerein {$method} {$path} failed: " . curl_error($curl));
    }

    if ($status < 200 || $status >= 300) {
        throw new EasyvereinApiRequestException(
            "easyVerein {$method} {$path} failed with status {$status}: " . mb_substr((string)$body, 0, 300),
            $status,
            (string)$body
        );
    }

    $decoded = json_decode((string)$body, true);

    return is_array($decoded) ? $decoded : [];
}
