<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\SolicitarIntervencionCuratoriaRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\IntervencionCuratoriaResource;

/**
 * Controlador para solicitar la intervención curatoria en una solicitud.
 */
final class SolicitarIntervencionCuratoriaController
{
    public function __construct(private readonly SolicitarIntervencionCuratoriaHandler $handler) {}

    /**
     * @param SolicitarIntervencionCuratoriaRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function __invoke(SolicitarIntervencionCuratoriaRequest $request, string $id): JsonResponse
    {
        $output = ($this->handler)(
            new SolicitarIntervencionCuratoriaInput(
                solicitudId: $id,
                investigadorId: $request->user()->id,
            )
        );

        return IntervencionCuratoriaResource::make($output)->response();
    }
}
