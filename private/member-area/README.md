# Mitgliederbereich

Der Mitgliederbereich nutzt PHP, MySQL 5.7 und ein JWT in einem `HttpOnly`-Cookie.

## Benötigte Strato-Daten

- Datenbank-Host
- Datenbank-Port, meist `3306`
- Datenbankname
- Datenbankbenutzer
- Datenbankpasswort

Diese Werte gehören nicht ins Git-Repository.

## Konfiguration

Die Anwendung wählt die Konfiguration automatisch:

- Lokal unter `localhost` oder `127.0.0.1`: `private/member-area/config.local.example.php`
- Auf Strato/Produktion: `private/member-area/config.local.php`

`config.local.example.php` enthält die lokale Docker-Datenbank.
`config.local.php` enthält die Strato-Datenbank und muss auf dem Server mit echtem DB-Passwort vorhanden sein.

Optional kann mit der Umgebungsvariable `MEMBER_AREA_CONFIG_FILE` eine konkrete Konfigurationsdatei erzwungen werden.

Auf dem Strato-Server muss `private/member-area/config.local.php` vorhanden sein. Die Datei ist absichtlich nicht im Git-Repository enthalten; vor dem Upload das Passwort unter `db.password` eintragen.

Für `jwt.secret` einen langen zufälligen Wert verwenden, zum Beispiel mindestens 32 Zeichen.

## Datenbank

Falls die Tabelle noch fehlt:

```sql
SOURCE private/member-area/schema.sql;
```

Alternativ den Inhalt von `schema.sql` in phpMyAdmin ausführen.

## Ersten Benutzer anlegen

Wenn lokal oder auf dem Server PHP-CLI verfügbar ist:

```bash
php private/member-area/create-user.php benutzername mail@example.de "SICHERES_PASSWORT" superadmin "Name"
```

Falls PHP-CLI bei Strato nicht verfügbar ist, kann der Passwort-Hash lokal erzeugt und per SQL eingefügt werden.

Alternativ kann der erste Superadmin im Browser angelegt werden:

1. Einen zufälligen Setup-Token erzeugen.
2. Den SHA-256-Hash davon in `config.local.php` unter `setup.token_hash` eintragen.
3. `/mitglieder/setup.php?token=DEIN_TOKEN` im Browser öffnen.

Sobald ein Benutzer existiert, deaktiviert sich dieses Setup automatisch. Danach `setup.token_hash` in `config.local.php` wieder leeren.

Der Login akzeptiert Benutzername oder E-Mail-Adresse. Nach 5 Fehlversuchen für denselben Login oder 20 Fehlversuchen von derselben IP wird der Login für 15 Minuten gesperrt (Tabelle `login_attempts`, wird bei Bedarf automatisch angelegt; gespeichert werden nur gehashte Werte).

## DB-Verbindung im Browser prüfen

Für einen temporären DB-Check:

1. Einen zufälligen Diagnose-Token erzeugen.
2. Den SHA-256-Hash davon in `config.local.php` unter `diagnostics.token_hash` eintragen.
3. `/mitglieder/check-db.php?token=DEIN_TOKEN` im Browser öffnen.

Ohne passenden Token antwortet die Datei mit `404`. Nach dem Check `diagnostics.token_hash` wieder leeren.

## Deployment

- `private/` muss außerhalb des Webroots liegen. Falls das nicht geht, schützt nur `private/.htaccess`; dann prüfen, dass `/private/member-area/README.md` im Browser `403` liefert.
- `uploads/news/` auf dem Server enthält die echten News-Bilder. Beim Hochladen von `dist/` diesen Ordner nie überschreiben oder per Synchronisation löschen lassen.
- Nach dem Deployment prüfen, dass `http://` auf `https://` weiterleitet (Regel in `public/.htaccess`).
- Migrationen aus `migrations/` in Reihenfolge in phpMyAdmin ausführen, falls die Tabellen noch fehlen.
