<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\ValidarDocumentacionInicialRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\ValidarDocumentacionInicialResource;

/**
 * Controlador para validar la documentación inicial adjunta a una solicitud.
 */
final class ValidarDocumentacionInicialController
{
    public function __construct(private readonly ValidarDocumentacionInicialHandler $handler) {}

    /**
     * @param ValidarDocumentacionInicialRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function __invoke(ValidarDocumentacionInicialRequest $request, string $id): JsonResponse
    {
        $output = ($this->handler)(
            new ValidarDocumentacionInicialInput(
                solicitudId: $id,
                provinciaOrigen: $request->validated('provincia_origen'),
                documentosAdjuntos: $request->validated('documentos_adjuntos'),
            )
        );

        return ValidarDocumentacionInicialResource::make($output)->response();
    }
}
