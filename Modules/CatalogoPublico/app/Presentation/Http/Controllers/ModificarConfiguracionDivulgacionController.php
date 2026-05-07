<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionHandler;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionInput;
use Modules\CatalogoPublico\Presentation\Http\Requests\ModificarConfiguracionDivulgacionRequest;
use Modules\CatalogoPublico\Presentation\Http\Resources\ModificarConfiguracionDivulgacionResource;

final class ModificarConfiguracionDivulgacionController
{
    public function __construct(private readonly ModificarConfiguracionDivulgacionHandler $handler) {}

    public function __invoke(ModificarConfiguracionDivulgacionRequest $request): JsonResponse
    {
        try {
            $output = $this->handler->handle(new ModificarConfiguracionDivulgacionInput(
                curadorId: $request->validated('curador_id'),
                especimenes: $request->validated('especimenes'),
            ));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return (new ModificarConfiguracionDivulgacionResource($output))
            ->response()
            ->setStatusCode(200);
    }
}
