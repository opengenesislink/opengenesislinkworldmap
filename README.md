# OpenGenesisLINK WorldMap

Eigenständiger, interaktiver Regionsatlas für **OpenGenesisLINK** und **OpenSimulator**. Die WorldMap entwickelt die ältere `openSIMULATOR-Map-Karte` aus der bereitgestellten ZIP technisch weiter.

## Funktionsumfang (Generation 1, Entwicklungsbasis)

- Responsives Dark-Mode-Frontend mit Canvas-Karte, Verschieben und Zoom
- Kartendarstellung: **Rot = registrierte/belegte Region**, **Grün = freies Kartenfeld**; noch nicht oder unvollständig geprüfte Flächen bleiben dunkel
- Klick auf jede Kartenposition öffnet ein kleines Info-Popup mit **exakten Grid-Koordinaten** (auch bei freien Feldern)
- Belegte Regionen zeigen Name, Größe, UUID, Server sowie – falls verfügbar – den registrierten Besitzer mit öffentlichem Avatar-Namen
- Standardregionen und VarRegions werden flächentreu dargestellt (einschließlich der zusätzlichen belegten Zellen großer Regionen)
- Regionssuche und Details (Name, Größe, UUID, Grid-Koordinaten, Server-URI)
- Schreibgeschützte JSON-API mit begrenzten Bounding-Box-Abfragen
- MariaDB/MySQL, PostgreSQL oder SQLite per PDO; Datenbanktabelle `regions`
- PHP 8.2+ ohne JavaScript-Framework und ohne Composer-Abhängigkeiten
- Dein transparentes OpenGenesisLINK-WorldMap-Logo, responsiv im Header
- Diagnose-Endpunkt `/api/health.php` mit sicheren Fehlercodes
- Koordinatenumrechnung Meter ↔ Grid-Regionen per Einstellung
- Automatischer PHP-Syntaxcheck und Geometrie-Tests per GitHub Actions

## Installation auf maps.stadt-nexverse.de

Die gesamte Konfiguration liegt jetzt **im WorldMap-Projekt unter `config/`**. Es wird **keine** Konfigurationsdatei im Elternverzeichnis benötigt.

Das richtige Verzeichnis ist:

```text
/home/users/stadtnexverse/www/maps.stadt-nexverse.de/
├── config/
│   ├── .htaccess                    # Apache: direkten HTTP-Zugriff vollständig sperren
│   ├── worldmap-config.example.php # Vorlage ohne echte Zugangsdaten
│   └── worldmap-config.php         # individuelle Datenbank-Zugangsdaten, Git-ignoriert
├── api/
├── assets/
├── lib/
└── index.php
```

### Konfigurationsdatei innerhalb von /config anlegen

```bash
cd /home/users/stadtnexverse/www/maps.stadt-nexverse.de
git pull --ff-only origin main

# Eine bereits bestehende lokale Datei wird NICHT überschrieben.
if [ ! -f config/worldmap-config.php ]; then
    cp config/worldmap-config.example.php config/worldmap-config.php
fi

nano config/worldmap-config.php
```

Danach die **GridService/ROBUST-Datenbank** eintragen (nicht die separate Simulator-Datenbank, sofern beide getrennt sind):

```php
<?php
if (!defined('OGL_WORLDMAP_INTERNAL')) {
    http_response_code(404);
    exit;
}
return [
    'OGL_DB_DSN' => 'mysql:host=ROBUST_DB_HOST;port=3306;dbname=opensim;charset=utf8mb4',
    'OGL_DB_USER' => 'opensim_reader',
    'OGL_DB_PASS' => 'DEIN_PASSWORT',
    'OGL_COORD_UNIT' => 'meters',
    'OGL_CENTER_X' => '5000',
    'OGL_CENTER_Y' => '5000',
];
```

