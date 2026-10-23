<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/map.php';
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "pdo_sqlite extension required" . PHP_EOL);
    exit(1);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE regions (uuid TEXT, regionName TEXT, locX INTEGER, locY INTEGER, sizeX INTEGER, sizeY INTEGER, serverURI TEXT)");
$db->exec("INSERT INTO regions VALUES
('region-a','Magdeburg',1280000,1280000,256,256,'http://example.invalid:9000/'),
('region-b','Gummybear Island',1281280,1281280,512,512,'http://example.invalid:9001/')");
$data = mapFetchRegions($db, [4998, 5010, 4998, 5010], 'meters');
if (count($data['regions']) !== 2 || $data['truncated']) {
    throw new RuntimeException('Expected two regions in bounds');
}
$large = $data['regions'][0];
if ($large['name'] !== 'Gummybear Island' || $large['width'] !== 2 || $large['height'] !== 2) {
    throw new RuntimeException('VarRegion size and name mismatch');
}
$search = mapSearch($db, 'magdeburg', 'meters');
if (count($search) !== 1 || $search[0]['name'] !== 'Magdeburg') {
    throw new RuntimeException('Regionssuche konnte nicht ausgeführt werden');
}
if (mapSearch($db, '%', 'meters') !== []) {
    throw new RuntimeException('Search wildcard was not escaped');
}
echo "OK: SQLite integration and VarRegion search" . PHP_EOL;
