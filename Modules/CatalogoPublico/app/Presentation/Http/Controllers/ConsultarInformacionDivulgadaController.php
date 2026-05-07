<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada\ConsultarInformacionDivulgadaHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada\ConsultarInformacionDivulgadaInput;
use Modules\CatalogoPublico\Presentation\Http\Resources\InformacionDivulgadaResource;

final class ConsultarInformacionDivulgadaController
{
    public function __construct(private readonly ConsultarInformacionDivulgadaHandler $handler) {}

    public function __invoke(Request $request, string $occurrenceID): JsonResponse
    {
        try {
            $output = $this->handler->handle(new ConsultarInformacionDivulgadaInput(
                occurrenceID: $occurrenceID,
            ));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return (new InformacionDivulgadaResource($output))
            ->response()
            ->setStatusCode(200);
    }
}
