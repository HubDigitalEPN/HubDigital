<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionOutput;

/** @extends JsonResource<ModificarConfiguracionDivulgacionOutput> */
final class ModificarConfiguracionDivulgacionResource extends JsonResource
{
    public function __construct(private readonly ModificarConfiguracionDivulgacionOutput $output)
    {
        parent::__construct($output);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'occurrenceIDs_modificados' => $this->output->occurrenceIDsModificados,
        ];
    }
}
