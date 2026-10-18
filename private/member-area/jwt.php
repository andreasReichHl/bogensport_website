<?php

require_once __DIR__ . '/config.php';

function jwt_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function jwt_base64url_decode(string $value): string
{
    $remainder = strlen($value) % 4;

    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);

    if ($decoded === false) {
        throw new RuntimeException('Invalid token encoding');
    }

    return $decoded;
}

function jwt_sign(array $payload): string
{
    $config = member_area_config()['jwt'];
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $segments = [
        jwt_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES)),
        jwt_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
    ];
    $signature = hash_hmac('sha256', implode('.', $segments), $config['secret'], true);
    $segments[] = jwt_base64url_encode($signature);

    return implode('.', $segments);
}

function jwt_verify(string $token): array
{
    $config = member_area_config()['jwt'];
    $segments = explode('.', $token);

    if (count($segments) !== 3) {
        throw new RuntimeException('Invalid token format');
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
    $expectedSignature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $config['secret'], true);
    $actualSignature = jwt_base64url_decode($encodedSignature);

    if (!hash_equals($expectedSignature, $actualSignature)) {
        throw new RuntimeException('Invalid token signature');
    }

    $payload = json_decode(jwt_base64url_decode($encodedPayload), true);

    if (!is_array($payload)) {
        throw new RuntimeException('Invalid token payload');
    }

    $now = time();

    if (($payload['exp'] ?? 0) < $now) {
        throw new RuntimeException('Token expired');
    }

    if (($payload['nbf'] ?? 0) > $now) {
        throw new RuntimeException('Token not valid yet');
    }

    if (($payload['iss'] ?? '') !== $config['issuer']) {
        throw new RuntimeException('Invalid token issuer');
    }

    if (($payload['aud'] ?? '') !== $config['audience']) {
        throw new RuntimeException('Invalid token audience');
    }

    return $payload;
}
