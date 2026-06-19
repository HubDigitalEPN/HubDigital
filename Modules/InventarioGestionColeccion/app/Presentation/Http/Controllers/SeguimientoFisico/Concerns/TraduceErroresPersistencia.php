<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TraductorErroresPersistenciaPort;
use Throwable;

/**
 * Traduce excepciones a mensajes legibles para el usuario en los componentes
 * Livewire de IoT/SeguimientoFisico.
 *
 * - Los errores de base de datos se delegan al TraductorErroresPersistenciaPort:
 *   el conocimiento del motor (SQLSTATE, constraints) vive en infraestructura.
 * - Las excepciones de dominio (DomainException / InvalidArgumentException) ya traen
 *   mensajes en español y se muestran tal cual.
 * - Cualquier otro error se reporta al log y se muestra un mensaje genérico.
 */
trait TraduceErroresPersistencia
{
    /**
     * Ejecuta la carga inicial de datos (típicamente en mount) protegiéndola de
     * fallos de base de datos. Si la carga falla, deja un mensaje claro en
     * $this->errorMessage y permite que la página se renderice (con listas
     * vacías) en lugar de romper con un error 500 sin contexto para el usuario.
     *
     * Requiere que el componente declare la propiedad pública ?string $errorMessage.
     */
    protected function cargarProtegido(callable $carga): void
    {
        try {
            $carga();
        } catch (Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    protected function traducirErrorParaUsuario(Throwable $e): string
    {
        $mensajeBaseDatos = app(TraductorErroresPersistenciaPort::class)->mensajeAmigable($e);

        if ($mensajeBaseDatos !== null) {
            report($e);

            return $mensajeBaseDatos;
        }

        // Las reglas de dominio y validación ya producen mensajes en español.
        if ($e instanceof \DomainException || $e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }

        report($e);

        return 'Ocurrió un error inesperado al procesar la operación. Inténtalo de nuevo o contacta al administrador si persiste.';
    }
}
