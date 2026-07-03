<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearLocalidadYEnlazarVerbatim;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim\ConfirmarLocalidadCanonicaParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarLocalidadCanonicaParaVerbatim\ConfirmarLocalidadCanonicaParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad\RegistrarLocalidadHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarLocalidad\RegistrarLocalidadInput;

/**
 * Crea una localidad canónica nueva y, en la misma transacción, enlaza a ella
 * los especímenes de un `localidad_verbatim` pendiente (todo el grupo o solo
 * los seleccionados). Cierra el hueco de tener que salir a la pantalla de
 * Localidades a registrarla antes de poder confirmar en la bandeja.
 *
 * Compone los casos de uso existentes para reutilizar sus invariantes: la
 * validación de rango y el rechazo de duplicados viven en RegistrarLocalidad;
 * el enlace escalable (UPDATE por verbatim o por ids) en Confirmar. Si el
 * enlace falla, la transacción revierte también la creación (sin localidad
 * huérfana).
 */
final class CrearLocalidadYEnlazarVerbatimHandler
{
    public function __construct(
        private readonly RegistrarLocalidadHandler $registrar,
        private readonly ConfirmarLocalidadCanonicaParaVerbatimHandler $confirmar,
    ) {}

    public function handle(CrearLocalidadYEnlazarVerbatimInput $input): CrearLocalidadYEnlazarVerbatimOutput
    {
        return DB::transaction(function () use ($input): CrearLocalidadYEnlazarVerbatimOutput {
            $localidad = $this->registrar->handle(new RegistrarLocalidadInput(
                nombreCanonico: $input->nombreCanonico,
                rango: $input->rango,
            ));

            $localidadId = (string) $localidad->id;

            $enlace = $this->confirmar->handle(new ConfirmarLocalidadCanonicaParaVerbatimInput(
                verbatim: $input->verbatim,
                localidadId: $localidadId,
                especimenIds: $input->especimenIds,
            ));

            return new CrearLocalidadYEnlazarVerbatimOutput(
                localidadId: $localidadId,
                nombreCanonico: $localidad->nombreCanonico,
                especimenesEnlazados: $enlace->especimenesEnlazados,
            );
        });
    }
}
