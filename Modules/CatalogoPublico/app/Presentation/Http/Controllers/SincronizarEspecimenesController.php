<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesInput;
use Modules\CatalogoPublico\Presentation\Http\Requests\SincronizarEspecimenesRequest;
use Modules\CatalogoPublico\Presentation\Http\Resources\SincronizarEspecimenesResource;

final class SincronizarEspecimenesController
{
    public function __construct(private readonly SincronizarEspecimenesHandler $handler) {}

    public function __invoke(SincronizarEspecimenesRequest $request): JsonResponse
    {
        $output = $this->handler->handle(new SincronizarEspecimenesInput(
            especimenes: $request->validated('especimenes'),
        ));

        return (new SincronizarEspecimenesResource($output))
            ->response()
            ->setStatusCode(201);
    }
}
