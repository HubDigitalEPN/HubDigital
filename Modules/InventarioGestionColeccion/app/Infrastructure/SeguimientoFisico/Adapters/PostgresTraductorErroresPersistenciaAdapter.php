<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Illuminate\Database\QueryException;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TraductorErroresPersistenciaPort;
use Throwable;

/**
 * Traduce los errores de PostgreSQL/PDO a mensajes legibles:
 *
 * - Errores de base de datos (QueryException/PDOException) nunca se exponen crudos:
 *   se mapean por nombre de constraint conocido y, en su defecto, por código SQLSTATE.
 * - Los problemas de conexión/disponibilidad se distinguen de las violaciones de
 *   integridad, porque se resuelven reintentando y no son culpa del dato ingresado.
 * - Si el error no proviene de la base de datos, devuelve null para que la capa
 *   superior lo trate por otra vía.
 */
final class PostgresTraductorErroresPersistenciaAdapter implements TraductorErroresPersistenciaPort
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

    public function mensajeAmigable(Throwable $e): ?string
    {
        $errorBaseDatos = $this->detectarErrorBaseDatos($e);

        if ($errorBaseDatos === null) {
            return null;
        }

        return $this->mensajeParaErrorBaseDatos($errorBaseDatos);
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
        $sqlState = $this->extraerSqlState($mensaje, $db);

        // Los problemas de conexión/disponibilidad se evalúan primero: no son culpa
        // del dato ingresado y se resuelven reintentando, por lo que merecen un
        // mensaje distinto al de las violaciones de unicidad/integridad.
        if ($this->esErrorDeConexion($sqlState, $mensaje)) {
            return 'No se pudo conectar con la base de datos en este momento. Espera unos segundos e inténtalo de nuevo; si el problema persiste, avisa al administrador.';
        }

        $constraint = $this->extraerNombreConstraint($mensaje);

        if ($constraint !== null && isset($this->mensajesPorConstraint[$constraint])) {
            return $this->mensajesPorConstraint[$constraint];
        }

        return match ($sqlState) {
            '23505' => 'El registro ya existe: hay un valor que debe ser único y está duplicado.',
            '23503' => 'No se puede completar la operación porque el registro está vinculado con otros datos.',
            '23502' => 'Falta un dato obligatorio para completar la operación.',
            '23514' => 'Uno de los valores ingresados no cumple las restricciones permitidas.',
            '23000' => 'La operación viola una restricción de integridad de los datos.',
            '40001' => 'Hubo un conflicto al guardar simultáneamente con otra operación. Inténtalo de nuevo.',
            '40P01' => 'Hubo un bloqueo entre operaciones simultáneas (deadlock). Inténtalo de nuevo.',
            '57014' => 'La operación tardó demasiado y fue cancelada. Inténtalo de nuevo.',
            '55P03' => 'El registro está siendo usado por otra operación. Espera un momento e inténtalo de nuevo.',
            default => 'No se pudo completar la operación por un problema con la base de datos. Inténtalo de nuevo.',
        };
    }

    /**
     * Determina si el error es de conexión/disponibilidad del servidor (no de los datos).
     * Cubre los SQLSTATE de la clase 08 (fallo de conexión), agotamiento de recursos
     * (demasiadas conexiones, apagado del servidor) y, como respaldo, los mensajes típicos
     * del pooler de PostgreSQL/Supabase cuando no traen un SQLSTATE limpio.
     */
    private function esErrorDeConexion(string $sqlState, string $mensaje): bool
    {
        $estadosDeConexion = [
            '08000', // connection_exception
            '08003', // connection_does_not_exist
            '08006', // connection_failure
            '08001', // sqlclient_unable_to_establish_sqlconnection
            '08004', // sqlserver_rejected_establishment_of_sqlconnection
            '08007', // transaction_resolution_unknown
            '53300', // too_many_connections
            '53400', // configuration_limit_exceeded
            '57P01', // admin_shutdown
            '57P02', // crash_shutdown
            '57P03', // cannot_connect_now
            'HY000', // error genérico de PDO (a menudo "server has gone away" / timeouts)
        ];

        if (in_array($sqlState, $estadosDeConexion, true)) {
            return true;
        }

        $patrones = [
            'could not connect',
            'connection refused',
            'connection timed out',
            'connection reset',
            'server closed the connection',
            'no connection to the server',
            'terminating connection',
            'gone away',
            'too many connections',
            'could not translate host name',
            'connection to server',
            'sslconnection',
            'timeout expired',
        ];

        $mensajeMinuscula = strtolower($mensaje);

        foreach ($patrones as $patron) {
            if (str_contains($mensajeMinuscula, $patron)) {
                return true;
            }
        }

        return false;
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
