<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaTransferenciaDominioGenerada;
use Modules\GestionPrestamosRecepciones\Domain\Events\CodigoQRAsignado;
use Modules\GestionPrestamosRecepciones\Domain\Events\DatoFaltanteCompletado;
use Modules\GestionPrestamosRecepciones\Domain\Events\DocumentacionOficialCargada;
use Modules\GestionPrestamosRecepciones\Domain\Events\DomainEvent;
use Modules\GestionPrestamosRecepciones\Domain\Events\IntervencionCuratoriaSolicitada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudAprobadaDocumentalmente;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudDepositoCreada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudDepositoPendienteDeRevision;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudPriorizada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudRechazadaDocumentalmente;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudRequiereCorreccion;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\DocumentacionInsuficiente;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionEstadoInvalida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlertaSolicitudId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DocumentoAdjunto;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrioridadSolicitud;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoAlerta;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoRechazoDocumental;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

/**
 * Agregado raíz que representa la solicitud de depósito o donación de especímenes
 * que inicia el investigador.
 *
 * Acumula la información declarada por el investigador (origen de recolección,
 * situación regulatoria, provincia), la documentación oficial adjunta y los datos
 * extraídos automáticamente de ella ({@see integrarDatosDeDocumentos()}). Lleva la
 * cuenta de los datos faltantes obligatorios que deben completarse manualmente
 * antes de avanzar a revisión por curaduría, o escalarse a intervención curatorial
 * cuando no hay documentación disponible. Su ciclo de vida se modela con
 * {@see EstadoSolicitudDeposito}.
 *
 * Construir vía {@see self::crear()}; rehidratar vía {@see self::reconstituir()}.
 */
final class SolicitudDeposito
{
    // ── Identidad ────────────────────────────────────────────────

    private SolicitudDepositoId $id;

    private NumeroSolicitudDeposito $numero;

    // ── Datos del trámite ────────────────────────────────────────

    private string $investigadorId;

    private TipoTramite $tipoTramite;

    private EstadoSolicitudDeposito $estado;

    // ── Información declarada por el investigador ────────────────

    private ?string $origenRecoleccion = null;

    private ?string $situacionRegulatoria = null;

    private ?string $provinciaOrigen = null;

    private bool $sinDocumentacion = false;

    // ── Documentación ────────────────────────────────────────────

    /** @var DocumentoAdjunto[] */
    private array $documentosAdjuntos = [];

    /** @var string[] Nombres de campos pendientes de completar manualmente */
    private array $datosFaltantes = [];

    /** @var string[] Nombres de campos que fueron ingresados o corregidos manualmente */
    private array $datosIngresadosManualmente = [];

    // ── Datos integrados de documentación oficial ────────────────

    private ?string $nroPermisoRecoleccion = null;

    private ?string $nroPermisoMovilizacion = null;

    private ?string $grupoAnimal = null;

    private ?string $localidad = null;

    private ?string $origenDonacion = null;

    private ?string $nombreInvestigadorDocumento = null;

    private ?string $nroIndividuos = null;

    private ?string $nroMorfoespecies = null;

    private ?string $nroLotes = null;

    // ── Decisión curatorial (aprobación documental) ──────────────

    private ?string $curadorResponsable = null;

    private ?DateTimeImmutable $aprobadaEn = null;

    private ?DateTimeImmutable $rechazadaEn = null;

    private ?CodigoQRLote $codigoQR = null;

    private ?string $comentarioCurador = null;

    private PrioridadSolicitud $prioridad = PrioridadSolicitud::Normal;

    private ?DocumentoAdjunto $actaTransferenciaDominio = null;

    /** @var AlertaSolicitud[] Alertas justificadas que el curador revisa */
    private array $alertas = [];

    // ── Cola interna de eventos de dominio ───────────────────────

    /** @var DomainEvent[] */
    private array $events = [];

    // ── Constructor ──────────────────────────────────────────────

    private function __construct() {}

    // ── Factory Method ───────────────────────────────────────────

