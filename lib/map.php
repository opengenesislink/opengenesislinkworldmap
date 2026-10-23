<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);

function mapSetting(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
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
    ];
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
        throw new RuntimeException('Database connection is not configured');
    }
    return new PDO($dsn, mapSetting('OGL_DB_USER'), mapSetting('OGL_DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
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
    $sql = "SELECT uuid, regionName, locX, locY, sizeX, sizeY, serverURI
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
    return ['regions' => array_map(static fn (array $row): array => mapGeometry($row, $unit), $rows), 'truncated' => $truncated];
}
function mapSearch(PDO $db, string $term, string $unit): array
{
    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], function_exists('mb_strtolower') ? mb_strtolower($term) : strtolower($term));
    $statement = $db->prepare("SELECT uuid, regionName, locX, locY, sizeX, sizeY, serverURI
        FROM regions WHERE LOWER(regionName) LIKE :name ESCAPE '!'
        ORDER BY regionName LIMIT 30");
    $statement->execute(['name' => '%' . $escaped . '%']);
    return array_map(static fn (array $row): array => mapGeometry($row, $unit), $statement->fetchAll());
}
