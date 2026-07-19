# Mitgliederbereich

Der Mitgliederbereich nutzt PHP, MySQL 5.7 und ein JWT in einem `HttpOnly`-Cookie.

## Benötigte Strato-Daten

- Datenbank-Host, meist `rdbms.strato.de`
- Datenbank-Port, meist `3306`
- Datenbankname
- Datenbankbenutzer
- Datenbankpasswort

Diese Werte gehören nicht ins Git-Repository.

## Lokale Konfiguration

`private/member-area/config.local.example.php` nach `private/member-area/config.local.php` kopieren und Werte setzen.

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
php private/member-area/create-user.php mail@example.de "SICHERES_PASSWORT" superadmin "Name"
```

Falls PHP-CLI bei Strato nicht verfügbar ist, kann der Passwort-Hash lokal erzeugt und per SQL eingefügt werden.

Alternativ kann der erste Superadmin im Browser angelegt werden:

1. Einen zufälligen Setup-Token erzeugen.
2. Den SHA-256-Hash davon in `config.local.php` unter `setup.token_hash` eintragen.
3. `/mitglieder/setup.php?token=DEIN_TOKEN` im Browser öffnen.

Sobald ein Benutzer existiert, deaktiviert sich dieses Setup automatisch.

Der Login akzeptiert E-Mail-Adresse oder exakt hinterlegten Namen. Namen sollten eindeutig sein; wenn ein Name mehrfach vorkommt, wird der Login per Name aus Sicherheitsgründen abgelehnt.

## DB-Verbindung im Browser prüfen

Für einen temporären DB-Check:

1. Einen zufälligen Diagnose-Token erzeugen.
2. Den SHA-256-Hash davon in `config.local.php` unter `diagnostics.token_hash` eintragen.
3. `/mitglieder/check-db.php?token=DEIN_TOKEN` im Browser öffnen.

Ohne passenden Token antwortet die Datei mit `404`.