    /**
     * Crea una solicitud nueva en estado EnBorrador.
     *
     * @throws \DomainException Si el investigadorId está vacío.
     */
    public static function crear(
        SolicitudDepositoId $id,
        NumeroSolicitudDeposito $numero,
        string $investigadorId,
        string $tipoTramite,
    ): self {
        if (trim($investigadorId) === '') {
            throw new \DomainException('El investigadorId no puede estar vacío');
        }

        $solicitud = new self;
        $solicitud->id = $id;
        $solicitud->numero = $numero;
        $solicitud->investigadorId = $investigadorId;
        $solicitud->tipoTramite = TipoTramite::from($tipoTramite);
        $solicitud->estado = EstadoSolicitudDeposito::EnBorrador;

        $solicitud->events[] = new SolicitudDepositoCreada(
            solicitudId: $id,
            investigadorId: $investigadorId,
            tipoTramite: $tipoTramite,
        );

        return $solicitud;
    }

    // ── Métodos de Negocio ───────────────────────────────────────

    public function declararOrigenRecoleccion(string $origen): void
    {
        if (trim($origen) === '') {
            throw new \DomainException('El origen de recolección no puede estar vacío');
        }

        $this->origenRecoleccion = $origen;
    }

    public function declararSituacionRegulatoria(string $situacion): void
    {
        if (trim($situacion) === '') {
            throw new \DomainException('La situación regulatoria no puede estar vacía');
        }

        $this->situacionRegulatoria = $situacion;
    }

    public function declararProvincia(string $provincia): void
    {
        if (trim($provincia) === '') {
            throw new \DomainException('La provincia no puede estar vacía');
        }

        $this->provinciaOrigen = $provincia;
    }

    public function marcarSinDocumentacionDisponible(): void
    {
        $this->sinDocumentacion = true;
    }

    public function adjuntarDocumento(string $nombre, string $ruta): void
    {
        $this->documentosAdjuntos[$nombre] = DocumentoAdjunto::of($nombre, $ruta);
    }

    public function marcarDatoComoFaltante(string $campo): void
    {
        if (trim($campo) === '') {
            throw new \DomainException('El nombre del dato faltante no puede estar vacío');
        }

        if (! in_array($campo, $this->datosFaltantes, true)) {
            $this->datosFaltantes[] = $campo;
        }
    }

    /**
     * Integra los datos extraídos de la documentación oficial en la solicitud.
     * Marca automáticamente como faltante cualquier campo obligatorio que no pudo ser extraído.
     */
    /**
     * @param  string[]  $nombresDocumentos  Nombres de los documentos de los que se extrajeron los datos.
     */
    public function integrarDatosDeDocumentos(DatosIntegradosDocumento $datos, array $nombresDocumentos): void
    {
        // Cada llamada re-evalúa los datos desde cero: limpiamos los faltantes
        // previos para que no persistan si el nuevo run sí los extrajo.
        $this->datosFaltantes = [];

        $esExtranjero = $this->origenRecoleccion === 'Exterior (Extranjero)';

        if ($datos->nroPermisoRecoleccion !== null) {
            $this->nroPermisoRecoleccion = $datos->nroPermisoRecoleccion;
        } elseif ($this->tipoTramite->equals(TipoTramite::Deposito)) {
            $this->marcarDatoComoFaltante($esExtranjero ? 'N.º Investigación' : 'N.º Permiso Recolección');
        }

        if ($datos->nroPermisoMovilizacion !== null) {
            $this->nroPermisoMovilizacion = $datos->nroPermisoMovilizacion;
        }

        if ($datos->provinciaOrigen !== null) {
            $this->provinciaOrigen = $datos->provinciaOrigen;
        } elseif ($this->tipoTramite->equals(TipoTramite::Deposito)) {
            $this->marcarDatoComoFaltante($esExtranjero ? 'Administración Política' : 'Provincia');
        }

        if (! $esExtranjero
            && $this->nroPermisoMovilizacion === null
            && $this->tipoTramite->equals(TipoTramite::Deposito)
            && ($datos->provinciaOrigen === null || strtolower(trim($datos->provinciaOrigen)) !== 'pichincha')
        ) {
            $this->marcarDatoComoFaltante('N.º Permiso Movilización');
        }

        if ($datos->grupoAnimal !== null) {
            $this->grupoAnimal = $datos->grupoAnimal;
        } elseif ($this->tipoTramite->equals(TipoTramite::Deposito)) {
            $this->marcarDatoComoFaltante('Grupo Animal');
        }

        if ($datos->localidad !== null) {
            $this->localidad = $datos->localidad;
        } elseif ($this->tipoTramite->equals(TipoTramite::Deposito)) {
            $this->marcarDatoComoFaltante('Localidad');
        }

        if ($datos->origenDonacion !== null) {
            $this->origenDonacion = $datos->origenDonacion;
        }

        if ($datos->nombreInvestigador !== null) {
            $this->nombreInvestigadorDocumento = $datos->nombreInvestigador;
        }

        if ($this->nroIndividuos === null) {
            $this->marcarDatoComoFaltante('N.º Individuos');
        }

        if (! $esExtranjero) {
            if ($this->nroMorfoespecies === null) {
                $this->marcarDatoComoFaltante('N.º Morfoespecies');
            }

            if ($this->nroLotes === null) {
                $this->marcarDatoComoFaltante('N.º Lotes');
            }
        }

        $this->events[] = new DocumentacionOficialCargada(
            solicitudId: $this->id,
            nombresDocumentos: $nombresDocumentos,
        );
    }

