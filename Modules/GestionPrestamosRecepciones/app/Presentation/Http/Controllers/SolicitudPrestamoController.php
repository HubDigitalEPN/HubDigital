<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\ActualizarSolicitudPrestamoRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\EnviarSolicitudPrestamoRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\RegistrarSolicitudPrestamoRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\SubirActaFirmadaRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\ActaFirmadaResource;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\EnvioSolicitudPrestamoResource;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\SolicitudPrestamoResource;

final class SolicitudPrestamoController extends Controller
{
    public function store(
        RegistrarSolicitudPrestamoRequest $request,
        RegistrarSolicitudPrestamoHandler $handler,
    ): JsonResponse {
        $input = new RegistrarSolicitudPrestamoInput(
            investigadorId: $request->validated('investigador_id'),
            tituloEstudio: $request->validated('titulo_estudio'),
            institucionAdscripcion: $request->validated('institucion_adscripcion'),
            lineaInvestigacion: $request->validated('linea_investigacion'),
            propositoPrestamo: $request->validated('proposito_prestamo'),
            duracionPropuestaMeses: $request->validated('duracion_propuesta_meses'),
            items: $request->validated('items'),
        );

        $output = $handler->handle($input);

        return (new SolicitudPrestamoResource($output))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        ActualizarSolicitudPrestamoRequest $request,
        ActualizarSolicitudPrestamoHandler $handler,
        string $id,
    ): JsonResponse {
        $input = new ActualizarSolicitudPrestamoInput(
            solicitudId: $id,
            investigadorId: $request->validated('investigador_id'),
            tituloEstudio: $request->validated('titulo_estudio'),
            institucionAdscripcion: $request->validated('institucion_adscripcion'),
            lineaInvestigacion: $request->validated('linea_investigacion'),
            propositoPrestamo: $request->validated('proposito_prestamo'),
            duracionPropuestaMeses: $request->validated('duracion_propuesta_meses'),
            items: $request->validated('items'),
            justificacionExtendida: $request->validated('justificacion_extendida'),
        );

        $output = $handler->handle($input);

        return (new SolicitudPrestamoResource($output))
            ->response()
            ->setStatusCode(200);
    }

    public function enviar(
        EnviarSolicitudPrestamoRequest $request,
        EnviarSolicitudPrestamoHandler $handler,
        string $id,
    ): JsonResponse {
        $input = new EnviarSolicitudPrestamoInput(
            solicitudId: $id,
            investigadorId: $request->validated('investigador_id'),
        );

        $output = $handler->handle($input);

        return (new EnvioSolicitudPrestamoResource($output))
            ->response()
            ->setStatusCode(200);
    }

    public function subirActa(
        SubirActaFirmadaRequest $request,
        SubirActaFirmadaHandler $handler,
        string $id,
    ): JsonResponse {
        $ruta = Storage::putFile('actas-firmadas', $request->file('pdf_firmado'));

        $input = new SubirActaFirmadaInput(
            solicitudId: $id,
            investigadorId: $request->validated('investigador_id'),
            pdfFirmadoRuta: $ruta,
        );

        $output = $handler->handle($input);

        return (new ActaFirmadaResource($output))
            ->response()
            ->setStatusCode(201);
    }
}
