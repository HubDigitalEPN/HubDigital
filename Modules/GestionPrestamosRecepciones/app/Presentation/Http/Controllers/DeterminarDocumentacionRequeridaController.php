<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\DocumentacionRequeridaResource;

final class DeterminarDocumentacionRequeridaController
{
    public function __construct(private readonly DeterminarDocumentacionRequeridaHandler $handler) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $output = ($this->handler)(
            new DeterminarDocumentacionRequeridaInput(solicitudId: $id)
        );

        return DocumentacionRequeridaResource::make($output)->response();
    }
}
