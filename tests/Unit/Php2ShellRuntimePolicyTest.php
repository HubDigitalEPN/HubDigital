<?php

declare(strict_types=1);

test('el despliegue rechaza versiones PHP2Shell vulnerables y versiones previas no soportadas', function (string $version, bool $expected): void {
    $policy = require dirname(__DIR__, 2).'/deploy/oracle/scripts/php-security-policy.php';
    expect($policy($version))->toBe($expected);
})->with([
    ['8.3.36', false], ['8.4.0', false], ['8.4.25', false], ['8.4.26', false],
    ['8.4.27', true], ['8.4.28', true], ['8.4.27RC1', false],
    ['8.5.0', false], ['8.5.11', false], ['8.5.12', true], ['8.5.13', true],
    ['8.6.0', false], ['', false],
]);
