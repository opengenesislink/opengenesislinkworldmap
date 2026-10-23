<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/map.php';

function assertOwner(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE regions (
    uuid TEXT, regionName TEXT, locX INTEGER, locY INTEGER,
    sizeX INTEGER, sizeY INTEGER, serverURI TEXT, owner_uuid TEXT
)");
$db->exec("CREATE TABLE UserAccounts (PrincipalID TEXT, FirstName TEXT, LastName TEXT)");
$db->exec("INSERT INTO UserAccounts VALUES ('0b8c88ab-d1b0-482d-b539-113c36e2ad47','Sleimer','Akina')");
$db->exec("INSERT INTO regions VALUES
    ('one','Magdeburg',1280000,1280000,256,256,'', '0b8c88ab-d1b0-482d-b539-113c36e2ad47'),
    ('two','Unused',1280256,1280256,256,256,'', '00000000-0000-0000-0000-000000000000')");
$regions = mapFetchRegions($db, [5000,5003,5000,5003], 'meters')['regions'];
assertOwner(count($regions) === 2, 'Two occupied regions');
$byName = [];
foreach ($regions as $region) {
    $byName[$region['name']] = $region;
}
assertOwner($byName['Magdeburg']['ownerName'] === 'Sleimer Akina', 'Resolve public avatar owner display name');
assertOwner($byName['Magdeburg']['ownerId'] === '0b8c88ab-d1b0-482d-b539-113c36e2ad47', 'Expose owner ID');
assertOwner($byName['Unused']['ownerId'] === null && $byName['Unused']['ownerName'] === null, 'Handle zero owner UUID');
$searched = mapSearch($db, 'Magdeburg', 'meters');
assertOwner($searched[0]['ownerName'] === 'Sleimer Akina', 'Owner details included in search results');

$dbNoAccounts = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$dbNoAccounts->exec("CREATE TABLE regions (
    uuid TEXT, regionName TEXT, locX INTEGER, locY INTEGER,
    sizeX INTEGER, sizeY INTEGER, serverURI TEXT, owner_uuid TEXT
)");
$dbNoAccounts->exec("INSERT INTO regions VALUES
    ('one','Region ohne UserAccounts',1280000,1280000,256,256,'', '0b8c88ab-d1b0-482d-b539-113c36e2ad47')");
$optional = mapFetchRegions($dbNoAccounts, [4999,5002,4999,5002], 'meters')['regions'][0];
assertOwner($optional['ownerId'] !== null && $optional['ownerName'] === null, 'Missing UserAccounts does not break map');

$dbLegacy = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$dbLegacy->exec("CREATE TABLE regions (
    uuid TEXT, regionName TEXT, locX INTEGER, locY INTEGER,
    sizeX INTEGER, sizeY INTEGER, serverURI TEXT
)");
$dbLegacy->exec("INSERT INTO regions VALUES ('one','Alte Region',1280000,1280000,256,256,'')");
$legacy = mapFetchRegions($dbLegacy, [4999,5002,4999,5002], 'meters')['regions'][0];
assertOwner($legacy['ownerId'] === null && $legacy['ownerName'] === null, 'Missing region owner column does not break map');
echo "OK: owner resolution with full, partial and legacy GridService schemas" . PHP_EOL;
