<?php

function member_area_private_path(): string
{
    $configuredPath = getenv('MEMBER_AREA_PRIVATE_PATH');

    $candidates = array_filter([
        $configuredPath ?: null,
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
