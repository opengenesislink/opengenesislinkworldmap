<?php
/** SPDX-License-Identifier: MPL-2.0 */
/*
 * Copy this file to config/worldmap-config.php in this same project.
 * Keep real credentials OUT of Git. The entire /config URL must be denied
 * by Apache (.htaccess) or by an explicit Nginx location rule.
 */
if (!defined('OGL_WORLDMAP_INTERNAL')) {
    http_response_code(404);
    exit;
}
return [
    // The same database as the OpenSimulator / OpenGenesisLINK ROBUST GridService.
    'OGL_DB_DSN' => 'mysql:host=127.0.0.1;port=3306;dbname=opensim;charset=utf8mb4',
    'OGL_DB_USER' => 'opensim_reader',
    'OGL_DB_PASS' => 'CHANGE_ME',
    'OGL_COORD_UNIT' => 'meters',
    'OGL_CENTER_X' => '5000',
    'OGL_CENTER_Y' => '5000',
];
