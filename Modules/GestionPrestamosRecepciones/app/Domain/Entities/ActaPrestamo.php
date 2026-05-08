<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDevueltaPorFirmaInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaSubida;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
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
        private readonly DateTimeImmutable $fechaInicio,
        private readonly DateTimeImmutable $fechaFin,
        private readonly string $pdfRuta,
        private ?string $pdfFirmadoRuta,
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
        DateTimeImmutable $fechaInicio,
        DateTimeImmutable $fechaFin,
        string $pdfRuta,
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
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
            pdfFirmadoRuta: null,
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
        DateTimeImmutable $fechaInicio,
        DateTimeImmutable $fechaFin,
        string $pdfRuta,
        ?string $pdfFirmadoRuta,
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
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            pdfRuta: $pdfRuta,
            pdfFirmadoRuta: $pdfFirmadoRuta,
            firmadaSubidaEn: $firmadaSubidaEn,
            validadaEn: $validadaEn,
            validadaPor: $validadaPor,
        );
    }

    // ── Métodos de negocio ────────────────────────────────────────────────────

    /**
     * Marca el acta como enviada al investigador para su firma.
     */
    public function marcarEnviada(): void
    {
        if (! $this->estado->equals(EstadoActa::PendienteEnvio)) {
            throw TransicionDeEstadoInvalidaException::para(
                'ActaPrestamo',
                $this->estado->value,
                'marcarEnviada',
            );
        }

        $this->estado = EstadoActa::PendienteFirma;
    }

    /**
     * El investigador sube el acta firmada.
     * Solo permitido desde PendienteFirma.
     */
    public function subirFirma(string $pdfFirmadoRuta): void
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

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::PendienteValidacion;
        $this->pdfFirmadoRuta = trim($pdfFirmadoRuta);
        $this->firmadaSubidaEn = $ahora;

        $this->events[] = new ActaFirmadaSubida(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            pdfFirmadoRuta: $this->pdfFirmadoRuta,
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

        $this->events[] = new ActaDevueltaPorFirmaInvalida(
            actaId: $this->id,
            investigadorId: $investigadorId,
            motivo: trim($motivo),
            ocurridoEn: $ahora,
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

    public function pdfFirmadoRuta(): ?string
    {
        return $this->pdfFirmadoRuta;
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
