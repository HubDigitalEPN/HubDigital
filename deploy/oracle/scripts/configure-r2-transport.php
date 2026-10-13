<?php

declare(strict_types=1);

// Solo configura un destino público de Cloudflare; nunca copia claves a nginx.
if (PHP_OS_FAMILY !== 'Linux' || posix_geteuid() !== 0) {
    throw new RuntimeException('El transporte privado requiere la activación Linux como root.');
}
$release = realpath($argv[1] ?? '');
if ($release === false || ! str_starts_with($release, '/srv/hubdigital/releases/')) {
    throw new RuntimeException('Release ajena al directorio de despliegue.');
}
require $release.'/vendor/autoload.php';
$variables = Dotenv\Dotenv::parse(file_get_contents('/etc/hubdigital/hubdigital.env'));
$endpoint = trim((string) ($variables['R2_ENDPOINT'] ?? ''));
if ($endpoint === '' && ($variables['R2_ACCOUNT_ID'] ?? '') !== '') {
    $endpoint = 'https://'.trim($variables['R2_ACCOUNT_ID']).'.r2.cloudflarestorage.com';
}
$partes = parse_url($endpoint);
$host = strtolower((string) ($partes['host'] ?? ''));
$config = '/etc/nginx/conf.d/hubdigital-r2-transport.conf';
$directorio = '/run/hubdigital/r2-transport';
$metadatos = $directorio.'/origin-host';
if (($partes['scheme'] ?? '') !== 'https' || ! preg_match('/\A[a-f0-9]{32}(?:\.eu|\.fedramp)?\.r2\.cloudflarestorage\.com\z/', $host)
    || isset($partes['port']) || isset($partes['user']) || isset($partes['pass']) || isset($partes['query']) || isset($partes['fragment'])
    || ! in_array($partes['path'] ?? '', ['', '/'], true)) {
    if (is_file($config)) unlink($config);
    if (is_file($metadatos)) unlink($metadatos);
    echo "R2 conserva su transporte HTTPS directo.\n";
    exit;
}
$grupo = posix_getgrnam('www-data')['gid'] ?? throw new RuntimeException('Falta el grupo del runtime.');
if (! is_dir($directorio) && ! mkdir($directorio, 0750, true)) throw new RuntimeException('No se pudo crear el directorio del transporte.');
chown($directorio, 0); chgrp($directorio, $grupo); chmod($directorio, 0750);
$plantilla = file_get_contents($release.'/deploy/oracle/nginx/r2-transport.conf.template');
if ($plantilla === false || file_put_contents($config, str_replace('__R2_HOST__', $host, $plantilla), LOCK_EX) === false
    || file_put_contents($metadatos, $host, LOCK_EX) === false) throw new RuntimeException('No se pudo preparar el transporte privado.');
chown($config, 0); chmod($config, 0644);
chown($metadatos, 0); chgrp($metadatos, $grupo); chmod($metadatos, 0440);
echo "Transporte R2 privado preparado con TLS verificado y sin caché documental.\n";
