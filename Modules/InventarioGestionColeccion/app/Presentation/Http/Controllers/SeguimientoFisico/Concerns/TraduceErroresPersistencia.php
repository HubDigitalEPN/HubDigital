<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Traduce excepciones a mensajes legibles para el usuario en los componentes
 * Livewire de IoT/SeguimientoFisico.
 *
 * - Errores de base de datos (QueryException) nunca se muestran crudos: se mapean
 *   por nombre de constraint conocido y, en su defecto, por código SQLSTATE.
 * - Las excepciones de dominio (DomainException / InvalidArgumentException) ya traen
 *   mensajes en español y se muestran tal cual.
 * - Cualquier otro error se reporta al log y se muestra un mensaje genérico.
 */
trait TraduceErroresPersistencia
{
    /**
     * Mensaje amigable por nombre de constraint de la base de datos.
     * Laravel genera el nombre reemplazando '.' por '_' en el nombre de la tabla
     * (p. ej. la tabla "iot.unit_trays" produce "iot_unit_trays_..._unique").
     *
     * @var array<string, string>
     */
    private array $mensajesPorConstraint = [
        'iot_unit_trays_caja_id_numero_unique' => 'Ya existe un unit tray con ese número en la caja seleccionada. Usa un número distinto.',
        'iot_unit_tray_especimenes_especimen_id_unique' => 'Ese espécimen ya está asignado a un unit tray. Quítalo del tray actual antes de reasignarlo.',
        'iot_cajas_codigo_unique' => 'Ya existe una caja con ese código.',
        'iot_cajas_codigo_rfid_unique' => 'Ese código RFID ya está asignado a otra caja.',
        'iot_gabinetes_codigo_unique' => 'Ya existe un gabinete con ese código.',
        'iot_ranuras_gabinete_gabinete_id_numero_ranura_unique' => 'Esa ranura ya existe en el gabinete.',
        'taxonomia_taxones_nombre_cientifico_rango_unique' => 'Ya existe un taxón con ese nombre científico y rango.',
        'taxonomia_entidades_depositantes_nombre_unique' => 'Ya existe una entidad depositante con ese nombre.',
        'taxonomia_especimenes_codigo_catalogo_unique' => 'Ya existe un espécimen con ese código de catálogo.',
        'taxonomia_especimenes_occurrence_id_unique' => 'Ese occurrenceID ya está registrado en otro espécimen.',
        'taxonomia_especimen_identificadores_especimen_id_tipo_valor_unique' => 'Ese identificador ya está registrado para el espécimen.',
    ];

    protected function traducirErrorParaUsuario(Throwable $e): string
    {
        $errorBaseDatos = $this->detectarErrorBaseDatos($e);

        if ($errorBaseDatos !== null) {
            report($e);

            return $this->mensajeParaErrorBaseDatos($errorBaseDatos);
        }

        // Las reglas de dominio y validación ya producen mensajes en español.
        if ($e instanceof \DomainException || $e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }

        report($e);

        return 'Ocurrió un error inesperado al procesar la operación. Inténtalo de nuevo o contacta al administrador si persiste.';
    }

    /**
     * Devuelve la QueryException/PDOException presente en la cadena de excepciones,
     * o null si el error no es de base de datos.
     */
    private function detectarErrorBaseDatos(Throwable $e): ?Throwable
    {
        $actual = $e;

        while ($actual !== null) {
            if ($actual instanceof QueryException || $actual instanceof \PDOException) {
                return $actual;
            }

            $actual = $actual->getPrevious();
        }

        return null;
    }

    private function mensajeParaErrorBaseDatos(Throwable $db): string
    {
        $mensaje = $db->getMessage();
        $constraint = $this->extraerNombreConstraint($mensaje);

        if ($constraint !== null && isset($this->mensajesPorConstraint[$constraint])) {
            return $this->mensajesPorConstraint[$constraint];
        }

        return match ($this->extraerSqlState($mensaje, $db)) {
            '23505' => 'El registro ya existe: hay un valor que debe ser único y está duplicado.',
            '23503' => 'No se puede completar la operación porque el registro está vinculado con otros datos.',
            '23502' => 'Falta un dato obligatorio para completar la operación.',
            '23514' => 'Uno de los valores ingresados no cumple las restricciones permitidas.',
            '23000' => 'La operación viola una restricción de integridad de los datos.',
            default => 'No se pudo guardar la información por un problema con la base de datos. Inténtalo de nuevo.',
        };
    }

    private function extraerNombreConstraint(string $mensaje): ?string
    {
        if (preg_match('/constraint "([^"]+)"/', $mensaje, $coincidencias) === 1) {
            return $coincidencias[1];
        }

        return null;
    }

    private function extraerSqlState(string $mensaje, Throwable $db): string
    {
        if (preg_match('/SQLSTATE\[(\w+)\]/', $mensaje, $coincidencias) === 1) {
            return $coincidencias[1];
        }

        return (string) $db->getCode();
    }
}
