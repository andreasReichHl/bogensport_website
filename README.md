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
- `private/membership/` — Online-Mitgliedsbeitritt (Validierung, verschlüsselte Speicherung, Mitgliedsarten aus easyVerein), ebenfalls außerhalb des Document-Roots.
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
| `EASYVEREIN_API_TOKEN` | PHP | Einmaliger Bootstrap-Token für die easyVerein-API (siehe unten) |

## Mitgliederbereich

Login unter `/mitglieder/login.php`. Rollen: `superadmin`, `admin`, `member` (siehe `private/member-area/auth.php`). News-Inhalte durchlaufen vor dem Speichern **und** bei jedem Lesen einen DOM-basierten HTML-Sanitizer (`member_area_news_sanitize_html()` in `private/member-area/news.php`).

## easyVerein-Integration

Termine werden live aus dem easyVerein-Kalender **"Turniere"** geladen (`GET /event?calendar=…&is_public=true`), inklusive eines optionalen Anhangs über das Custom Field **"Anhang"**. Beide Namen sind in `public/termine-feed.php` / `public/termine-attachment.php` als Konstanten hinterlegt.

Der API-Token wird automatisch verwaltet (`private/easyverein/token.php`): er liegt in der Tabelle `easyverein_tokens`, wird nach 15 von 30 Tagen Gültigkeit automatisch erneuert (`GET /refresh-token`) und ist race-safe für mehrere gleichzeitige PHP-Worker. Für den allerersten Start (keine Zeile in der Tabelle vorhanden) muss einmalig ein in der easyVerein-Oberfläche (Einstellungen → easyVerein API) generierter Token über die Umgebungsvariable `EASYVEREIN_API_TOKEN` gesetzt werden.

## Probetraining-Formular

`public/probetraining-absenden.php` (POST, JSON) prüft die Anfrage serverseitig (`private/probetraining/request.php`) und schickt sie per Mail an `trial_training_email` (Standard: info@) – über dasselbe SMTP-Postfach wie der Mitgliedsbeitritt (`membership.mail`), Antworten gehen per Reply-To direkt an den Absender. Kein externer Formulardienst. Spamschutz:

- Honeypot-Feld und Zeitfalle (abgeschickt weniger als 3 Sekunden nach dem Laden) – solche Anfragen bekommen eine Scheinbestätigung, es geht keine Mail raus.
- Links in Namen, Nachricht oder Gesundheitshinweisen werden abgelehnt.
- Limit: 5 Anfragen pro Stunde und IP, 30 pro Stunde insgesamt (greift es, steht ein Eintrag im PHP-Fehlerlog). Gespeichert wird dafür nur ein Hash der IP (Tabelle `probetraining_requests`, Einträge nach 24 Stunden gelöscht).
- Bestätigungsmails an die eingetragene Adresse gibt es bewusst nicht – das Formular kann so nicht missbraucht werden, um Mails an Dritte zu schicken.

## Online-Mitgliedsbeitritt

Eigenes Beitrittsformular, das ausschließlich die easyVerein-API nutzt (kein easyVerein-Iframe). Ablauf: Formular → Speicherung als `pending` → Bestätigungslink per Mail (Double-Opt-in) → Übertragung an easyVerein als Mitgliedsantrag (`is_application`) + PDF an den Antragsteller.

