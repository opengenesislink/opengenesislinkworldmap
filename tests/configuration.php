<?php
/** SPDX-License-Identifier: MPL-2.0 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/map.php';

function testAssert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAILED: ' . $message . PHP_EOL);
        exit(1);
    }
}

putenv('OGL_CONFIG_PATH=' . sys_get_temp_dir() . '/worldmap-test-config-does-not-exist.php');
putenv('OGL_DB_DSN');
try {
    mapDatabase();
    testAssert(false, 'missing config must reject connection');
} catch (MapConfigurationException $error) {
    testAssert(mapPublicError($error)['code'] === 'configuration_required', 'configuration code is actionable');
}
putenv('OGL_CENTER_X=6200');
testAssert(mapSetting('OGL_CENTER_X', '5000') === '6200', 'process environment override');
testAssert(mapPublicError(new RuntimeException('sensitive password here'))['error'] === 'Die Regionsdaten sind derzeit nicht verfügbar.', 'do not expose exception messages');
echo "OK: configuration diagnostics tests" . PHP_EOL;
