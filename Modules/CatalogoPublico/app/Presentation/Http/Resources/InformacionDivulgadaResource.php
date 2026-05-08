<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada\ConsultarInformacionDivulgadaOutput;

/** @extends JsonResource<ConsultarInformacionDivulgadaOutput> */
final class InformacionDivulgadaResource extends JsonResource
{
    public function __construct(private readonly ConsultarInformacionDivulgadaOutput $output)
    {
        parent::__construct($output);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->output->datosVisibles;
    }
}
