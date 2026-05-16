<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\CompletarDatosManualesRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\CompletarDatosManualesResource;

final class CompletarDatosManualesController
{
    public function __construct(private readonly CompletarDatosManualesHandler $handler) {}

    public function __invoke(CompletarDatosManualesRequest $request, string $id): JsonResponse
    {
        $output = ($this->handler)(
            new CompletarDatosManualesInput(
                solicitudId: $id,
                campo: $request->validated('campo'),
                valor: $request->validated('valor'),
            )
        );

        return CompletarDatosManualesResource::make($output)->response();
    }
}
