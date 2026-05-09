<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesOutput;

/** @extends JsonResource<SincronizarEspecimenesOutput> */
final class SincronizarEspecimenesResource extends JsonResource
{
    public function __construct(private readonly SincronizarEspecimenesOutput $output)
    {
        parent::__construct($output);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'occurrenceIDs_actualizados' => $this->output->occurrenceIDsActualizados,
        ];
    }
}
