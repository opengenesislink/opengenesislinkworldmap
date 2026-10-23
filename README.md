# OpenGenesisLINK WorldMap

Eigenständiger, interaktiver Regionsatlas für **OpenGenesisLINK** und **OpenSimulator**. Die WorldMap entwickelt die ältere `openSIMULATOR-Map-Karte` aus der bereitgestellten ZIP technisch weiter.

## Funktionsumfang (Generation 1, Entwicklungsbasis)

- Responsives Dark-Mode-Frontend mit Canvas-Karte, Verschieben und Zoom
- Freie und belegte Regionskoordinaten, Standardregionen und VarRegions
- Regionssuche und Details (Name, Größe, UUID, Grid-Koordinaten, Server-URI)
- Schreibgeschützte JSON-API mit begrenzten Bounding-Box-Abfragen
- MariaDB/MySQL, PostgreSQL oder SQLite per PDO; Datenbanktabelle `regions`
- PHP 8.2+ ohne JavaScript-Framework und ohne Composer-Abhängigkeiten
- Dein transparentes OpenGenesisLINK-WorldMap-Logo, responsiv im Header
- Diagnose-Endpunkt `/api/health.php` mit sicheren Fehlercodes
- Koordinatenumrechnung Meter ↔ Grid-Regionen per Einstellung
- Automatischer PHP-Syntaxcheck und Geometrie-Tests per GitHub Actions

## Installation

1. PHP 8.2+ sowie den passenden PDO-Treiber (`pdo_mysql`, `pdo_pgsql` oder `pdo_sqlite`) installieren.
2. Den Webserver-DocumentRoot auf das Projektverzeichnis richten; `index.php` verwenden.
3. Die **Umgebungsvariablen** im Webserver/PHP-FPM/Docker-Prozess setzen:

| Variable | Beispiel | Bedeutung |
| --- | --- | --- |
| `OGL_DB_DSN` | `mysql:host=127.0.0.1;port=3306;dbname=opensim;charset=utf8mb4` | PDO-Datenbankverbindung |
| `OGL_DB_USER` | `opensim_reader` | Datenbankbenutzer mit SELECT-Rechten |
| `OGL_DB_PASS` | *vertraulich* | Datenbankpasswort |
| `OGL_CENTER_X` | `5000` | Kartenstart X in Regionskoordinaten |
| `OGL_CENTER_Y` | `5000` | Kartenstart Y in Regionskoordinaten |
| `OGL_COORD_UNIT` | `meters` | `meters` für OpenSimulator-Standard, `regions` für ältere Grids |

### Installation auf maps.stadt-nexverse.de (ohne PHP-FPM-Umgebungsvariablen)

Der Fehler **„Die Datenbank ist nicht eingerichtet oder derzeit nicht erreichbar“** stammt im ersten Release vom fehlenden `OGL_DB_DSN` oder einer fehlgeschlagenen PDO-Abfrage.

**Einfachster Weg auf klassischem Webhosting:** Lege die Konfiguration **außerhalb des öffentlich erreichbaren Webverzeichnisses** an. Angenommen das geklonte Repository selbst ist der DocumentRoot:

```bash
cd /pfad/zur/opengenesislinkworldmap
cp config/worldmap-config.example.php ../worldmap-config.php
chmod 600 ../worldmap-config.php
nano ../worldmap-config.php
```

Dort die echten, nur leseberechtigten Datenbankwerte von OpenGenesisLINK/ROBUST eintragen:

```php
<?php
return [
    'OGL_DB_DSN' => 'mysql:host=DEIN_PRIVATER_DB_HOST;port=3306;dbname=opensim;charset=utf8mb4',
    'OGL_DB_USER' => 'opensim_reader',
    'OGL_DB_PASS' => 'DEIN_PASSWORT',
    'OGL_COORD_UNIT' => 'meters',
    'OGL_CENTER_X' => '5000',
    'OGL_CENTER_Y' => '5000',
];
```

