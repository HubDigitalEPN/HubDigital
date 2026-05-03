<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use Modules\GestionPrestamosRecepciones\Domain\Events\DatoFaltanteCompletado;
use Modules\GestionPrestamosRecepciones\Domain\Events\DocumentacionOficialCargada;
use Modules\GestionPrestamosRecepciones\Domain\Events\DomainEvent;
use Modules\GestionPrestamosRecepciones\Domain\Events\IntervencionCuratoriaSolicitada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudDepositoCreada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SolicitudDepositoPendienteDeRevision;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\DocumentacionInsuficiente;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionEstadoInvalida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DocumentoAdjunto;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

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

    // ── Datos integrados de documentación oficial ────────────────

    private ?string $nroPermisoRecoleccion = null;

    private ?string $nroPermisoMovilizacion = null;

    private ?string $grupoAnimal = null;

    private ?string $localidad = null;

    private ?string $origenDonacion = null;

    // ── Cola interna de eventos de dominio ───────────────────────

    /** @var DomainEvent[] */
    private array $events = [];

    // ── Constructor ──────────────────────────────────────────────

    private function __construct() {}

    // ── Factory Method ───────────────────────────────────────────

    public static function crear(
        SolicitudDepositoId $id,
        string $investigadorId,
        string $tipoTramite,
    ): self {
        if (trim($investigadorId) === '') {
            throw new \DomainException('El investigadorId no puede estar vacío');
        }

        $solicitud = new self;
        $solicitud->id = $id;
        $solicitud->numero = NumeroSolicitudDeposito::generate();
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
    public function integrarDatosDeDocumentos(DatosIntegradosDocumento $datos, array $nombresDocumentos): void
    {
        if ($datos->nroPermisoRecoleccion !== null) {
            $this->nroPermisoRecoleccion = $datos->nroPermisoRecoleccion;
        }

        if ($datos->nroPermisoMovilizacion !== null) {
            $this->nroPermisoMovilizacion = $datos->nroPermisoMovilizacion;
        }

        if ($datos->grupoAnimal !== null) {
            $this->grupoAnimal = $datos->grupoAnimal;
        } elseif ($this->tipoTramite->equals(TipoTramite::Deposito)) {
            $this->marcarDatoComoFaltante('Grupo Animal');
        }

        if ($datos->provinciaOrigen !== null) {
            $this->provinciaOrigen = $datos->provinciaOrigen;
        }

        if ($datos->localidad !== null) {
            $this->localidad = $datos->localidad;
        }

        if ($datos->origenDonacion !== null) {
            $this->origenDonacion = $datos->origenDonacion;
        }

        $this->events[] = new DocumentacionOficialCargada(
            solicitudId: $this->id,
            nombresDocumentos: $nombresDocumentos,
        );

        if (! $this->tieneDatosFaltantes()) {
            $this->avanzarARevisionCuraduria();
        }
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

        $this->events[] = new DatoFaltanteCompletado(
            solicitudId: $this->id,
            campo: $campo,
            valor: $valor,
        );

        if (! $this->tieneDatosFaltantes()) {
            $this->avanzarARevisionCuraduria();
        }
    }

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

    public function avanzarARevisionCuraduria(): void
    {
        if ($this->tieneDatosFaltantes()) {
            throw new \DomainException(
                'No es posible avanzar a revisión por curaduría mientras existan datos faltantes: '.implode(', ', $this->datosFaltantes)
            );
        }

        if (! $this->estado->equals(EstadoSolicitudDeposito::EnBorrador)) {
            throw TransicionEstadoInvalida::de($this->estado->value, EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value);
        }

        $this->estado = EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria;

        $this->events[] = new SolicitudDepositoPendienteDeRevision(
            solicitudId: $this->id,
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

    /**
     * @return array<string, DocumentoAdjunto>
     */
    public function documentosAdjuntosParaPersistir(): array
    {
        return $this->documentosAdjuntos;
    }

    /** @return string[] */
    public function datosFaltantesParaPersistir(): array
    {
        return $this->datosFaltantes;
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

        return $solicitud;
    }

    // ── Helpers privados ─────────────────────────────────────────

    private function asignarCampoPorNombre(string $campo, string $valor): void
    {
        match ($campo) {
            'Grupo Animal' => $this->grupoAnimal = $valor,
            'N.º Permiso Recolección' => $this->nroPermisoRecoleccion = $valor,
            'N.º Permiso Movilización' => $this->nroPermisoMovilizacion = $valor,
            'Provincia' => $this->provinciaOrigen = $valor,
            'Localidad' => $this->localidad = $valor,
            default => throw new \DomainException(
                sprintf('El campo "%s" no es un campo de datos conocido de la solicitud', $campo)
            ),
        };
    }
}
