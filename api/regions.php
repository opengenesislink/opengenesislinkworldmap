<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/map.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    $db = mapDatabase();
    $unit = mapCoordinateUnit();
    if (isset($_GET['q'])) {
        $term = trim((string) $_GET['q']);
        if (strlen($term) < 2 || strlen($term) > 160) {
            http_response_code(400);
            echo json_encode(['error' => 'Suchbegriff muss 2 bis 80 Zeichen enthalten']);
            exit;
        }
        $result = ['regions' => mapSearch($db, $term, $unit)];
    } else {
        $west = mapInteger('west', 4980);
        $east = mapInteger('east', 5020);
        $south = mapInteger('south', 4980);
        $north = mapInteger('north', 5020);
        if ($east <= $west || $north <= $south || $east - $west > 256 || $north - $south > 256) {
            http_response_code(400);
            echo json_encode(['error' => 'Ungueltiger Kartenausschnitt (maximal 256 × 256 Regionen)']);
            exit;
        }
        $result = mapFetchRegions($db, [$west, $east, $south, $north], $unit);
    }
    echo json_encode($result + ['coordinateUnit' => 'regions'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('WorldMap API: ' . $error->getMessage() . ($error->getPrevious() ? ' (' . $error->getPrevious()->getMessage() . ')' : ''));
    http_response_code(503);
    echo json_encode(mapPublicError($error), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
