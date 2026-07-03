<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\CrearTaxonYEnlazarVerbatim;

use Illuminate\Support\Facades\DB;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim\ConfirmarTaxonCanonicoParaVerbatimHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfirmarTaxonCanonicoParaVerbatim\ConfirmarTaxonCanonicoParaVerbatimInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon\RegistrarTaxonHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarTaxon\RegistrarTaxonInput;

/**
 * Crea un taxón canónico nuevo y, en la misma transacción, enlaza a él los
 * especímenes de un `taxon_verbatim` pendiente (todo el grupo o solo los
 * seleccionados). Cierra el hueco de tener que salir a "Gestionar taxones" a
 * registrarlo antes de poder confirmar en la bandeja.
 *
 * Compone los casos de uso existentes para reutilizar sus invariantes: la
 * validación de rango, el rechazo de duplicados y la jerarquía viven en
 * RegistrarTaxon; el enlace escalable (por verbatim o por ids) en Confirmar.
 * Si el enlace falla, la transacción revierte también la creación.
 */
final class CrearTaxonYEnlazarVerbatimHandler
{
    public function __construct(
        private readonly RegistrarTaxonHandler $registrar,
        private readonly ConfirmarTaxonCanonicoParaVerbatimHandler $confirmar,
    ) {}

    public function handle(CrearTaxonYEnlazarVerbatimInput $input): CrearTaxonYEnlazarVerbatimOutput
    {
        return DB::transaction(function () use ($input): CrearTaxonYEnlazarVerbatimOutput {
            $taxon = $this->registrar->handle(new RegistrarTaxonInput(
                nombreCientifico: $input->nombreCientifico,
                rango: $input->rango,
            ));

            $taxonId = (string) $taxon->id;

            $enlace = $this->confirmar->handle(new ConfirmarTaxonCanonicoParaVerbatimInput(
                verbatim: $input->verbatim,
                taxonId: $taxonId,
                especimenIds: $input->especimenIds,
            ));

            return new CrearTaxonYEnlazarVerbatimOutput(
                taxonId: $taxonId,
                nombreCientifico: $taxon->nombreCientifico,
                especimenesEnlazados: $enlace->especimenesEnlazados,
            );
        });
    }
}
