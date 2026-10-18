# Bad Oldesloer Bogensport e.V. — Website

Vereinswebsite mit öffentlichem Auftritt (React) und einem PHP-basierten Mitgliederbereich.

## Stack

- **Frontend**: React 19 + React Router, gebaut mit Vite + Tailwind CSS v4
- **Backend**: Plain PHP (kein Framework, kein Composer) für `/mitglieder/*` und die öffentlichen Endpunkte `news-feed.php` / `termine-feed.php`
- **Datenbank**: MySQL/MariaDB (Zugriff ausschließlich über PDO mit Prepared Statements)
- **easyVerein**: Termine/Kalender werden live über die easyVerein-API (`private/easyverein/`) geladen, nicht mehr über Nextcloud/ICS

## Projektstruktur

- `src/` — React-App (Startseite, News, Termine, statische Seiten)
- `public/` — Von Vite direkt kopierte statische Dateien + die PHP-Endpunkte (`news-feed.php`, `termine-feed.php`, `termine-attachment.php`, `mitglieder/*.php`)
- `private/member-area/` — PHP-Backend-Logik des Mitgliederbereichs (Auth, DB-Zugriff, News-Verwaltung). Liegt bewusst außerhalb des Document-Roots.
- `private/easyverein/` — easyVerein-API-Integration (Token-Verwaltung + generischer API-Client), ebenfalls außerhalb des Document-Roots.
- `dist/` — Build-Output von `npm run build` (nicht versioniert)

## Setup

```bash
npm install
cp private/member-area/config.local.example.php private/member-area/config.local.php
# config.local.php mit echten DB-Zugangsdaten und einem eigenen JWT-Secret befüllen
```

`config.local.example.php` ist ausschließlich für die lokale Entwicklung gedacht und wird automatisch geladen, wenn PHP über den eingebauten Dev-Server (`php -S`) läuft. In jeder anderen Umgebung wird `config.local.php` benötigt.

## Entwicklung

```bash
npm run dev          # Vite Dev-Server (React-Frontend, Port 5173)
```

Der Dev-Server proxyt `/news-feed.php`, `/termine-feed.php`, `/termine-attachment.php` und `/uploads/news` an `http://127.0.0.1:8000` (siehe `vite.config.js`) — für den vollen Funktionsumfang (News, Termine, Mitgliederbereich) muss parallel der PHP-Server laufen:

```bash
npm run serve:php    # baut das Frontend und startet `php -S 127.0.0.1:8000 -t dist`
```

## Weitere Befehle

```bash
npm run build         # Produktions-Build nach dist/
npm run lint           # ESLint über das gesamte Projekt
npm run preview        # Vite-Preview des Produktions-Builds
php -l <datei>          # Syntax-Check einzelner PHP-Dateien
php private/member-area/check-db.php   # DB-Verbindungscheck (nur per CLI nutzbar)
```

## Umgebungsvariablen

| Variable | Wo | Zweck |
|---|---|---|
| `MEMBER_AREA_CONFIG_FILE` | PHP | Pfad zu einer alternativen Config-Datei statt `config.local.(example.)php` |
| `MEMBER_AREA_PRIVATE_PATH` | PHP | Pfad zu `private/member-area`, falls abweichend vom Standard-Layout |
| `MEMBER_AREA_NEWS_IMAGE_RETENTION_DAYS` | PHP | Wie lange Bilder abgelaufener News-Beiträge aufbewahrt werden (Default: 30) |
| `VITE_BASE_PATH` | Vite | Basis-Pfad, falls die App nicht unter `/` ausgeliefert wird |
| `VITE_FORMSPREE_ENDPOINT` | Vite | Formspree-Endpunkt für das Probetraining-Formular |
| `EASYVEREIN_API_TOKEN` | PHP | Einmaliger Bootstrap-Token für die easyVerein-API (siehe unten) |

## Mitgliederbereich

Login unter `/mitglieder/login.php`. Rollen: `superadmin`, `admin`, `member` (siehe `private/member-area/auth.php`). News-Inhalte durchlaufen vor dem Speichern **und** bei jedem Lesen einen DOM-basierten HTML-Sanitizer (`member_area_news_sanitize_html()` in `private/member-area/news.php`).

## easyVerein-Integration

Termine werden live aus dem easyVerein-Kalender **"Turniere"** geladen (`GET /event?calendar=…&is_public=true`), inklusive eines optionalen Anhangs über das Custom Field **"Anhang"**. Beide Namen sind in `public/termine-feed.php` / `public/termine-attachment.php` als Konstanten hinterlegt.

Der API-Token wird automatisch verwaltet (`private/easyverein/token.php`): er liegt in der Tabelle `easyverein_tokens`, wird nach 15 von 30 Tagen Gültigkeit automatisch erneuert (`GET /refresh-token`) und ist race-safe für mehrere gleichzeitige PHP-Worker. Für den allerersten Start (keine Zeile in der Tabelle vorhanden) muss einmalig ein in der easyVerein-Oberfläche (Einstellungen → easyVerein API) generierter Token über die Umgebungsvariable `EASYVEREIN_API_TOKEN` gesetzt werden.
