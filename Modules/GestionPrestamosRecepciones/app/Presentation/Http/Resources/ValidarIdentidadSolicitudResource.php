<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarIdentidadSolicitud\ValidarIdentidadSolicitudOutput;

/** @mixin ValidarIdentidadSolicitudOutput */
final class ValidarIdentidadSolicitudResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'resultado' => $this->resultado->value,
            'acciones_permitidas' => $this->accionesPermitidas,
        ];
    }
}
