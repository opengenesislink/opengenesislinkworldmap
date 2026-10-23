<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);

final class MapConfigurationException extends RuntimeException {}

function mapConfigPath(): string
{
    return dirname(__DIR__) . '/config/worldmap-config.php';
}

function mapLocalSettings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    // Keep all WorldMap configuration inside this project's /config directory.
    // The webserver MUST deny HTTP access to /config (see config/.htaccess / Nginx docs).
    $path = mapConfigPath();
    if (!is_file($path)) {
        return $settings = [];
    }
    // Template files may explicitly reject direct browser requests.
    if (!defined('OGL_WORLDMAP_INTERNAL')) {
        define('OGL_WORLDMAP_INTERNAL', true);
    }
    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new MapConfigurationException('invalid_configuration');
    }
    return $settings = $loaded;
}

function mapSetting(string $key, string $default = ''): string
{
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    $fileSettings = mapLocalSettings();
    return isset($fileSettings[$key]) ? (string) $fileSettings[$key] : $default;
}

function mapPublicError(Throwable $error): array
{
    $code = 'data_source_error';
    if ($error instanceof MapConfigurationException) {
        $code = $error->getMessage();
    } elseif ($error instanceof PDOException && preg_match('/^(42|HY000)/', (string) $error->getCode())) {
        $code = 'schema_error';
    }
    $messages = [
        'configuration_required' => 'Datenbank nicht konfiguriert. Bitte config/worldmap-config.php in der WorldMap einrichten.',
        'invalid_configuration' => 'Die WorldMap-Konfigurationsdatei ist ungültig.',
        'pdo_driver_missing' => 'Der erforderliche PHP-PDO-Datenbanktreiber ist nicht installiert.',
        'connection_failed' => 'Datenbankverbindung fehlgeschlagen. Host, Port, Berechtigungen und Zugangsdaten prüfen.',
        'schema_error' => 'Datenbank erreichbar, aber die Regions-Tabelle oder erforderliche Spalten sind nicht verfügbar.',
        'data_source_error' => 'Die Regionsdaten sind derzeit nicht verfügbar.',
    ];
    if (!array_key_exists($code, $messages)) {
        $code = 'data_source_error';
    }
    return ['code' => $code, 'error' => $messages[$code]];
}

function mapCoordinateUnit(): string
{
    $unit = strtolower(mapSetting('OGL_COORD_UNIT', 'meters'));
    return in_array($unit, ['meters', 'regions'], true) ? $unit : 'meters';
}
function mapGeometry(array $row, string $unit = 'meters'): array
{
    $sizeX = max(256, (int) ($row['sizeX'] ?? 256));
    $sizeY = max(256, (int) ($row['sizeY'] ?? 256));
    $factor = $unit === 'regions' ? 1 : 256;
    return [
        'id' => (string) ($row['uuid'] ?? ''),
        'name' => (string) ($row['regionName'] ?? ''),
        'x' => (int) floor((int) ($row['locX'] ?? 0) / $factor),
        'y' => (int) floor((int) ($row['locY'] ?? 0) / $factor),
        'width' => (int) ceil($sizeX / 256),
        'height' => (int) ceil($sizeY / 256),
        'sizeX' => $sizeX,
        'sizeY' => $sizeY,
        'serverURI' => (string) ($row['serverURI'] ?? ''),
        'ownerId' => mapOwnerId((string) ($row['map_owner_uuid'] ?? '')),
        'ownerName' => null,
    ];
}
/**
 * OpenSimulator stores the grid-level region owner in regions.owner_uuid.
 * Some custom forks call the same field regionOwner. Older schemas can lack both.
 */
function mapOwnerColumnSql(PDO $db): string
{
    foreach (['owner_uuid', 'regionOwner'] as $column) {
        try {
            $db->query('SELECT ' . $column . ' FROM regions WHERE 1=0');
            return ', ' . $column . ' AS map_owner_uuid';
        } catch (PDOException $error) {
            // Optional metadata must not break the map on older GridService schemas.
        }
    }
    return '';
}

function mapOwnerId(string $value): ?string
{
    $value = trim($value);
    if ($value === '' || strtolower($value) === '00000000-0000-0000-0000-000000000000') {
        return null;
    }
    // Never return arbitrary DB values as an account identifier.
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD', $value)
        ? $value : null;
}

/**
 * Resolve display names using the public name columns of UserAccounts.
 * A read-only grid DB login can access regions only; owner UUIDs then remain visible
 * but the name is explicitly unavailable. No e-mail or account secrets are read.
 */
