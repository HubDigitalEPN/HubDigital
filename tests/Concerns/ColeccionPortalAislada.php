<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Colección sintética por caso; DatabaseTransactions restaura los datos locales. */
trait ColeccionPortalAislada
{
    protected function setUpColeccionPortalAislada(): void
    {
        $conexion = DB::connection();
        // Laravel abre DatabaseTransactions antes de invocar este hook. No
        // permitir que esta preparación opere fuera de su rollback obligatorio.
        if (! $this->app->environment('testing') || $conexion->getDriverName() !== 'pgsql'
            || $conexion->transactionLevel() < 1) {
            throw new LogicException('La colección de prueba requiere PostgreSQL en testing y una transacción activa.');
        }

        // CASCADE incluye especímenes, divulgación y relaciones dependientes.
        // PostgreSQL revierte también TRUNCATE al terminar DatabaseTransactions;
        // se conservan el esquema y los catálogos institucionales del entorno.
        $conexion->statement('TRUNCATE TABLE taxonomia.taxones, taxonomia.localidades, taxonomia.muestras_colecta CASCADE');
    }
}
