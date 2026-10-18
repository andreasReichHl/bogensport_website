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
