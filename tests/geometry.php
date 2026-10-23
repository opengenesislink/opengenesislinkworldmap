<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/map.php';
function check(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAILED: $message\n"); exit(1); }
}
$single = mapGeometry(['uuid'=>'x', 'regionName'=>'Magdeburg', 'locX'=>1280000, 'locY'=>1280000, 'sizeX'=>256, 'sizeY'=>256]);
check($single['x'] === 5000 && $single['y'] === 5000, 'meters must convert to grid coordinates');
check($single['width'] === 1 && $single['height'] === 1, 'standard region is 1x1');
$large = mapGeometry(['locX'=>5000,'locY'=>5000,'sizeX'=>1024,'sizeY'=>512], 'regions');
check($large['x'] === 5000 && $large['width'] === 4 && $large['height'] === 2, 'variable region footprint');
$empty = mapGeometry(['locX'=>0,'locY'=>0,'sizeX'=>0,'sizeY'=>0]);
check($empty['width'] === 1 && $empty['height'] === 1, 'missing size fallback');
echo "OK: 4 geometry checks\n";
