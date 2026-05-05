<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoOutput;

/** @mixin RegistrarSolicitudDepositoOutput */
final class RegistrarSolicitudDepositoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'estado' => $this->estado->value,
        ];
    }
}
