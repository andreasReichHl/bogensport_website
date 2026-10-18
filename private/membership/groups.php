<?php

/**
 * Membership types offered in the online application = easyVerein member groups.
 *
 * Which groups appear is controlled in easyVerein itself: only groups with the setting
 * "Im Aufnahmeformular anzeigen" (show_in_applicationform) are offered. That way the board
 * can change the selection and the fees without touching the website.
 */

require_once __DIR__ . '/../easyverein/client.php';
require_once __DIR__ . '/config.php';

const MEMBERSHIP_GROUPS_CACHE_SECONDS = 600;

function membership_groups_cache_file(): string
{
    return sys_get_temp_dir() . '/bobs-membership-groups-' . md5(__DIR__) . '.json';
}

/**
 * @return list<array{id:int,name:string,amount:?float,intervalMonths:int,maxAge:?int,admissionFee:float}>
 */
function membership_groups(): array
{
    $admissionFees = membership_config()['admission_fees'];
    $groups = [];

    foreach (membership_easyverein_groups() as $group) {
        if (!array_key_exists($group['id'], $admissionFees)) {
            error_log("Membership group {$group['id']} ({$group['name']}) is offered in easyVerein but has no admission fee configured - not shown in the form");
            continue;
        }

        $groups[] = [...$group, 'admissionFee' => $admissionFees[$group['id']]];
    }

    return $groups;
}

/**
 * The groups ticked "Im Aufnahmeformular anzeigen" in easyVerein (cached).
 */
function membership_easyverein_groups(): array
{
    $cacheFile = membership_groups_cache_file();

    if (is_file($cacheFile) && filemtime($cacheFile) > time() - MEMBERSHIP_GROUPS_CACHE_SECONDS) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);

        if (is_array($cached)) {
            return $cached;
        }
    }

    $groups = easyverein_api_get_all('/member-group', [
        'query' => '{id,name,payment_amount,payment_interval,show_in_applicationform,age_permission}',
    ]);

    $offered = [];

    foreach ($groups as $group) {
        if (($group['show_in_applicationform'] ?? false) !== true) {
            continue;
        }

        $amount = $group['payment_amount'] ?? null;
        $maxAge = (int)($group['age_permission'] ?? 0);

        $offered[] = [
            'id' => (int)$group['id'],
            'name' => (string)$group['name'],
            'amount' => is_numeric($amount) ? (float)$amount : null,
            'intervalMonths' => (int)($group['payment_interval'] ?? 12),
            // easyVerein uses 0 for "no age limit".
            'maxAge' => $maxAge > 0 ? $maxAge : null,
        ];
    }

    @file_put_contents($cacheFile, json_encode($offered), LOCK_EX);

    return $offered;
}

function membership_find_group(int $groupId): ?array
{
    foreach (membership_groups() as $group) {
        if ($group['id'] === $groupId) {
            return $group;
        }
    }

    return null;
}
