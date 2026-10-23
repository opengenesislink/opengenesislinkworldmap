<?php
/** SPDX-License-Identifier: MPL-2.0 */
/*
 * COPY TO ../worldmap-config.php (one directory ABOVE the web document root).
 * Never commit your production copy or put credentials in the web directory.
 */
return [
    // The same database as the OpenSimulator / OpenGenesisLINK ROBUST GridService.
    'OGL_DB_DSN' => 'mysql:host=127.0.0.1;port=3306;dbname=opensim;charset=utf8mb4',
    'OGL_DB_USER' => 'opensim_reader',
    'OGL_DB_PASS' => 'CHANGE_ME',
    'OGL_COORD_UNIT' => 'meters',
    'OGL_CENTER_X' => '5000',
    'OGL_CENTER_Y' => '5000',
];