- `public/beitritt-formular.php` (GET) liefert die Mitgliedsarten und den Wortlaut aller Erklärungen (`private/membership/texts.php`, versioniert über `MEMBERSHIP_TEXTS_VERSION`).
- `public/beitritt-absenden.php` (POST, JSON) validiert serverseitig, speichert in `membership_applications` und verschickt den Bestätigungslink per Mail (SMTP über PHPMailer, `private/vendor/phpmailer/`). Schlägt der Mailversand fehl, wird der Antrag wieder gelöscht. Die Antragsdaten (inkl. IBAN) liegen dort **verschlüsselt** (libsodium); unbestätigte Anträge werden nach `confirm_ttl_hours` gelöscht. Spam-Schutz: Honeypot-Feld + Limits pro IP und E-Mail.
- `/mitglied-werden/bestaetigen?token=…` zeigt den Antrag (`beitritt-pruefen.php`); **bestätigt wird erst per Button** (`beitritt-bestaetigen.php`, POST), nicht schon beim Öffnen des Links – Mail-Virenscanner öffnen Links automatisch. Gespeichert werden Zeitpunkt, IP und Browser der Bestätigung.
- **Nach der Bestätigung** (`private/membership/process.php`): HMAC-SHA256-Prüfsumme über alle Angaben + Bestätigungsnachweis (`signature.php`, Schlüssel `signature_key`), PDF (tFPDF, `private/vendor/tfpdf/`) an den Antragsteller und an `board_email` (vorstand@). Jeder Schritt wird einzeln vermerkt und nur einmal ausgeführt – schlägt eine Mail fehl, wird sie beim nächsten Durchlauf nachgeholt. Das PDF wird nie gespeichert, sondern jeweils aus den Daten erzeugt.
- **Prüfsumme kontrollieren:** `php private/membership/verify.php OM-2026-XXXXXX` – meldet, ob die gespeicherten Daten noch zur Prüfsumme passen; stimmt diese mit der im PDF überein, ist das PDF unverändert.
- **Übertragung an easyVerein** (`private/membership/sync.php`, Schalter `easyverein_sync`: `off` | `dry-run` | `live`): legt Kontaktdaten (inkl. SEPA-Mandat), Mitglied mit `is_application` (= Mitgliedsantrag, den der Vorstand in easyVerein annimmt), Gruppenzuordnung und das PDF als Antrags- und Mandatsdokument an. Login = E-Mail-Adresse; ist sie vergeben, `name_1@…`, `name_2@…` (die echte Adresse steht als private E-Mail). „Über Elternteil verwalten“ verknüpft mit dem Mitglied, dessen Login die Eltern-Adresse ist. Lokal ist **`dry-run`** eingestellt: nur lesende Abfragen, die geplanten Anfragen landen im PHP-Log – nichts wird in das (produktive) easyVerein geschrieben. Produktion: `live`.
- **Cronjob** (stündlich – bei Strato „mehrfach täglich → stündlich“; Läufe können sich um bis zu 4 Stunden verzögern): `/bin/php -f ./private/membership/cron.php` (`private/` liegt im Webspace-Stammordner, einrichten unter Strato-Kundenlogin → Verwaltung → Cron-Jobs) holt fehlgeschlagene Mails/Übertragungen nach (nach `sync_max_attempts` Fehlversuchen Status `sync_failed` + Warn-Mail an den Vorstand), fragt ab, ob der Vorstand in easyVerein entschieden hat (keine Webhooks): bei **Annahme** Willkommens-Mail und lokale Kopie löschen, bei **Ablehnung/Löschung** (404) nur lokale Kopie löschen – easyVerein löscht bei Ablehnung Mitglied und Kontakt selbst. Löscht abgelaufene unbestätigte Anträge (zusätzlich bei jedem neuen Antrag; die Datenschutzerklärung nennt dafür „spätestens nach 3 Tagen“) und spätestens `retention_days` (90) Tage nach der Übertragung die lokale Kopie. `… cron.php status` listet alle Anträge (ohne personenbezogene Daten), `… cron.php retry OM-…` überträgt einen fehlgeschlagenen Antrag erneut. Die Fristen stehen auch in der Datenschutzerklärung – bei Änderung dort anpassen.
- **Mails lokal:** `docker compose up -d mailpit` – alle Mails landen unter http://localhost:8025, nichts wird echt zugestellt. In Produktion stehen die SMTP-Daten von `beitritt@badoldesloer-bogensport.de` in `membership.mail` der `config.local.php`, dazu `site_url`.
- **Mitgliedsarten** sind die easyVerein-Gruppen mit der Einstellung „Im Aufnahmeformular anzeigen“ – Auswahl und Beiträge werden also in easyVerein gepflegt (10 Minuten gecacht).
- **Konfiguration** im Abschnitt `membership` der `config.local.php` (Vorlage: `config.local.example.php`). Für Produktion eigene Schlüssel `data_key` und `signature_key` erzeugen: `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`. Geht `data_key` verloren, sind gespeicherte Anträge nicht mehr lesbar.
- `satzung_version` muss zu `satzungMeta.version` in `src/data/satzung.js` passen, sonst werden Anträge abgelehnt.
