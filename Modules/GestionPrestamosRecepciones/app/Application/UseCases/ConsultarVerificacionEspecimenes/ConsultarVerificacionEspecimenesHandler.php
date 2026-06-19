<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarVerificacionEspecimenes;

use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\VerificacionEspecimenesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ObservacionEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;

/**
 * Manejador del caso de uso para consultar la verificación de especímenes de un
 * préstamo, resolviendo el código externo legible de cada novedad.
 *
 * {@see ConsultarVerificacionEspecimenesInput}
 * {@see ConsultarVerificacionEspecimenesOutput}
 */
final class ConsultarVerificacionEspecimenesHandler
{
    /**
     * @param VerificacionEspecimenesRepositoryInterface $verificacionRepo Repositorio de verificaciones.
     * @param SolicitudPrestamoRepositoryInterface $solicitudRepo Repositorio de solicitudes (resolución de códigos).
     */
    public function __construct(
        private readonly VerificacionEspecimenesRepositoryInterface $verificacionRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
    ) {}

    /**
     * Ejecuta el caso de uso.
     *
     * @param ConsultarVerificacionEspecimenesInput $input Datos de entrada.
     * @return ConsultarVerificacionEspecimenesOutput Verificación lista para renderizar.
     */
    public function handle(ConsultarVerificacionEspecimenesInput $input): ConsultarVerificacionEspecimenesOutput
    {
        $verificacion = $this->verificacionRepo->buscarPorPrestamoYTipo(
            PrestamoId::fromString($input->prestamoId),
            $input->tipo,
        );

        if ($verificacion === null) {
            return new ConsultarVerificacionEspecimenesOutput(
                existe: false,
                resultado: null,
                observacionGeneral: null,
                observaciones: [],
            );
        }

        $observaciones = $verificacion->observaciones();

        $itemIds = array_map(
            fn (ObservacionEspecimen $obs) => $obs->itemPrestamoId,
            $observaciones,
        );

        $codigos = $this->solicitudRepo->mapaCodigosExternos($itemIds);

        $observacionesVista = array_map(
            fn (ObservacionEspecimen $obs) => new ObservacionEspecimenVista(
                codigoEspecimen: $codigos[$obs->itemPrestamoId] ?? $obs->itemPrestamoId,
                descripcion: $obs->descripcion,
            ),
            $observaciones,
        );

        return new ConsultarVerificacionEspecimenesOutput(
            existe: true,
            resultado: $verificacion->resultado(),
            observacionGeneral: $verificacion->observacionGeneral(),
            observaciones: $observacionesVista,
        );
    }
}
