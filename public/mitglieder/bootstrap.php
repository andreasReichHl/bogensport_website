<?php

function member_area_private_path(): string
{
    $configuredPath = getenv('MEMBER_AREA_PRIVATE_PATH');

    $candidates = array_filter([
        $configuredPath ?: null,
        __DIR__ . '/../private/member-area',
        __DIR__ . '/../../private/member-area',
        __DIR__ . '/../../../private/member-area',
        dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), 1) . '/private/member-area',
    ]);

    foreach ($candidates as $candidate) {
        $path = rtrim($candidate, '/');

        if (is_file($path . '/auth.php')) {
            return $path;
        }
    }

    throw new RuntimeException('Member area private path could not be found');
}

define('MEMBER_AREA_PRIVATE_PATH', member_area_private_path());

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    // No framing (clickjacking), no MIME sniffing, no foreign form targets.
    // X-Frame-Options comes from public/.htaccess; frame-ancestors takes precedence over it.
    header("Content-Security-Policy: frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