    public function completarDatoFaltante(string $campo, string $valor): void
    {
        if (trim($valor) === '') {
            throw new \DomainException(
                sprintf('El valor para el campo "%s" no puede estar vacío', $campo)
            );
        }

        $this->asignarCampoPorNombre($campo, $valor);

        $this->datosFaltantes = array_values(
            array_filter($this->datosFaltantes, fn ($c) => $c !== $campo)
        );

        if (! in_array($campo, $this->datosIngresadosManualmente, true)) {
            $this->datosIngresadosManualmente[] = $campo;
        }

        if ($campo === 'Provincia'
            && strtolower(trim($valor)) !== 'pichincha'
            && $this->nroPermisoMovilizacion === null
            && ! in_array('N.º Permiso Movilización', $this->datosFaltantes, true)
        ) {
            $this->marcarDatoComoFaltante('N.º Permiso Movilización');
        }

        $this->events[] = new DatoFaltanteCompletado(
            solicitudId: $this->id,
            campo: $campo,
            valor: $valor,
        );
    }

    /**
     * Escala la solicitud a asesoría curatorial cuando el investigador no dispone
     * de documentación. Solo permitido desde EnBorrador y tras marcarla sin documentación.
     *
     * @throws DocumentacionInsuficiente Si no se marcó como sin documentación.
     * @throws TransicionEstadoInvalida Si no está en estado EnBorrador.
     */
    public function escalarAIntervencionCuratoria(): void
    {
        if (! $this->sinDocumentacion) {
            throw DocumentacionInsuficiente::paraEscalada();
        }

        if (! $this->estado->equals(EstadoSolicitudDeposito::EnBorrador)) {
            throw TransicionEstadoInvalida::de($this->estado->value, EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial->value);
        }

        $this->estado = EstadoSolicitudDeposito::RetenidaParaAsesoriaCuratorial;

        $this->events[] = new IntervencionCuratoriaSolicitada(
            solicitudId: $this->id,
            investigadorId: $this->investigadorId,
        );
    }

    /**
     * Avanza la solicitud a revisión por curaduría. Requiere que no queden datos
     * faltantes y que esté en estado EnBorrador.
     *
     * @throws \DomainException Si existen datos faltantes.
     * @throws TransicionEstadoInvalida Si no está en estado EnBorrador.
     */
    public function avanzarARevisionCuraduria(): void
    {
        if ($this->tieneDatosFaltantes()) {
            throw new \DomainException(
                'No es posible avanzar a revisión por curaduría mientras existan datos faltantes: '.implode(', ', $this->datosFaltantes)
            );
        }

        // Se envía desde un borrador nuevo o desde una solicitud devuelta para
        // corrección (subsanable), que se edita en su sitio sin perder trazabilidad.
        $puedeEnviar = $this->estado->equals(EstadoSolicitudDeposito::EnBorrador)
            || $this->estado->equals(EstadoSolicitudDeposito::RequiereCorreccion);

        if (! $puedeEnviar) {
            throw TransicionEstadoInvalida::de($this->estado->value, EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value);
        }

        $this->estado = EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria;

        $this->events[] = new SolicitudDepositoPendienteDeRevision(
            solicitudId: $this->id,
        );
    }

