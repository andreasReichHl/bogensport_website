<?php

function member_area_escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function member_area_page_start(string $title): void
{
    $currentPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $dashboardCurrent = $currentPath === '/mitglieder/dashboard.php' ? ' aria-current="page"' : '';
    $logoutCurrent = $currentPath === '/mitglieder/logout.php' ? ' aria-current="page"' : '';
    $currentUser = function_exists('member_area_current_user') ? member_area_current_user() : null;
    $isLoggedIn = $currentUser !== null;

    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . member_area_escape($title) . ' - Mitgliederbereich</title>';
    echo '<style>
        @font-face{font-family:Montserrat;src:url("/assets/fonts/Montserrat-VariableFont_wght.ttf") format("truetype");font-weight:100 900;font-style:normal;font-display:swap}
        @font-face{font-family:Inter;src:url("/assets/fonts/Inter-Light.woff2") format("woff2");font-weight:300;font-style:normal;font-display:swap}
        @font-face{font-family:Inter;src:url("/assets/fonts/Inter-Regular.woff2") format("woff2");font-weight:400;font-style:normal;font-display:swap}
        @font-face{font-family:Inter;src:url("/assets/fonts/Inter-Medium.woff2") format("woff2");font-weight:500;font-style:normal;font-display:swap}
        @font-face{font-family:Inter;src:url("/assets/fonts/Inter-SemiBold.woff2") format("woff2");font-weight:600;font-style:normal;font-display:swap}
        @font-face{font-family:Inter;src:url("/assets/fonts/Inter-Bold.woff2") format("woff2");font-weight:700;font-style:normal;font-display:swap}
        :root{--brand:#002f5f;--brand-dark:#00264d;--link:#1d4ed8;--text:#0f172a;--muted:#475569;--line:#e2e8f0;--surface:#fff;--page:#f8fafc}
        *{box-sizing:border-box}
        html{font-family:Inter,ui-sans-serif,system-ui,sans-serif;scrollbar-gutter:stable}
        body{margin:0;background:var(--page);color:var(--text);font-family:Inter,ui-sans-serif,system-ui,sans-serif;font-size:16px;line-height:1.5}
        h1,h2,h3,h4,h5,h6,nav,button,.button,.site-brand{font-family:Montserrat,ui-sans-serif,system-ui,sans-serif}
        h1{margin:0 0 24px;font-size:clamp(1.875rem,3vw,2.5rem);line-height:1.1;font-weight:700;color:var(--brand)}
        p{margin:0 0 16px;color:var(--muted)}
        a{color:var(--link)}
        .site-header{position:relative;z-index:50;background:var(--brand);color:#fff;box-shadow:0 10px 15px rgba(15,23,42,.18)}
        .site-header-inner{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:12px 16px}
        .site-brand{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none;min-width:0}
        .site-logo-frame{display:block;width:36px;height:36px;overflow:hidden;flex:0 0 auto}
        .site-logo{display:block;height:100%;width:auto;max-width:none}
        .site-brand-text{display:flex;flex-direction:column;line-height:1.08;letter-spacing:.025em;white-space:nowrap}
        .site-brand-kicker{font-size:14px;font-weight:700}
        .site-brand-name{font-size:18px;font-weight:400}
        .site-actions{display:flex;align-items:center;justify-content:flex-end;gap:28px;flex:1}
        .site-user{display:inline-flex;align-items:center;gap:8px;color:#fff;font-family:Montserrat,ui-sans-serif,system-ui,sans-serif;font-size:14px;font-weight:600;white-space:nowrap}
        .site-user-icon{display:inline-flex;width:30px;height:30px;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.35);border-radius:999px;background:rgba(255,255,255,.08)}
        .site-user-icon svg{width:17px;height:17px}
        .site-nav{display:flex;align-items:center;gap:28px;font-size:14px}
        .site-nav a{color:#fff;text-decoration:none;transition:color .15s ease}
        .site-nav a:hover,.site-nav a:focus{color:#93c5fd}
        .site-nav a[aria-current=page]{color:#93c5fd;text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:8px}
        main{max-width:1120px;margin:0 auto;padding:40px 20px 56px}
        .panel{background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.08)}
        .page-intro{margin-bottom:28px}
        .tile-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:24px}
        .tile{display:block;min-height:130px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:22px;color:var(--text);text-decoration:none;box-shadow:0 10px 24px rgba(15,23,42,.07);transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}
        .tile:hover,.tile:focus{border-color:#93c5fd;box-shadow:0 16px 30px rgba(15,23,42,.12);color:var(--text);transform:translateY(-1px)}
        .tile-title{display:block;font-family:Montserrat,ui-sans-serif,system-ui,sans-serif;font-size:18px;font-weight:700;color:var(--brand);line-height:1.25}
        .tile-description{display:block;margin:10px 0 0;color:var(--muted)}
        .form-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
        .checkbox-label{display:flex;align-items:flex-start;gap:10px;font-weight:500}
        .checkbox-label input{width:auto;margin:5px 0 0}
        .editor-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}
        .editor-toolbar button{min-height:34px;padding:0 10px;background:#e2e8f0;color:var(--text)}
        .editor-toolbar button:hover{background:#cbd5e1;color:var(--text)}
        .rich-editor{min-height:220px;margin-top:6px;padding:14px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:var(--text)}
        .rich-editor:focus{border-color:#2563eb;outline:3px solid rgba(37,99,235,.18)}
        .news-list{display:grid;gap:18px;margin-top:24px}
        .news-card{display:grid;gap:18px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:22px;box-shadow:0 10px 24px rgba(15,23,42,.07)}
        .news-card.with-image{grid-template-columns:minmax(180px,280px) 1fr}
        .news-image{width:100%;height:180px;object-fit:cover;border-radius:8px}
        .news-meta{margin:0 0 10px;color:var(--muted);font-size:14px}
        .news-content h2,.news-content h3{margin:18px 0 8px;color:var(--brand)}
        .news-content p{margin:0 0 12px}
        .news-content blockquote{margin:16px 0;padding:12px 16px;border-left:4px solid #93c5fd;background:#eff6ff;color:var(--text)}
        .news-content ul,.news-content ol{margin:0 0 12px;padding-left:24px;color:var(--muted)}
        .visibility-list{display:flex;gap:10px;flex-wrap:wrap;color:var(--muted);font-size:14px}
        label{display:block;margin:0 0 16px;color:var(--text);font-weight:600}
        input,select{display:block;width:100%;margin-top:6px;padding:11px 12px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:var(--text);font:inherit}
        input[type=file]{padding:9px 12px}
        input:focus,select:focus{border-color:#2563eb;outline:3px solid rgba(37,99,235,.18)}
        button,.button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border:0;border-radius:6px;background:#2563eb;color:#fff;font-size:14px;font-weight:700;text-decoration:none;cursor:pointer;transition:background .15s ease,color .15s ease}
        button:hover,.button:hover{background:#1d4ed8;color:#fff}
        .button.secondary{background:#e2e8f0;color:var(--text)}
        .button.secondary:hover{background:#cbd5e1;color:var(--text)}
        .error{color:#b91c1c;font-weight:700}
        table{width:100%;border-collapse:collapse;background:#fff}
        th,td{padding:12px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
        th{font-weight:700;color:var(--brand)}
        @media (min-width:640px){.site-header-inner{padding:16px 24px}.site-logo-frame{width:40px;height:40px}.site-brand-kicker{font-size:14px}.site-brand-name{font-size:20px}}
        @media (max-width:639px){.site-header-inner{align-items:flex-start;flex-direction:column}.site-actions{width:100%;align-items:flex-start;justify-content:flex-start;flex-direction:column;gap:14px}.site-nav{gap:20px}.panel{padding:22px 18px}.news-card.with-image{grid-template-columns:1fr}main{padding:28px 16px 44px}}
    </style>';
    echo '</head><body><header class="site-header"><div class="site-header-inner">';
    echo '<a class="site-brand" href="/" aria-label="Zur Startseite">';
    echo '<span class="site-logo-frame"><img class="site-logo" src="/assets/brand/bobs_logo_white_old.svg" alt=""></span>';
    echo '<span class="site-brand-text"><span class="site-brand-kicker">BAD OLDESLOER</span><span class="site-brand-name">BOGENSPORT e.V.</span></span>';
    echo '</a>';
    if ($isLoggedIn) {
        echo '<div class="site-actions">';
        echo '<nav class="site-nav" aria-label="Mitgliederbereich">';
        echo '<a href="/mitglieder/dashboard.php"' . $dashboardCurrent . '>Dashboard</a>';
        echo '<a href="/mitglieder/logout.php"' . $logoutCurrent . '>Logout</a>';
        echo '</nav>';
        echo '<div class="site-user" aria-label="Angemeldet als ' . member_area_escape($currentUser['username'] ?? '') . '">';
        echo '<span class="site-user-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg></span>';
        echo '<span>' . member_area_escape($currentUser['username'] ?? '') . '</span>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div></header><main>';
}

function member_area_page_end(): void
{
    echo '</main></body></html>';
}
