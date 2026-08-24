<?php

function member_area_escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function member_area_format_date(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d.m.Y', $timestamp);
}

function member_area_flash_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function member_area_set_flash(string $message, string $type = 'success'): void
{
    member_area_flash_session_start();
    $_SESSION['member_area_flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function member_area_take_flash(): ?array
{
    member_area_flash_session_start();
    $flash = $_SESSION['member_area_flash'] ?? null;
    unset($_SESSION['member_area_flash']);

    return is_array($flash) ? $flash : null;
}

function member_area_asset_url(string $path): string
{
    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $version = '20260818-1';

    if ($documentRoot !== '') {
        $assetPath = $documentRoot . $path;

        if (is_file($assetPath)) {
            $version = (string)filemtime($assetPath);
        }
    }

    return $path . '?v=' . rawurlencode($version);
}

function member_area_page_start(string $title): void
{
    $currentPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $dashboardCurrent = $currentPath === '/mitglieder/dashboard.php' ? ' aria-current="page"' : '';
    $profileCurrent = $currentPath === '/mitglieder/profil.php' ? ' aria-current="page"' : '';
    $logoutCurrent = $currentPath === '/mitglieder/logout.php' ? ' aria-current="page"' : '';
    $currentUser = null;

    if (function_exists('member_area_current_user')) {
        try {
            $currentUser = member_area_current_user();
        } catch (Throwable $error) {
            if (!function_exists('member_area_is_database_exception') || !member_area_is_database_exception($error)) {
                throw $error;
            }
        }
    }

    $isLoggedIn = $currentUser !== null;
    $flash = member_area_take_flash();

    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . member_area_escape($title) . ' - Mitgliederbereich</title>';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..24,400,0,0">';
    echo '<link rel="stylesheet" href="' . member_area_escape(member_area_asset_url('/mitglieder/assets/vendor/quill/quill.snow.css')) . '">';
    echo '<link rel="stylesheet" href="' . member_area_escape(member_area_asset_url('/mitglieder/assets/vendor/cropper/cropper.min.css')) . '">';
    echo '<link rel="stylesheet" href="' . member_area_escape(member_area_asset_url('/assets/shared/news-content.css')) . '">';
    echo '<link rel="stylesheet" href="' . member_area_escape(member_area_asset_url('/mitglieder/assets/member-area.css')) . '">';
    echo '<script src="' . member_area_escape(member_area_asset_url('/mitglieder/assets/member-menu.js')) . '" defer></script>';
    echo '</head><body>';
    if ($flash && !empty($flash['message'])) {
        $toastClass = ($flash['type'] ?? '') === 'error' ? 'toast error' : 'toast';
        echo '<div class="' . member_area_escape($toastClass) . '" role="status" aria-live="polite">' . member_area_escape((string)$flash['message']) . '</div>';
    }
    echo '<header class="site-header"><div class="site-header-inner">';
    echo '<a class="site-brand" href="/mitglieder/dashboard.php" aria-label="Zum Dashboard">';
    echo '<span class="site-logo-frame"><img class="site-logo" src="/assets/brand/bobs_logo_white_old.svg" alt=""></span>';
    echo '<span class="site-brand-text"><span class="site-brand-kicker">BAD OLDESLOER</span><span class="site-brand-name">BOGENSPORT e.V.</span></span>';
    echo '</a>';
    if ($isLoggedIn) {
        echo '<button class="site-menu-toggle" type="button" aria-label="Menü öffnen" aria-expanded="false" aria-controls="member_site_menu"><span class="site-menu-toggle-lines" aria-hidden="true"><span></span></span></button>';
        echo '<div class="site-actions" id="member_site_menu">';
        echo '<nav class="site-nav" aria-label="Mitgliederbereich">';
        echo '<a href="/mitglieder/dashboard.php"' . $dashboardCurrent . '>Dashboard</a>';
        echo '<a href="/mitglieder/profil.php"' . $profileCurrent . '>Profil</a>';
        echo '<a href="/mitglieder/logout.php"' . $logoutCurrent . '>Logout</a>';
        echo '</nav>';
        echo '<a class="site-user" href="/mitglieder/profil.php" aria-label="Profil von ' . member_area_escape($currentUser['username'] ?? '') . '">';
        echo '<span class="site-user-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg></span>';
        echo '<span>' . member_area_escape($currentUser['username'] ?? '') . '</span>';
        echo '</a>';
        echo '</div>';
    }
    echo '</div></header><main>';

}

function member_area_page_end(): void
{
    echo '</main></body></html>';
}
