<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudDeposito\RegistrarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\RegistrarSolicitudDepositoRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\RegistrarSolicitudDepositoResource;

/**
 * Controlador para registrar una nueva solicitud de depósito.
 *
 * Recibe los datos iniciales de una solicitud de depósito y delega la lógica al
 * caso de uso correspondiente.
 */
final class RegistrarSolicitudDepositoController
{
    public function __construct(private readonly RegistrarSolicitudDepositoHandler $handler) {}

    /**
     * Maneja la petición HTTP para registrar una solicitud de depósito.
     *
     * @param  RegistrarSolicitudDepositoRequest  $request
     * @return JsonResponse
     */
    public function __invoke(RegistrarSolicitudDepositoRequest $request): JsonResponse
    {
        $output = ($this->handler)(
            new RegistrarSolicitudDepositoInput(
                investigadorId: $request->user()->id,
                tipoTramite: $request->validated('tipo_tramite'),
            )
        );

        return RegistrarSolicitudDepositoResource::make($output)
            ->response()
            ->setStatusCode(201);
    }
}
