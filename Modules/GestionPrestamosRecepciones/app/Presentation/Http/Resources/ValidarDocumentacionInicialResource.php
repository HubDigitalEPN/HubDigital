<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ValidarDocumentacionInicial\ValidarDocumentacionInicialOutput;

/** @mixin ValidarDocumentacionInicialOutput */
final class ValidarDocumentacionInicialResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'estado_documental' => $this->estadoDocumental->value,
        ];
    }
}
