<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CompletarDatosManualmente\CompletarDatosManualesOutput;

/** @mixin CompletarDatosManualesOutput */
final class CompletarDatosManualesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'estado' => $this->estado->value,
        ];
    }
}