function mapResolveOwners(PDO $db, array $regions): array
{
    $byOwner = [];
    foreach ($regions as $i => $region) {
        if ($region['ownerId'] !== null) {
            $byOwner[$region['ownerId']][] = $i;
        }
    }
    if ($byOwner === []) {
        return $regions;
    }
    foreach (array_chunk(array_keys($byOwner), 200) as $ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $statement = $db->prepare(
                'SELECT PrincipalID, FirstName, LastName FROM UserAccounts WHERE PrincipalID IN (' . $placeholders . ')'
            );
            $statement->execute($ids);
            foreach ($statement->fetchAll() as $account) {
                $id = (string) $account['PrincipalID'];
                $name = trim((string) ($account['FirstName'] ?? '') . ' ' . (string) ($account['LastName'] ?? ''));
                if ($name === '') {
                    continue;
                }
                foreach ($byOwner[$id] ?? [] as $index) {
                    $regions[$index]['ownerName'] = $name;
                }
            }
        } catch (PDOException $error) {
            // Optional UserAccounts table or SELECT grant unavailable; show UUID only.
            return $regions;
        }
    }
    return $regions;
}

function mapInteger(string $key, int $default, int $min = -1000000, int $max = 1000000): int
{
    $raw = $_GET[$key] ?? null;
    if (!is_string($raw) || !preg_match('/^-?\d{1,9}$/D', $raw)) {
        return $default;
    }
    return max($min, min($max, (int) $raw));
}
function mapDatabase(): PDO
{
    $dsn = mapSetting('OGL_DB_DSN');
    if ($dsn === '') {
        throw new MapConfigurationException('configuration_required');
    }
    $driver = strtolower((string) strstr($dsn, ':', true));
    if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
        throw new MapConfigurationException('pdo_driver_missing');
    }
    try {
        return new PDO($dsn, mapSetting('OGL_DB_USER'), mapSetting('OGL_DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (PDOException $error) {
        throw new MapConfigurationException('connection_failed', 0, $error);
    }
}

function mapFetchRegions(PDO $db, array $bounds, string $unit): array
{
    [$west, $east, $south, $north] = $bounds;
    $factor = $unit === 'regions' ? 1 : 256;
    $widthExpression = $unit === 'regions'
        ? '(CASE WHEN sizeX > 0 THEN sizeX / 256.0 ELSE 1 END)'
        : '(CASE WHEN sizeX > 0 THEN sizeX ELSE 256 END)';
    $heightExpression = $unit === 'regions'
        ? '(CASE WHEN sizeY > 0 THEN sizeY / 256.0 ELSE 1 END)'
        : '(CASE WHEN sizeY > 0 THEN sizeY ELSE 256 END)';
    $ownerSql = mapOwnerColumnSql($db);
    $sql = "SELECT uuid, regionName, locX, locY, sizeX, sizeY, serverURI $ownerSql
            FROM regions
            WHERE locX < :east AND locY < :north
              AND locX + $widthExpression > :west
              AND locY + $heightExpression > :south
            ORDER BY locY DESC, locX ASC LIMIT 2001";
    $query = $db->prepare($sql);
    foreach (['west' => $west, 'east' => $east, 'south' => $south, 'north' => $north] as $key => $value) {
        $query->bindValue(':' . $key, $value * $factor, PDO::PARAM_INT);
    }
    $query->execute();
    $rows = $query->fetchAll();
    $truncated = count($rows) > 2000;
    if ($truncated) {
        $rows = array_slice($rows, 0, 2000);
    }
    $regions = array_map(static fn (array $row): array => mapGeometry($row, $unit), $rows);
    return ['regions' => mapResolveOwners($db, $regions), 'truncated' => $truncated];
}
function mapSearch(PDO $db, string $term, string $unit): array
{
    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], function_exists('mb_strtolower') ? mb_strtolower($term) : strtolower($term));
    $ownerSql = mapOwnerColumnSql($db);
    $statement = $db->prepare("SELECT uuid, regionName, locX, locY, sizeX, sizeY, serverURI $ownerSql
        FROM regions WHERE LOWER(regionName) LIKE :name ESCAPE '!'
        ORDER BY regionName LIMIT 30");
    $statement->execute(['name' => '%' . $escaped . '%']);
    $regions = array_map(static fn (array $row): array => mapGeometry($row, $unit), $statement->fetchAll());
    return mapResolveOwners($db, $regions);
}
