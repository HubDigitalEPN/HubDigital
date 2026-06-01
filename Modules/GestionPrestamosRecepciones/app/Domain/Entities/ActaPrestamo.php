<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDevueltaPorFirmaInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaDigitalmente;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaSubida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;
use Modules\GestionPrestamosRecepciones\Domain\Events\DocumentoExportacionSubido;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;

final class ActaPrestamo
{
    /** @var list<object> */
    private array $events = [];

    private function __construct(
        private readonly ActaPrestamoId $id,
        private readonly NumeroPrestamo $numeroPrestamo,
        private readonly SolicitudPrestamoId $solicitudPrestamoId,
        private EstadoActa $estado,
        private readonly TipoPrestamo $tipoPrestamo,
        private readonly AlcancePrestamo $alcancePrestamo,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly DateTimeImmutable $fechaFin,
        private readonly string $pdfRuta,
        private ?string $condicionesGenerales,
        private ?string $pdfFirmadoRuta,
        private ?string $documentoIdentidadRuta,
        private ?string $documentoExportacionRuta,
        private ?string $motivoDevolucion,
        private ?DateTimeImmutable $firmadaSubidaEn,
        private ?DateTimeImmutable $validadaEn,
        private ?string $validadaPor,
    ) {}

    // ── Named constructors ────────────────────────────────────────────────────

    public static function emitir(
        ActaPrestamoId $id,
        NumeroPrestamo $numeroPrestamo,
        SolicitudPrestamoId $solicitudPrestamoId,
        TipoPrestamo $tipoPrestamo,
        AlcancePrestamo $alcancePrestamo,
        DateTimeImmutable $fechaInicio,
        DateTimeImmutable $fechaFin,
        string $pdfRuta,
        ?string $condicionesGenerales = null,
    ): self {
        if (trim($pdfRuta) === '') {
            throw new InvalidArgumentException('La ruta del PDF del acta no puede estar vacía.');
        }

        if ($fechaFin <= $fechaInicio) {
            throw new InvalidArgumentException('La fecha de fin debe ser posterior a la fecha de inicio.');
        }

        return new self(
            id: $id,
            numeroPrestamo: $numeroPrestamo,
            solicitudPrestamoId: $solicitudPrestamoId,
            estado: EstadoActa::PendienteEnvio,
            tipoPrestamo: $tipoPrestamo,
            alcancePrestamo: $alcancePrestamo,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
            condicionesGenerales: $condicionesGenerales,
            pdfFirmadoRuta: null,
            documentoIdentidadRuta: null,
            documentoExportacionRuta: null,
            motivoDevolucion: null,
            firmadaSubidaEn: null,
            validadaEn: null,
            validadaPor: null,
        );
    }

    /**
     * Reconstitución desde persistencia — no registra eventos de dominio.
     */
    public static function reconstituir(
        ActaPrestamoId $id,
        NumeroPrestamo $numeroPrestamo,
        SolicitudPrestamoId $solicitudPrestamoId,
        EstadoActa $estado,
        TipoPrestamo $tipoPrestamo,
        AlcancePrestamo $alcancePrestamo,
        DateTimeImmutable $fechaInicio,
        DateTimeImmutable $fechaFin,
        string $pdfRuta,
        ?string $condicionesGenerales,
        ?string $pdfFirmadoRuta,
        ?string $documentoIdentidadRuta,
        ?string $documentoExportacionRuta,
        ?string $motivoDevolucion,
        ?DateTimeImmutable $firmadaSubidaEn,
        ?DateTimeImmutable $validadaEn,
        ?string $validadaPor,
    ): self {
        return new self(
            id: $id,
            numeroPrestamo: $numeroPrestamo,
            solicitudPrestamoId: $solicitudPrestamoId,
            estado: $estado,
            tipoPrestamo: $tipoPrestamo,
            alcancePrestamo: $alcancePrestamo,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
            condicionesGenerales: $condicionesGenerales,
            pdfFirmadoRuta: $pdfFirmadoRuta,
            documentoIdentidadRuta: $documentoIdentidadRuta,
            documentoExportacionRuta: $documentoExportacionRuta,
            motivoDevolucion: $motivoDevolucion,
            firmadaSubidaEn: $firmadaSubidaEn,
            validadaEn: $validadaEn,
            validadaPor: $validadaPor,
        );
    }

