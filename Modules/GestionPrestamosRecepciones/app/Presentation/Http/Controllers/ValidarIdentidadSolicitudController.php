<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\ValidarIdentidadSolicitudRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\ValidarIdentidadSolicitudResource;

/**
 * Controlador para validar la identidad del solicitante en base a su perfil y documento.
 */
final class ValidarIdentidadSolicitudController
{
    public function __construct(private readonly ValidarIdentidadSolicitudHandler $handler) {}

    /**
     * @param ValidarIdentidadSolicitudRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function __invoke(ValidarIdentidadSolicitudRequest $request, string $id): JsonResponse
    {
        $output = ($this->handler)(
            new ValidarIdentidadSolicitudInput(
                solicitudId: $id,
                nombrePerfil: $request->validated('nombre_perfil'),
                nombreEnDocumento: $request->validated('nombre_en_documento'),
            )
        );

        return ValidarIdentidadSolicitudResource::make($output)->response();
    }
}
