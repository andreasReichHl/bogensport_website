<?php

require_once __DIR__ . '/db.php';

function member_area_modules(): array
{
    return [
        'users' => [
            'label' => 'Benutzer',
            'description' => 'Benutzerkonten und Berechtigungen verwalten.',
            'href' => '/mitglieder/admin/users.php',
            'roles' => ['superadmin', 'admin'],
            'grantable' => false,
        ],
        'tournaments' => [
            'label' => 'Turniere',
            'description' => 'Turniere und Meldungen einsehen.',
            'href' => '/mitglieder/turniere.php',
            'roles' => [],
            'grantable' => true,
        ],
        'news' => [
            'label' => 'News',
            'description' => 'Beiträge verfassen und veröffentlichen.',
            'href' => '/mitglieder/news.php',
            'roles' => [],
            'grantable' => true,
        ],
    ];
}

function member_area_grantable_modules(): array
{
    return array_filter(
        member_area_modules(),
        static fn(array $module): bool => (bool)($module['grantable'] ?? false)
    );
}

function member_area_user_module_keys(int $userId): array
{
    $statement = member_area_db()->prepare(
        'SELECT module_key FROM user_module_permissions WHERE user_id = ? ORDER BY module_key'
    );
    $statement->execute([$userId]);

    return array_column($statement->fetchAll(), 'module_key');
}

function member_area_can_access_module(array $user, string $moduleKey): bool
{
    $modules = member_area_modules();

    if (!isset($modules[$moduleKey])) {
        return false;
    }

    if (($user['role'] ?? '') === 'superadmin') {
        return true;
    }

    if (in_array($user['role'] ?? '', $modules[$moduleKey]['roles'] ?? [], true)) {
        return true;
    }

    return in_array($moduleKey, member_area_user_module_keys((int)$user['id']), true);
}

function member_area_accessible_modules(array $user): array
{
    return array_filter(
        member_area_modules(),
        static fn(string $key): bool => member_area_can_access_module($user, $key),
        ARRAY_FILTER_USE_KEY
    );
}

function member_area_require_module(string $moduleKey): array
{
    require_once __DIR__ . '/auth.php';

    $user = member_area_require_user();

    if (!member_area_can_access_module($user, $moduleKey)) {
        http_response_code(403);
        echo 'Zugriff verweigert.';
        exit;
    }

    return $user;
}
