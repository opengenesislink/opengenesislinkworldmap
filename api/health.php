<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/map.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    $db = mapDatabase();
    // Test only columns required by the map, without disclosing grid data.
    $db->query('SELECT uuid, regionName, locX, locY, sizeX, sizeY, serverURI FROM regions LIMIT 0');
    echo json_encode(['status' => 'ok', 'code' => 'ready', 'configured' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    error_log('WorldMap health: ' . $error->getMessage() . ($error->getPrevious() ? ' (' . $error->getPrevious()->getMessage() . ')' : ''));
    http_response_code(503);
    echo json_encode(['status' => 'error'] + mapPublicError($error), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