Wichtig: Das Beispiel zeigt **Platzhalter**, keine echten Zugangsdaten. Der Dateipfad ist genau `<DocumentRoot>/../worldmap-config.php`; alternativ kann `OGL_CONFIG_PATH` auf eine andere absolute, geschützte Datei zeigen. `OGL_DB_DSN` aus der Prozessumgebung hat Vorrang. Die Tabelle `regions` muss aus der OpenSimulator/OpenGenesisLINK **GridService-Datenbank**, nicht aus der Simulator-Prim-Datenbank, stammen.

**Sicherheitsregel:** Wenn die WorldMap auf einem anderen Host läuft als ROBUST/MySQL, nutze eine interne IP/VPN-Verbindung mit gezielter Firewallfreigabe, statt MySQL pauschal öffentlich erreichbar zu machen.

Zur Diagnose nach der Konfiguration:

```bash
curl -i https://maps.stadt-nexverse.de/api/health.php
curl -i 'https://maps.stadt-nexverse.de/api/regions.php?west=4990&east=5010&south=4990&north=5010'
php -m | grep -Ei 'PDO|pdo_mysql|pdo_pgsql|pdo_sqlite'
```

`/api/health.php` antwortet bei erfolgreicher Verbindung **200 + `{"status":"ok","code":"ready","configured":true}`**. Andernfalls kommt 503 mit einem der sicheren Codes `configuration_required`, `pdo_driver_missing`, `connection_failed` oder `schema_error`. Der PHP-Error-Log enthält die konkrete technische Fehlermeldung; das öffentliche API gibt keine Passwörter oder SQL-Details preis.

**Keine Datenbank-Zugangsdaten committen.** Die Datei `.env.example` dokumentiert Werte, wird von der Anwendung aber **nicht automatisch eingelesen**. Die Variablen müssen über Webserver oder Prozessumgebung gesetzt werden.

MySQL/MariaDB: Beispiel für einen beschränkten Lesebenutzer:

```sql
CREATE USER 'opensim_reader'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT SELECT ON opensim.regions TO 'opensim_reader'@'localhost';
```

Für OpenSimulator werden `locX` und `locY` üblicherweise in **Metern** gespeichert: z. B. `1280000` entspricht X `5000` auf der Karte. `sizeX` und `sizeY` enthalten die Regionsabmessungen in Metern. Die aktuelle Map rechnet die Maße in 256-m-Zellen um. Abweichende Schemas müssen vor Produktivbetrieb gegen die eigene Grid-Datenbank geprüft werden.

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
php -S 127.0.0.1:8080
```

Ohne Datenbank bleibt die Karte als Oberfläche erreichbar und zeigt eine Verbindungswarnung. Für öffentliches Hosting werden TLS, PHP-FPM, sichere Webserver-Konfiguration und Caching empfohlen.

## Projektstruktur

- `index.php` – Weboberfläche und öffentliche Konfiguration
- `assets/` – Canvas-Karte, Suchfeld, Styling
- `api/regions.php` – schreibgeschützte Regions-API
- `lib/map.php` – DB-Zugriff und Koordinatenlogik
- `tests/` – Testskripte
- `legacy/` – Hinweise zum ursprünglichen Projekt

## Ausblick

Anbindung der OpenGenesisLINK World API, originalgetreue MapTiles, Hypergrid-Teleportlinks, Multi-Grid-Suche, Cache-Unterstützung, Marker und Integration in den OpenGenesisLINK Atlas.

## Projekt- und Lizenzhinweise

Die ursprüngliche `openSIMULATOR-Map-Karte` diente als funktionaler Ausgangspunkt. Sie enthält BSD-Lizenzhinweise von **Metropolis Metaversum / MetroTools**; diese Hinweise bleiben im historischen Originalarchiv erhalten. Die neue Implementierung ist eine Neuentwicklung.

Neuer Quellcode: **MPL-2.0** – https://www.mozilla.org/en-US/MPL/2.0/.
