<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SolicitarIntervencionCuratoria\SolicitarIntervencionCuratoriaInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\SolicitarIntervencionCuratoriaRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\IntervencionCuratoriaResource;

final class SolicitarIntervencionCuratoriaController
{
    public function __construct(private readonly SolicitarIntervencionCuratoriaHandler $handler) {}

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