Die Zugangsdaten im Beispiel sind Platzhalter. PHP muss `config/worldmap-config.php` lesen können; die Datei soll nicht weltlesbar sein. Beispiel bei passender Eigentümer-/Gruppenzuordnung: `chmod 640 config/worldmap-config.php`. Prüfe PHP-FPM-Webserver-Benutzer und Verzeichnisrechte, falls die Datei nicht gelesen werden kann.

Die Datei ist durch `.gitignore` vom Repository ausgeschlossen. Deshalb bleibt sie bei zukünftigen `git pull`-Updates erhalten und **darf nicht eingecheckt** werden.

### Web-Zugriff auf /config sperren

**Apache 2.4:** `config/.htaccess` wird im Repository mitgeliefert und enthält `Require all denied`. Dazu müssen `.htaccess`-Regeln auf dem Hosting-System aktiviert sein (`AllowOverride`). Für andere Webserver gilt das nicht automatisch.

**Nginx:** Die folgende Regel muss **innerhalb des aktiven `server { ... }`-Blocks** von `maps.stadt-nexverse.de` vor Regex-PHP-Locations wirksam sein; danach Konfiguration testen und Nginx neu laden:

```nginx
location ^~ /config/ {
    deny all;
}
```

Ist die Serverkonfiguration nicht entsprechend abgesichert, darf die Anwendung mit echten Zugangsdaten nicht öffentlich betrieben werden. Zusätzlich sollte bei Nginx der PHP-Handler korrekt eingerichtet sein; der direkt aufgerufene Konfigurations-PHP-Dateiname muss trotz Schutzregel über HTTP grundsätzlich **403 oder 404** liefern.

HTTP-Schutz kontrollieren:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' \
  https://maps.stadt-nexverse.de/config/worldmap-config.php
