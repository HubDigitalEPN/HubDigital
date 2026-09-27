<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

$project = dirname(__DIR__, 3);
require $project.'/vendor/autoload.php';

$files = array_merge(
    glob($project.'/database/migrations/*.php') ?: [],
    glob($project.'/Modules/*/database/migrations/*.php') ?: [],
);
sort($files, SORT_STRING);

if ($files === []) {
    fwrite(STDERR, "No se encontraron migraciones para verificar.\n");
    exit(1);
}

foreach ($files as $file) {
    try {
        $migration = require $file;
    } catch (Throwable $error) {
        fwrite(STDERR, 'Migración incompatible: '.substr($file, strlen($project) + 1).': '.$error->getMessage().PHP_EOL);
        exit(1);
    }

    if (! $migration instanceof Migration) {
        fwrite(STDERR, 'La migración no devuelve una instancia de Laravel: '.substr($file, strlen($project) + 1).PHP_EOL);
        exit(1);
    }
}

echo 'OK migraciones: '.count($files).' clases cargadas sin ejecutar cambios en la base.'.PHP_EOL;
