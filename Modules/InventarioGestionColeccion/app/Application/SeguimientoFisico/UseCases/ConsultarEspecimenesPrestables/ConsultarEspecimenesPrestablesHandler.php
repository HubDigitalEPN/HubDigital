<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\EspecimenPrestable;

/**
 * Qué especímenes de la colección pueden salir en préstamo.
 *
 * Es el punto de entrada público para GestionPrestamosRecepciones. Existe porque aquel
 * módulo venía resolviendo la pregunta por su cuenta, con SQL crudo y una copia de las
 * reglas: al añadirse el régimen de custodia, su copia se quedó vieja y siguió
 * ofreciendo material ya devuelto a su depositante. Quien es dueño de la regla es quien
 * debe responderla.
 *
 * El predicado vive en {@see EspecimenPrestable}
 * y el repositorio lo traduce a SQL; este handler solo traduce el resultado a primitivos.
 */
final class ConsultarEspecimenesPrestablesHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(ConsultarEspecimenesPrestablesInput $input): ConsultarEspecimenesPrestablesOutput
    {
        $filas = $this->especimenRepo->buscarPrestables(
            texto: $input->texto,
            ids: $input->ids,
            limite: $input->limite,
        );

        return new ConsultarEspecimenesPrestablesOutput(
            especimenes: array_map(
                fn (array $fila): EspecimenPrestableDto => new EspecimenPrestableDto(
                    especimenId: $fila['id'],
                    codigoCatalogo: $fila['codigoCatalogo'],
                    individualCount: $fila['individualCount'],
                    estado: $fila['estado'],
                    nombreCientifico: $fila['nombreCientifico'],
                ),
                $filas,
            ),
        );
    }
}
