<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarSolicitudPrestamo\RegistrarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ActualizarSolicitudPrestamo\ActualizarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudPrestamo\EnviarSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo\GenerarActaPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaPrestamo\GenerarActaPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaFirmada\SubirActaFirmadaInput;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitud;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: envio_solicitud_prestamo.feature
 * Capability: TramitacionSolicitudesInvestigador
 */
final class EnvioSolicitudPrestamoContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private RegistrarSolicitudPrestamoHandler $registrarHandler;

    private ActualizarSolicitudPrestamoHandler $actualizarHandler;

    private EnviarSolicitudPrestamoHandler $enviarHandler;

    private GenerarActaPrestamoHandler $generarActaHandler;

    private SubirActaFirmadaHandler $subirActaHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?SolicitudPrestamo $solicitudExistente = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ID fijo del investigador actor en todos los escenarios de esta feature
    private string $investigadorId = 'inv-001';

    // Datos canónicos de la solicitud para los escenarios de envío
    private array $datosSolicitudCompleta = [
        'titulo_estudio'           => 'Revisión taxonómica del género Morpho en Ecuador',
        'institucion_adscripcion'  => 'Universidad Central del Ecuador',
        'linea_investigacion'      => 'Entomología sistemática',
        'proposito_prestamo'       => 'Estudio comparativo de morfología alar',
        'duracion_propuesta_meses' => 3,
        'items'                    => [
            ['especimen_codigo_externo' => 'ESP-001', 'cantidad_solicitada' => 2],
            ['especimen_codigo_externo' => 'ESP-002', 'cantidad_solicitada' => 1],
        ],
    ];

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->registrarHandler   = $this->make(RegistrarSolicitudPrestamoHandler::class);
        $this->actualizarHandler  = $this->make(ActualizarSolicitudPrestamoHandler::class);
        $this->enviarHandler      = $this->make(EnviarSolicitudPrestamoHandler::class);
        $this->generarActaHandler = $this->make(GenerarActaPrestamoHandler::class);
        $this->subirActaHandler   = $this->make(SubirActaFirmadaHandler::class);
    }

    // ── Helpers de fixture ───────────────────────────────────────────────────

    /**
     * Crea, persiste y asigna una SolicitudPrestamo completa con los datos canónicos.
     * Las mutaciones de estado posteriores deben hacerse en el paso @Given que llame a este helper.
     */
    private function sembrarSolicitudBase(): SolicitudPrestamo
    {
        $repo = $this->make(SolicitudPrestamoRepositoryInterface::class);

        $solicitud = SolicitudPrestamo::crear(
            id:                     $repo->nextIdentity(),
            investigadorId:         $this->investigadorId,
            tituloEstudio:          $this->datosSolicitudCompleta['titulo_estudio'],
            institucionAdscripcion: $this->datosSolicitudCompleta['institucion_adscripcion'],
            lineaInvestigacion:     $this->datosSolicitudCompleta['linea_investigacion'],
            propositoPrestamo:      $this->datosSolicitudCompleta['proposito_prestamo'],
            duracionPropuestaMeses: $this->datosSolicitudCompleta['duracion_propuesta_meses'],
            items:                  $this->datosSolicitudCompleta['items'],
        );

        $repo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    /**
     * Crea, persiste y asigna una SolicitudPrestamo con información incompleta.
     */
    private function sembrarSolicitudIncompleta(): SolicitudPrestamo
    {
        $repo = $this->make(SolicitudPrestamoRepositoryInterface::class);

        $solicitud = SolicitudPrestamo::crearIncompleta(
            id:             $repo->nextIdentity(),
            investigadorId: $this->investigadorId,
        );

        $repo->guardar($solicitud);
        $this->solicitudExistente = $solicitud;

        return $solicitud;
    }

    // =========================================================================
    // ESCENARIO: Guardar una solicitud como borrador
    // =========================================================================

    /**
     * @Given que el investigador ha ingresado información en una solicitud
     */
    public function queElInvestigadorHaIngresadoInformacionEnUnaSolicitud(): void
    {
        // Verificar que los datos canónicos tienen todos los campos requeridos
        Assert::assertNotEmpty($this->investigadorId, 'investigador_id no puede estar vacío');
        Assert::assertNotEmpty($this->datosSolicitudCompleta['titulo_estudio'], 'titulo_estudio es requerido');
        Assert::assertNotEmpty($this->datosSolicitudCompleta['institucion_adscripcion'], 'institucion_adscripcion es requerido');
        Assert::assertNotEmpty($this->datosSolicitudCompleta['linea_investigacion'], 'linea_investigacion es requerido');
        Assert::assertNotEmpty($this->datosSolicitudCompleta['proposito_prestamo'], 'proposito_prestamo es requerido');
        Assert::assertGreaterThan(0, $this->datosSolicitudCompleta['duracion_propuesta_meses'], 'duracion_propuesta_meses debe ser mayor a 0');
        Assert::assertNotEmpty($this->datosSolicitudCompleta['items'], 'La solicitud debe incluir al menos un ítem');

        $this->sembrarSolicitudBase();
    }

    /**
     * @When el investigador registra la solicitud
     */
    public function elInvestigadorRegistraLaSolicitud(): void
    {
        try {
            $this->ultimaRespuesta = $this->registrarHandler->handle(
                new RegistrarSolicitudPrestamoInput(
                    investigadorId:         $this->investigadorId,
                    tituloEstudio:          $this->datosSolicitudCompleta['titulo_estudio'],
                    institucionAdscripcion: $this->datosSolicitudCompleta['institucion_adscripcion'],
                    lineaInvestigacion:     $this->datosSolicitudCompleta['linea_investigacion'],
                    propositoPrestamo:      $this->datosSolicitudCompleta['proposito_prestamo'],
                    duracionPropuestaMeses: $this->datosSolicitudCompleta['duracion_propuesta_meses'],
                    items:                  $this->datosSolicitudCompleta['items'],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    /**
     * @Then la solicitud queda registrada en estado borrador
     */
    public function laSolicitudQuedaRegistradaEnEstadoBorrador(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: ' . $this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitud::Borrador),
            "Se esperaba estado 'borrador', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Editar una solicitud en estado borrador
    // =========================================================================

    /**
     * @Given que existe una solicitud en estado borrador
     */
    public function queExisteUnaSolicitudEnEstadoBorrador(): void
    {
        $solicitud = $this->sembrarSolicitudBase();

        // Verificar que quedó persistida y en estado borrador
        $repo      = $this->make(SolicitudPrestamoRepositoryInterface::class);
        $persistida = $repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio tras guardarla');
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::Borrador),
            "Se esperaba que la solicitud recién creada tuviera estado 'borrador', se obtuvo: {$persistida->estado()->value}"
        );
    }

    /**
     * @Given el investigador tiene acceso a dicha solicitud
     */
    public function elInvestigadorTieneAccesoADichaSolicitud(): void
    {
        Assert::assertNotNull(
            $this->solicitudExistente,
            'Se esperaba una solicitud existente del step Dado anterior'
        );
        Assert::assertSame(
            $this->investigadorId,
            (string) $this->solicitudExistente->investigadorId(),
            'El investigador del test no es el propietario de la solicitud'
        );
    }

    /**
     * @When el investigador actualiza la información de la solicitud
     */
    public function elInvestigadorActualizaLaInformacionDeLaSolicitud(): void
    {
        Assert::assertNotNull(
            $this->solicitudExistente,
            'Se esperaba una solicitud existente del step Dado anterior'
        );

        // Datos nuevos — distintos a los originales para que la actualización sea verificable
        $tituloNuevo          = 'Morfología comparada de Lepidoptera neotropicales';
        $institucionNueva     = 'Escuela Politécnica Nacional';
        $lineaNueva           = 'Biología evolutiva';
        $propositoNuevo       = 'Análisis filogenético de caracteres morfológicos';
        $duracionNueva        = 6;

        // Verificar que los datos nuevos son distintos a los persistidos
        Assert::assertNotSame(
            $tituloNuevo,
            $this->solicitudExistente->tituloEstudio(),
            'El título nuevo debe ser distinto al original para que la actualización sea significativa'
        );
        Assert::assertNotSame(
            $institucionNueva,
            $this->solicitudExistente->institucionAdscripcion(),
            'La institución nueva debe ser distinta a la original'
        );

        try {
            $this->ultimaRespuesta = $this->actualizarHandler->handle(
                new ActualizarSolicitudPrestamoInput(
                    solicitudId:            (string) $this->solicitudExistente->id(),
                    investigadorId:         $this->investigadorId,
                    tituloEstudio:          $tituloNuevo,
                    institucionAdscripcion: $institucionNueva,
                    lineaInvestigacion:     $lineaNueva,
                    propositoPrestamo:      $propositoNuevo,
                    duracionPropuestaMeses: $duracionNueva,
                    items:                  $this->datosSolicitudCompleta['items'],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    /**
     * @Then la solicitud refleja la información actualizada
     */
    public function laSolicitudReflejaLaInformacionActualizada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: ' . $this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        // Verificar que los datos nuevos se reflejan en la respuesta
        Assert::assertSame('Morfología comparada de Lepidoptera neotropicales', $this->ultimaRespuesta->tituloEstudio);
        Assert::assertSame('Escuela Politécnica Nacional', $this->ultimaRespuesta->institucionAdscripcion);
        Assert::assertSame('Biología evolutiva', $this->ultimaRespuesta->lineaInvestigacion);
        Assert::assertSame('Análisis filogenético de caracteres morfológicos', $this->ultimaRespuesta->propositoPrestamo);
        Assert::assertSame(6, $this->ultimaRespuesta->duracionPropuestaMeses);
    }

    /**
     * @Then la solicitud permanece en estado borrador
     */
    public function laSolicitudPermanenceEnEstadoBorrador(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitud::Borrador),
            "Se esperaba que el estado permaneciera en 'borrador' tras la edición, se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Enviar una solicitud con información completa
    // =========================================================================

    /**
     * @Given que existe una solicitud en estado :estado_previo con su información requerida completa
     */
    public function queExisteUnaSolicitudEnEstadoConInformacionCompleta(string $estado_previo): void
    {
        $solicitud = $this->sembrarSolicitudBase();

        if ($estado_previo === 'observada') {
            $solicitud->enviar();
            $solicitud->observar(
                curadorId:   'cur-001',
                observacion: 'Requiere información adicional sobre el período de estudio',
            );
            $repo = $this->make(SolicitudPrestamoRepositoryInterface::class);
            $repo->guardar($solicitud);
        }

        // Verificar que la solicitud quedó en el estado esperado y con todos los campos requeridos
        $persistida = $repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud no fue encontrada en el repositorio');
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::from($estado_previo)),
            "Se esperaba estado '{$estado_previo}', se obtuvo: {$persistida->estado()->value}"
        );
        Assert::assertNotNull($persistida->tituloEstudio(), 'titulo_estudio no puede ser nulo para el envío');
        Assert::assertNotNull($persistida->institucionAdscripcion(), 'institucion_adscripcion no puede ser nulo para el envío');
        Assert::assertNotNull($persistida->lineaInvestigacion(), 'linea_investigacion no puede ser nulo para el envío');
        Assert::assertNotNull($persistida->propositoPrestamo(), 'proposito_prestamo no puede ser nulo para el envío');
        Assert::assertGreaterThan(0, $persistida->duracionPropuestaMeses(), 'duracion_propuesta_meses debe ser mayor a 0');
        Assert::assertNotEmpty($persistida->items(), 'La solicitud debe tener al menos un ítem para poder ser enviada');
    }

    /**
     * @When el investigador envía la solicitud
     */
    public function elInvestigadorEnviaLaSolicitud(): void
    {
        Assert::assertNotNull(
            $this->solicitudExistente,
            'Se esperaba una solicitud existente del step Dado anterior'
        );

        try {
            $this->ultimaRespuesta = $this->enviarHandler->handle(
                new EnviarSolicitudPrestamoInput(
                    solicitudId:    (string) $this->solicitudExistente->id(),
                    investigadorId: $this->investigadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    /**
     * @Then la solicitud queda en estado enviada
     */
    public function laSolicitudQuedaEnEstadoEnviada(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: ' . $this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estado->equals(EstadoSolicitud::Enviada),
            "Se esperaba transición al estado 'enviada', se obtuvo: {$this->ultimaRespuesta->estado->value}"
        );
        Assert::assertNotNull(
            $this->ultimaRespuesta->enviadaEn,
            "Se esperaba que 'enviada_en' quedara registrado tras el envío"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: No permitir enviar una solicitud con información incompleta
    // =========================================================================

    /**
     * @Given que existe una solicitud en estado :estado_previo con información incompleta
     */
    public function queExisteUnaSolicitudEnEstadoConInformacionIncompleta(string $estado_previo): void
    {
        $solicitud = $this->sembrarSolicitudIncompleta();

        if ($estado_previo === 'observada') {
            $solicitud->observar(
                curadorId:   'cur-001',
                observacion: 'Requiere información adicional sobre el período de estudio',
            );
            $repo = $this->make(SolicitudPrestamoRepositoryInterface::class);
            $repo->guardar($solicitud);
        }

        // Confirmar que el estado es el esperado antes de intentar el envío
        $persistida = $repo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida);
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitud::from($estado_previo)),
            "Se esperaba estado '{$estado_previo}', se obtuvo: {$persistida->estado()->value}"
        );
    }

    /**
     * @Then la solicitud permanece en estado :estado_previo
     */
    public function laSolicitudPermanenceEnEstado(string $estado_previo): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el envío fallara (solicitud con información incompleta) pero el handler completó sin error'
        );

        // Verificar en repositorio que el estado no cambió
        $repo      = $this->make(SolicitudPrestamoRepositoryInterface::class);
        $solicitud = $repo->buscarPorId($this->solicitudExistente->id());

        Assert::assertNotNull($solicitud);
        Assert::assertTrue(
            $solicitud->estado()->equals(EstadoSolicitud::from($estado_previo)),
            "Se esperaba que el estado permaneciera en '{$estado_previo}', se obtuvo: {$solicitud->estado()->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Recibir el acta de préstamo para firma
    // =========================================================================

    /**
     * @Given que existe una solicitud del investigador en estado aprobada
     */
    public function queExisteUnaSolicitudEnEstadoAprobada(): void
    {
        $solicitud = $this->sembrarSolicitudBase();

        $solicitud->enviar();
        $solicitud->aprobar(curadorId: 'cur-001');

        $repo = $this->make(SolicitudPrestamoRepositoryInterface::class);
        $repo->guardar($solicitud);
    }

    /**
     * @When el acta de préstamo es generada
     */
    public function elActaDePrestamoEsGenerada(): void
    {
        Assert::assertNotNull(
            $this->solicitudExistente,
            'Se esperaba una solicitud existente del step Dado anterior'
        );

        try {
            $this->ultimaRespuesta = $this->generarActaHandler->handle(
                new GenerarActaPrestamoInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    /**
     * @Then el investigador recibe una notificación con el acta para su firma
     */
    public function elInvestigadorRecibeUnaNotificacionConElActaParaSuFirma(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: ' . $this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->notificacionEnviada,
            'Se esperaba que la notificación al investigador fuera enviada'
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->pdfRuta,
            "Se esperaba 'pdf_ruta' con la ruta del acta generada"
        );
        Assert::assertNull(
            $this->ultimaRespuesta->pdfFirmadoRuta,
            "Se esperaba que 'pdf_firmado_ruta' fuera null — el acta aún no ha sido firmada"
        );
    }

    // =========================================================================
    // ESCENARIO: Firmar y enviar el acta de préstamo
    // =========================================================================

    /**
     * @Given que el investigador ha recibido el acta de préstamo
     */
    public function queElInvestigadorHaRecibidoElActaDePrestamo(): void
    {
        $solicitud = $this->sembrarSolicitudBase();

        $solicitud->enviar();
        $solicitud->aprobar(curadorId: 'cur-001');
        $solicitud->emitirActa(pdfRuta: 'actas/MEPN-INV-001-2026.pdf');

        $repo = $this->make(SolicitudPrestamoRepositoryInterface::class);
        $repo->guardar($solicitud);
    }

    /**
     * @When el investigador sube el acta firmada
     */
    public function elInvestigadorSubeElActaFirmada(): void
    {
        Assert::assertNotNull(
            $this->solicitudExistente,
            'Se esperaba una solicitud existente del step Dado anterior'
        );

        try {
            $this->ultimaRespuesta = $this->subirActaHandler->handle(
                new SubirActaFirmadaInput(
                    solicitudId:      (string) $this->solicitudExistente->id(),
                    investigadorId:   $this->investigadorId,
                    pdfFirmadoRuta:   'actas/firmadas/MEPN-INV-001-2026-firmada.pdf',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    /**
     * @Then el acta queda pendiente de validación
     */
    public function elActaQuedaPendienteDeValidacion(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: ' . $this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            'pendiente_validacion',
            $this->ultimaRespuesta->estadoActa,
            "Se esperaba que el acta quedara en 'pendiente_validacion' tras subir la firma"
        );
        Assert::assertNotEmpty(
            $this->ultimaRespuesta->pdfFirmadoRuta,
            "Se esperaba que 'pdf_firmado_ruta' quedara registrado en el acta"
        );
        Assert::assertNotNull(
            $this->ultimaRespuesta->firmadaSubidaEn,
            "Se esperaba que 'firmada_subida_en' quedara registrado"
        );
    }
}
