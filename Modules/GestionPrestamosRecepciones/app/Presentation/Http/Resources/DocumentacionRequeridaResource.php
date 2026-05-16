<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GestionPrestamosRecepciones\Application\UseCases\DeterminarDocumentacionRequerida\DeterminarDocumentacionRequeridaOutput;

/** @mixin DeterminarDocumentacionRequeridaOutput */
final class DocumentacionRequeridaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'documentos_requeridos' => $this->documentosRequeridos,
        ];
    }
}