    // ── Decisión curatorial (aprobación documental) ──────────────

    /**
     * Registra una alerta que llega con la solicitud justificada por el investigador,
     * para que el curador la revise. Solo permitido en revisión por curaduría.
     *
     * @throws TransicionEstadoInvalida Si la solicitud no está en revisión.
     */
    public function registrarAlertaJustificada(TipoAlerta $tipo, string $justificacion): void
    {
        $this->garantizarEnRevision('registrarAlertaJustificada');

        $this->alertas[] = AlertaSolicitud::registrar(
            id: AlertaSolicitudId::generate(),
            tipo: $tipo,
            justificacionInvestigador: $justificacion,
        );
    }

    /**
     * El curador aprueba documentalmente la solicitud. Requiere que no queden alertas
     * pendientes de revisión. Asigna el Código QR del lote y, si es una donación,
     * genera el Acta de Transferencia de Dominio.
     *
     * @throws TransicionEstadoInvalida Si no está en revisión por curaduría.
     * @throws \DomainException Si quedan alertas pendientes o el curadorId está vacío.
     */
    public function aprobarDocumentalmente(string $curadorId): void
    {
        $this->garantizarEnRevision('aprobarDocumentalmente');
        $this->garantizarCuradorId($curadorId);

        if ($this->tieneAlertasPendientes()) {
            throw new \DomainException(
                'No es posible aprobar documentalmente mientras existan alertas pendientes de revisión'
            );
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoSolicitudDeposito::AprobadaDocumentalmente;
        $this->curadorResponsable = $curadorId;
        $this->aprobadaEn = $ahora;
        $this->codigoQR = CodigoQRLote::generar();

        $this->events[] = new SolicitudAprobadaDocumentalmente(
            solicitudId: $this->id,
            curadorId: $curadorId,
            ocurridoEn: $ahora,
        );

        $this->events[] = new CodigoQRAsignado(
            solicitudId: $this->id,
            codigoQR: (string) $this->codigoQR,
            ocurridoEn: $ahora,
        );

        if ($this->tipoTramite->equals(TipoTramite::Donacion)) {
            $ruta = 'actas/transferencia-dominio/'.((string) $this->id).'.pdf';
            $this->actaTransferenciaDominio = DocumentoAdjunto::of('Acta de Transferencia de Dominio', $ruta);

            $this->events[] = new ActaTransferenciaDominioGenerada(
                solicitudId: $this->id,
                ruta: $ruta,
                ocurridoEn: $ahora,
            );
        }
    }

    /**
     * El curador acepta todas las justificaciones pendientes y aprueba la solicitud.
     *
     * @throws TransicionEstadoInvalida Si no está en revisión por curaduría.
     */
    public function aceptarTodasLasJustificaciones(string $curadorId): void
    {
        $this->garantizarEnRevision('aceptarTodasLasJustificaciones');

        foreach ($this->alertas as $alerta) {
            if ($alerta->estaPendiente()) {
                $alerta->aceptar();
            }
        }

        $this->aprobarDocumentalmente($curadorId);
    }

    /**
     * El curador no acepta una o más justificaciones; la solicitud pasa a requerir
     * corrección con un comentario que indica cuáles no fueron aceptadas.
     *
     * @param  TipoAlerta[]  $rechazadas  Tipos de alerta cuya justificación se rechaza.
     *
     * @throws TransicionEstadoInvalida Si no está en revisión por curaduría.
     * @throws \DomainException Si no se indica ninguna justificación a rechazar.
     */
    public function rechazarJustificaciones(string $curadorId, array $rechazadas): void
    {
        $this->garantizarEnRevision('rechazarJustificaciones');
        $this->garantizarCuradorId($curadorId);

        if ($rechazadas === []) {
            throw new \DomainException('Debe indicarse al menos una justificación rechazada');
        }

        $nombresRechazadas = [];

        foreach ($rechazadas as $tipo) {
            foreach ($this->alertas as $alerta) {
                if ($alerta->tipo()->equals($tipo) && $alerta->estaPendiente()) {
                    $alerta->rechazar();
                    $nombresRechazadas[] = $tipo->value;
                }
            }
        }

        $ahora = new DateTimeImmutable;

        $comentario = 'Justificaciones no aceptadas: '.implode(', ', $nombresRechazadas);

        $this->estado = EstadoSolicitudDeposito::RequiereCorreccion;
        $this->curadorResponsable = $curadorId;
        $this->rechazadaEn = $ahora;
        $this->comentarioCurador = $comentario;

        $this->events[] = new SolicitudRequiereCorreccion(
            solicitudId: $this->id,
            curadorId: $curadorId,
            comentario: $comentario,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador rechaza documentalmente la solicitud indicando el motivo. El tipo de
     * rechazo determina el estado resultante (subsanable → requiere corrección;
     * definitivo → rechazo permanente).
     *
     * @throws TransicionEstadoInvalida Si no está en revisión por curaduría.
     * @throws \DomainException Si el curadorId o el motivo están vacíos.
     */
    public function rechazarDocumentalmente(string $curadorId, TipoRechazoDocumental $tipo, string $motivo): void
    {
        $this->garantizarEnRevision('rechazarDocumentalmente');
        $this->garantizarCuradorId($curadorId);

        if (trim($motivo) === '') {
            throw new \DomainException('El motivo del rechazo no puede estar vacío');
        }

        $ahora = new DateTimeImmutable;
        $estadoResultante = $tipo->estadoResultante();

        $this->estado = $estadoResultante;
        $this->curadorResponsable = $curadorId;
        $this->rechazadaEn = $ahora;
        $this->comentarioCurador = $motivo;

        $this->events[] = new SolicitudRechazadaDocumentalmente(
            solicitudId: $this->id,
            curadorId: $curadorId,
            tipoRechazo: $tipo,
            estadoResultante: $estadoResultante,
            motivo: $motivo,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador clasifica la prioridad de la solicitud en la cola de revisión.
     * Solo permitido mientras está en revisión por curaduría.
     *
     * @throws TransicionEstadoInvalida Si no está en revisión por curaduría.
     */
    public function priorizar(PrioridadSolicitud $prioridad): void
    {
        $this->garantizarEnRevision('priorizar');

        $this->prioridad = $prioridad;

        $this->events[] = new SolicitudPriorizada(
            solicitudId: $this->id,
            prioridad: $prioridad,
            ocurridoEn: new DateTimeImmutable,
        );
    }

    // ── Queries ──────────────────────────────────────────────────

    public function id(): SolicitudDepositoId
    {
        return $this->id;
    }

    public function numero(): NumeroSolicitudDeposito
    {
        return $this->numero;
    }

    public function investigadorId(): string
    {
        return $this->investigadorId;
    }

    public function tipoTramite(): string
    {
        return $this->tipoTramite->value;
    }

    public function estado(): EstadoSolicitudDeposito
    {
        return $this->estado;
    }

    public function origenRecoleccion(): ?string
    {
        return $this->origenRecoleccion;
    }

    public function situacionRegulatoria(): ?string
    {
        return $this->situacionRegulatoria;
    }

    public function provinciaOrigen(): ?string
    {
        return $this->provinciaOrigen;
    }

    public function sinDocumentacionDisponible(): bool
    {
        return $this->sinDocumentacion;
    }

    public function estaEnBorrador(): bool
    {
        return $this->estado->equals(EstadoSolicitudDeposito::EnBorrador);
    }

    public function tieneDocumentoAdjunto(string $nombre): bool
    {
        return isset($this->documentosAdjuntos[$nombre]);
    }

    public function tieneDatoFaltante(string $campo): bool
    {
        return in_array($campo, $this->datosFaltantes, true);
    }

    public function tieneDatosFaltantes(): bool
    {
        return ! empty($this->datosFaltantes);
    }

    public function nroPermisoRecoleccion(): ?string
    {
        return $this->nroPermisoRecoleccion;
    }

    public function nroPermisoMovilizacion(): ?string
    {
        return $this->nroPermisoMovilizacion;
    }

    public function grupoAnimal(): ?string
    {
        return $this->grupoAnimal;
    }

    public function localidad(): ?string
    {
        return $this->localidad;
    }

    public function origenDonacion(): ?string
    {
        return $this->origenDonacion;
    }

    public function nombreInvestigadorDocumento(): ?string
    {
        return $this->nombreInvestigadorDocumento;
    }

    public function nroIndividuos(): ?string
    {
        return $this->nroIndividuos;
    }

    public function nroMorfoespecies(): ?string
    {
        return $this->nroMorfoespecies;
    }

    public function nroLotes(): ?string
    {
        return $this->nroLotes;
    }

    public function curadorResponsable(): ?string
    {
        return $this->curadorResponsable;
    }

    public function aprobadaEn(): ?DateTimeImmutable
    {
        return $this->aprobadaEn;
    }

    public function rechazadaEn(): ?DateTimeImmutable
    {
        return $this->rechazadaEn;
    }

    public function codigoQR(): ?CodigoQRLote
    {
        return $this->codigoQR;
    }

    public function comentarioCurador(): ?string
    {
        return $this->comentarioCurador;
    }

    public function prioridad(): PrioridadSolicitud
    {
        return $this->prioridad;
    }

    public function actaTransferenciaDominio(): ?DocumentoAdjunto
    {
        return $this->actaTransferenciaDominio;
    }

    /** @return AlertaSolicitud[] */
    public function alertas(): array
    {
        return $this->alertas;
    }

    public function tieneAlertasPendientes(): bool
    {
        foreach ($this->alertas as $alerta) {
            if ($alerta->estaPendiente()) {
                return true;
            }
        }

        return false;
    }

    public function tieneAlertaJustificada(TipoAlerta $tipo): bool
    {
        foreach ($this->alertas as $alerta) {
            if ($alerta->tipo()->equals($tipo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, DocumentoAdjunto>
     */
    public function documentosAdjuntosParaPersistir(): array
    {
        return $this->documentosAdjuntos;
    }

    /** @return AlertaSolicitud[] */
    public function alertasParaPersistir(): array
    {
        return $this->alertas;
    }

    /** @return string[] */
    public function datosFaltantesParaPersistir(): array
    {
        return $this->datosFaltantes;
    }

    /** @return string[] */
    public function datosIngresadosManualmenterParaPersistir(): array
    {
        return $this->datosIngresadosManualmente;
    }

    /**
     * Extrae y vacía la cola interna de eventos. El Handler los publica tras guardar.
     *
     * @return DomainEvent[]
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /**
     * Factory de reconstitución desde persistencia. Bypasea validaciones del constructor.
     * Usar ÚNICAMENTE en repositorios Eloquent al rehidratar desde la base de datos.
     *
     * @param  array<string, DocumentoAdjunto>  $documentosAdjuntos
     * @param  string[]  $datosFaltantes
     */
    public static function reconstituir(
        SolicitudDepositoId $id,
        NumeroSolicitudDeposito $numero,
        string $investigadorId,
        TipoTramite $tipoTramite,
        EstadoSolicitudDeposito $estado,
        ?string $origenRecoleccion,
        ?string $situacionRegulatoria,
        ?string $provinciaOrigen,
        bool $sinDocumentacion,
        ?string $nroPermisoRecoleccion,
        ?string $nroPermisoMovilizacion,
        ?string $grupoAnimal,
        ?string $localidad,
        ?string $origenDonacion,
        array $documentosAdjuntos,
        array $datosFaltantes,
        ?string $nombreInvestigadorDocumento = null,
        array $datosIngresadosManualmente = [],
        ?string $nroIndividuos = null,
        ?string $nroMorfoespecies = null,
        ?string $nroLotes = null,
        ?string $curadorResponsable = null,
        ?DateTimeImmutable $aprobadaEn = null,
        ?DateTimeImmutable $rechazadaEn = null,
        ?CodigoQRLote $codigoQR = null,
        ?string $comentarioCurador = null,
        PrioridadSolicitud $prioridad = PrioridadSolicitud::Normal,
        ?DocumentoAdjunto $actaTransferenciaDominio = null,
        array $alertas = [],
    ): self {
        $solicitud = new self;

        $solicitud->id = $id;
        $solicitud->numero = $numero;
        $solicitud->investigadorId = $investigadorId;
        $solicitud->tipoTramite = $tipoTramite;
        $solicitud->estado = $estado;
        $solicitud->origenRecoleccion = $origenRecoleccion;
        $solicitud->situacionRegulatoria = $situacionRegulatoria;
        $solicitud->provinciaOrigen = $provinciaOrigen;
        $solicitud->sinDocumentacion = $sinDocumentacion;
        $solicitud->nroPermisoRecoleccion = $nroPermisoRecoleccion;
        $solicitud->nroPermisoMovilizacion = $nroPermisoMovilizacion;
        $solicitud->grupoAnimal = $grupoAnimal;
        $solicitud->localidad = $localidad;
        $solicitud->origenDonacion = $origenDonacion;
        $solicitud->documentosAdjuntos = $documentosAdjuntos;
        $solicitud->datosFaltantes = $datosFaltantes;
        $solicitud->nombreInvestigadorDocumento = $nombreInvestigadorDocumento;
        $solicitud->datosIngresadosManualmente = $datosIngresadosManualmente;
        $solicitud->nroIndividuos = $nroIndividuos;
        $solicitud->nroMorfoespecies = $nroMorfoespecies;
        $solicitud->nroLotes = $nroLotes;
        $solicitud->curadorResponsable = $curadorResponsable;
        $solicitud->aprobadaEn = $aprobadaEn;
        $solicitud->rechazadaEn = $rechazadaEn;
        $solicitud->codigoQR = $codigoQR;
        $solicitud->comentarioCurador = $comentarioCurador;
        $solicitud->prioridad = $prioridad;
        $solicitud->actaTransferenciaDominio = $actaTransferenciaDominio;
        $solicitud->alertas = $alertas;

        return $solicitud;
    }

    // ── Helpers privados ─────────────────────────────────────────

    /**
     * Garantiza que la solicitud esté en revisión por curaduría antes de aplicar una
     * decisión curatorial.
     *
     * @throws TransicionEstadoInvalida Si la solicitud no está en revisión.
     */
    private function garantizarEnRevision(string $accion): void
    {
        if (! $this->estado->equals(EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria)) {
            throw TransicionEstadoInvalida::de($this->estado->value, $accion);
        }
    }

    private function garantizarCuradorId(string $curadorId): void
    {
        if (trim($curadorId) === '') {
            throw new \DomainException('El curadorId no puede estar vacío');
        }
    }

    private function asignarCampoPorNombre(string $campo, string $valor): void
    {
        match ($campo) {
            'Grupo Animal' => $this->grupoAnimal = $valor,
            'N.º Permiso Recolección', 'N.º Investigación' => $this->nroPermisoRecoleccion = $valor,
            'N.º Permiso Movilización' => $this->nroPermisoMovilizacion = $valor,
            'Provincia', 'Administración Política' => $this->provinciaOrigen = $valor,
            'Localidad' => $this->localidad = $valor,
            'N.º Individuos' => $this->nroIndividuos = $valor,
            'N.º Morfoespecies' => $this->nroMorfoespecies = $valor,
            'N.º Lotes' => $this->nroLotes = $valor,
            default => throw new \DomainException(
                sprintf('El campo "%s" no es un campo de datos conocido de la solicitud', $campo)
            ),
        };
    }
}