    // ── Métodos de negocio ────────────────────────────────────────────────────

    /**
     * Marca el acta como enviada al investigador para su firma.
     */
    public function marcarEnviada(string $investigadorId): void
    {
        if (! $this->estado->equals(EstadoActa::PendienteEnvio)) {
            throw TransicionDeEstadoInvalidaException::para(
                'ActaPrestamo',
                $this->estado->value,
                'marcarEnviada',
            );
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::PendienteFirma;

        $this->events[] = new ActaEnviada(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            investigadorId: $investigadorId,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El investigador sube el acta firmada y su documento de identidad.
     * Solo permitido desde PendienteFirma.
     */
    public function subirFirma(string $pdfFirmadoRuta, string $documentoIdentidadRuta): void
    {
        if (! $this->estado->equals(EstadoActa::PendienteFirma)) {
            throw TransicionDeEstadoInvalidaException::para(
                'ActaPrestamo',
                $this->estado->value,
                'subirFirma',
            );
        }

        if (trim($pdfFirmadoRuta) === '') {
            throw new InvalidArgumentException('La ruta del PDF firmado no puede estar vacía.');
        }

        if (trim($documentoIdentidadRuta) === '') {
            throw new InvalidArgumentException('La ruta del documento de identidad no puede estar vacía.');
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::PendienteValidacion;
        $this->pdfFirmadoRuta = trim($pdfFirmadoRuta);
        $this->documentoIdentidadRuta = trim($documentoIdentidadRuta);
        $this->firmadaSubidaEn = $ahora;

        $this->events[] = new ActaFirmadaSubida(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            pdfFirmadoRuta: $this->pdfFirmadoRuta,
            documentoIdentidadRuta: $this->documentoIdentidadRuta,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El investigador firma el acta digitalmente mediante canvas.
     * Solo permitido desde PendienteFirma.
     */
    /**
     * El investigador firma el acta digitalmente mediante canvas.
     * Solo guarda la imagen de la firma; el estado permanece en PendienteFirma
     * hasta que el investigador suba su documento de identidad.
     */
    public function firmarDigitalmente(string $firmaImagenRuta): void
    {
        if (! $this->estado->equals(EstadoActa::PendienteFirma)) {
            throw TransicionDeEstadoInvalidaException::para(
                'ActaPrestamo',
                $this->estado->value,
                'firmarDigitalmente',
            );
        }

        if (trim($firmaImagenRuta) === '') {
            throw new InvalidArgumentException('La ruta de la imagen de firma no puede estar vacía.');
        }

        $ahora = new DateTimeImmutable;

        // No se cambia el estado: sigue en PendienteFirma hasta subir documento de identidad.
        $this->pdfFirmadoRuta = trim($firmaImagenRuta);

        $this->events[] = new ActaFirmadaDigitalmente(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            pdfFirmadoRuta: $this->pdfFirmadoRuta,
            ocurridoEn: $ahora,
        );
    }

    /**
     * Completa la firma digital subiendo el documento de identidad.
     * Solo permitido desde PendienteFirma cuando ya existe firma digital.
     */
    public function completarFirmaDigitalConIdentidad(string $documentoIdentidadRuta): void
    {
        if (! $this->estado->equals(EstadoActa::PendienteFirma)) {
            throw TransicionDeEstadoInvalidaException::para(
                'ActaPrestamo',
                $this->estado->value,
                'completarFirmaDigitalConIdentidad',
            );
        }

        if ($this->pdfFirmadoRuta === null) {
            throw new InvalidArgumentException('Debe existir una firma digital antes de subir el documento de identidad.');
        }

        if (trim($documentoIdentidadRuta) === '') {
            throw new InvalidArgumentException('La ruta del documento de identidad no puede estar vacía.');
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::PendienteValidacion;
        $this->documentoIdentidadRuta = trim($documentoIdentidadRuta);
        $this->firmadaSubidaEn = $ahora;

        $this->events[] = new ActaFirmadaSubida(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            pdfFirmadoRuta: $this->pdfFirmadoRuta,
            documentoIdentidadRuta: $this->documentoIdentidadRuta,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador devuelve el acta por firma inválida.
     * Solo permitido desde PendienteValidacion.
     */
    public function devolver(string $investigadorId, string $motivo): void
    {
        if (! $this->estado->equals(EstadoActa::PendienteValidacion)) {
            throw TransicionDeEstadoInvalidaException::para(
                'ActaPrestamo',
                $this->estado->value,
                'devolver',
            );
        }

        if (trim($motivo) === '') {
            throw new InvalidArgumentException('El motivo de devolución no puede estar vacío.');
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::PendienteFirma;
        $this->pdfFirmadoRuta = null;
        $this->documentoIdentidadRuta = null;
        $this->motivoDevolucion = trim($motivo);

        $this->events[] = new ActaDevueltaPorFirmaInvalida(
            actaId: $this->id,
            investigadorId: $investigadorId,
            motivo: trim($motivo),
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador adjunta el documento de exportación del Ministerio del Ambiente.
     * Solo aplica para actas de alcance Internacional.
     */
    public function adjuntarDocumentoExportacion(string $ruta): void
    {
        if (! $this->alcancePrestamo->esInternacional()) {
            throw new \DomainException('Solo préstamos internacionales requieren documento de exportación.');
        }

        if (trim($ruta) === '') {
            throw new InvalidArgumentException('La ruta del documento de exportación no puede estar vacía.');
        }

        $this->documentoExportacionRuta = trim($ruta);

        $this->events[] = new DocumentoExportacionSubido(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            ocurridoEn: new DateTimeImmutable,
        );
    }

    /**
     * El curador valida el acta firmada.
     * Solo permitido desde PendienteValidacion.
     */
    public function validar(string $validadoPor): void
    {
        if (! $this->estado->equals(EstadoActa::PendienteValidacion)) {
            throw TransicionDeEstadoInvalidaException::para(
                'ActaPrestamo',
                $this->estado->value,
                'validar',
            );
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::Validada;
        $this->validadaEn = $ahora;
        $this->validadaPor = $validadoPor;

        $this->events[] = new ActaValidada(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            validadoPor: $validadoPor,
            ocurridoEn: $ahora,
        );
    }

    /**
     * Retorna y vacía los eventos de dominio registrados en esta instancia.
     *
     * @return list<object>
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function id(): ActaPrestamoId
    {
        return $this->id;
    }

    public function numeroPrestamo(): NumeroPrestamo
    {
        return $this->numeroPrestamo;
    }

    public function solicitudPrestamoId(): SolicitudPrestamoId
    {
        return $this->solicitudPrestamoId;
    }

    public function estado(): EstadoActa
    {
        return $this->estado;
    }

    public function tipoPrestamo(): TipoPrestamo
    {
        return $this->tipoPrestamo;
    }

    public function alcancePrestamo(): AlcancePrestamo
    {
        return $this->alcancePrestamo;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function pdfRuta(): string
    {
        return $this->pdfRuta;
    }

    public function condicionesGenerales(): ?string
    {
        return $this->condicionesGenerales;
    }

    public function pdfFirmadoRuta(): ?string
    {
        return $this->pdfFirmadoRuta;
    }

    public function documentoIdentidadRuta(): ?string
    {
        return $this->documentoIdentidadRuta;
    }

    public function documentoExportacionRuta(): ?string
    {
        return $this->documentoExportacionRuta;
    }

    public function motivoDevolucion(): ?string
    {
        return $this->motivoDevolucion;
    }

    public function firmadaSubidaEn(): ?DateTimeImmutable
    {
        return $this->firmadaSubidaEn;
    }

    public function validadaEn(): ?DateTimeImmutable
    {
        return $this->validadaEn;
    }

    public function validadaPor(): ?string
    {
        return $this->validadaPor;
    }
}