```

Erwartet wird `403` oder `404`, **nicht** `200`.

### Datenbankverbindung prüfen

```bash
curl -i https://maps.stadt-nexverse.de/api/health.php
curl -i 'https://maps.stadt-nexverse.de/api/regions.php?west=4990&east=5010&south=4990&north=5010'
php -m | grep -Ei 'PDO|pdo_mysql|pdo_pgsql|pdo_sqlite'
```

Die Health-API antwortet bei erfolgreicher Verbindung mit HTTP 200 und `{"status":"ok","code":"ready","configured":true}`. Andernfalls folgen HTTP 503 und die Codes `configuration_required`, `pdo_driver_missing`, `connection_failed` oder `schema_error`. Der PHP-Error-Log enthält technische Details, die öffentliche API keine Passwort- oder SQL-Details.

Wenn die WorldMap auf einem anderen Server als ROBUST/MySQL läuft, sollte die Datenbank über ein internes Netz oder VPN verbunden werden, nicht öffentlich mit beliebig offenem MySQL-Port.

### Optional: Umgebungsvariablen statt lokaler Datei

PHP 8.2+ mit passendem PDO-Treiber (`pdo_mysql`, `pdo_pgsql` oder `pdo_sqlite`) ist Voraussetzung. `OGL_DB_DSN`, `OGL_DB_USER`, `OGL_DB_PASS`, `OGL_COORD_UNIT`, `OGL_CENTER_X` und `OGL_CENTER_Y` können alternativ als PHP-Prozessumgebungsvariablen gesetzt werden; gesetzte Umgebungsvariablen haben Vorrang vor der Datei. `OGL_CONFIG_PATH` wird bewusst nicht mehr verwendet: der dateibasierte Pfad bleibt immer `<Projektverzeichnis>/config/worldmap-config.php`.

MySQL/MariaDB: Beispiel für einen beschränkten Lesebenutzer:

```sql
CREATE USER 'opensim_reader'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT SELECT ON opensim.regions TO 'opensim_reader'@'localhost';
-- Optional für Avatar-Besitzernamen, nicht für die Karte selbst notwendig:
GRANT SELECT (PrincipalID, FirstName, LastName) ON opensim.UserAccounts TO 'opensim_reader'@'localhost';
```

Für OpenSimulator werden `locX` und `locY` üblicherweise in **Metern** gespeichert: z. B. `1280000` entspricht X `5000` auf der Karte. `sizeX` und `sizeY` enthalten die Regionsabmessungen in Metern. Die aktuelle Map rechnet die Maße in 256-m-Zellen um. Abweichende Schemas müssen vor Produktivbetrieb gegen die eigene Grid-Datenbank geprüft werden.

## Farben, Klick-Infofenster und Besitzer

- **Rot:** Belegte/registrierte Region laut der GridService-Tabelle `regions`. Eine VarRegion belegt entsprechend ihrer Größe mehrere 256-m-Kartenzellen.
- **Grün:** Im vollständig abgefragten Ausschnitt nicht in `regions` registrierte Kartenzelle. Das ist **keine Buchungs- oder Mietgarantie**.
- **Dunkel:** Keine verlässlichen Daten (noch ladend, fehlerhafte Verbindung oder gekappte API-Ausgabe mit mehr als 2000 Regionen).
- **Klick:** Das Infofenster zeigt auch auf freien Feldern die präzisen Zellenkoordinaten. Bei belegten Regionen enthält es weitere Grid-Daten. Besitzer werden zuerst aus `regions.owner_uuid` (bzw. kompatiblen `regionOwner`-Spalten) gelesen; über `UserAccounts.PrincipalID` können `FirstName` und `LastName` aufgelöst werden.
- Wenn die Tabelle `UserAccounts` nicht vorhanden ist oder kein SELECT-Recht besteht, funktioniert die Karte weiterhin. Es erscheint gegebenenfalls nur die Besitzer-UUID bzw. „Nicht hinterlegt“. **Estate-/Parzellenbesitzer sind andere Datensätze auf den Simulatoren**; die WorldMap zeigt derzeit ausschließlich den registrierten Grid-Regionsbesitzer.

Es werden keine Passwörter, E-Mail-Adressen oder Benutzer-Authentifizierungsdaten abgefragt.

## API

- `GET /api/regions.php?west=4980&east=5020&south=4980&north=5020`
- `GET /api/regions.php?q=Magdeburg`

Die Bounding-Box ist auf höchstens 256 × 256 Zellen begrenzt. Bei mehr als 2000 Regionen wird das Ergebnis als `truncated` markiert. Suchanfragen liefern maximal 30 Treffer. Bei fehlender DB-Konfiguration liefert die API HTTP 503 statt interne Verbindungsdetails offenzulegen.

## Tests

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/geometry.php
php tests/configuration.php
php tests/database.php
php tests/owners.php
node --check assets/app.js
php -S 127.0.0.1:8080
```

Ohne Datenbank bleibt die Karte als Oberfläche erreichbar und zeigt eine Verbindungswarnung. Für öffentliches Hosting werden TLS, PHP-FPM, sichere Webserver-Konfiguration und Caching empfohlen.

## Projektstruktur

- `index.php` – Weboberfläche und öffentliche Konfiguration
- `assets/` – Canvas-Karte, Suchfeld, Styling
- `api/regions.php` – schreibgeschützte Regions-API
- `lib/map.php` – DB-Zugriff und Koordinatenlogik
- `config/` – lokale (Git-ignorierte) Datenbankkonfiguration und Web-Zugriffsschutz
- `tests/` – Testskripte
- `legacy/` – Hinweise zum ursprünglichen Projekt

## Ausblick

Anbindung der OpenGenesisLINK World API, originalgetreue MapTiles, Hypergrid-Teleportlinks, Multi-Grid-Suche, Cache-Unterstützung, Marker und Integration in den OpenGenesisLINK Atlas.

## Projekt- und Lizenzhinweise

Die ursprüngliche `openSIMULATOR-Map-Karte` diente als funktionaler Ausgangspunkt. Sie enthält BSD-Lizenzhinweise von **Metropolis Metaversum / MetroTools**; diese Hinweise bleiben im historischen Originalarchiv erhalten. Die neue Implementierung ist eine Neuentwicklung.

Neuer Quellcode: **MPL-2.0** – https://www.mozilla.org/en-US/MPL/2.0/.
