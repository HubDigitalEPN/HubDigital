<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\CargarDocumentacionOficialRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\CargarDocumentacionOficialResource;

final class CargarDocumentacionOficialController
{
    public function __construct(private readonly CargarDocumentacionOficialHandler $handler) {}

    public function __invoke(CargarDocumentacionOficialRequest $request, string $id): JsonResponse
    {
        $output = ($this->handler)(
            new CargarDocumentacionOficialInput(
                solicitudId: $id,
                documentos: $request->validated('documentos'),
            )
        );

        return CargarDocumentacionOficialResource::make($output)->response();
    }
}
