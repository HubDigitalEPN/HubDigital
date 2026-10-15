<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

// El empaquetador crea y retira estas copias. Cada proceso conserva PostgreSQL
// real y todos los casos, pero no comparte TRUNCATE ni temporales con los demás.
$prefijo = getenv('HUBDIGITAL_TEST_DATABASE_PREFIX');
$token = getenv('TEST_TOKEN');
if ($prefijo === false || $prefijo === '' || $token === false) {
    return;
}
if (! preg_match('/^hd_pkg_[a-f0-9]{12}$/D', $prefijo) || ! preg_match('/^[1-4]$/D', $token)) {
    throw new RuntimeException('Identidad inválida del proceso de pruebas aislado.');
}
$proyecto = realpath(dirname(__DIR__));
$raiz = realpath((string) getenv('HUBDIGITAL_TEST_RUNTIME_DIR'));
$esperada = $proyecto.DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'validation'.DIRECTORY_SEPARATOR.$prefijo;
if ($raiz === false || $raiz !== $esperada) {
    throw new RuntimeException('El almacenamiento de pruebas debe permanecer en su directorio privado del proyecto.');
}
$almacenamiento = $raiz.DIRECTORY_SEPARATOR.'worker-'.$token;
if (! is_dir($almacenamiento.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views')) {
    throw new RuntimeException('El empaquetador no preparó el almacenamiento del proceso.');
}
foreach ([
    'DB_DATABASE' => $prefijo.'_'.$token,
    'TEST_DB_DATABASE' => $prefijo.'_'.$token,
    'DB_URL' => '',
    'LARAVEL_STORAGE_PATH' => $almacenamiento,
    'VIEW_COMPILED_PATH' => $almacenamiento.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views',
    'HUBDIGITAL_TEMPORARY_DIRECTORY' => $almacenamiento.DIRECTORY_SEPARATOR.'document-processing',
    // El framework consulta $_SERVER; ningún caso debe crear una segunda base
    // vacía a partir del nombre que el empaquetador ya preparó y migró.
    'LARAVEL_PARALLEL_TESTING_WITHOUT_DATABASES' => '1',
] as $nombre => $valor) {
    $_ENV[$nombre] = $_SERVER[$nombre] = $valor;
    putenv($nombre.'='.$valor);
}
