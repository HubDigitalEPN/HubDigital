<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDevueltaPorFirmaInvalida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaEnviada;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaDigitalmente;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaPorCurador;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaFirmadaSubida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaValidada;
use Modules\GestionPrestamosRecepciones\Domain\Events\DocumentoExportacionSubido;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionDeEstadoInvalidaException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AlcancePrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoPrestamo;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoActa;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudPrestamoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoPrestamo;

/**
 * Agregado raíz que representa el acta de préstamo: el documento formal que el
 * investigador debe firmar y el curador validar antes de activar el préstamo.
 *
 * Gestiona el ciclo de vida del acta mediante una máquina de estados (ver
 * {@see EstadoActa}): PendienteEnvio → PendienteFirma → PendienteValidacion →
 * Validada, con posibilidad de devolución por firma inválida. Soporta tanto la
 * firma por subida de PDF ({@see subirFirma()}) como la firma digital en canvas
 * ({@see firmarDigitalmente()} + {@see completarFirmaDigitalConIdentidad()}). Las
 * actas internacionales requieren además el documento de exportación del MAE.
 *
 * Construir vía {@see self::emitir()}; rehidratar desde persistencia vía
 * {@see self::reconstituir()} (sin emitir eventos).
 */
final class ActaPrestamo
{
    /** @var list<object> Eventos de dominio acumulados, drenados con {@see pullEvents()}. */
    private array $events = [];

    private function __construct(
        private readonly ActaPrestamoId $id,
        private readonly CodigoPrestamo $codigoPrestamo,
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
        private ?string $pdfFirmadoCuradorRuta = null,
    ) {}

    // ── Named constructors ────────────────────────────────────────────────────

    /**
     * Emite un acta nueva en estado PendienteEnvio a partir de una solicitud aprobada.
     *
     * @throws InvalidArgumentException Si la ruta del PDF está vacía o la fecha de fin no es posterior a la de inicio.
     */
    public static function emitir(
        ActaPrestamoId $id,
        CodigoPrestamo $codigoPrestamo,
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
            codigoPrestamo: $codigoPrestamo,
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
        CodigoPrestamo $codigoPrestamo,
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
        ?string $pdfFirmadoCuradorRuta = null,
    ): self {
        return new self(
            id: $id,
            codigoPrestamo: $codigoPrestamo,
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
            pdfFirmadoCuradorRuta: $pdfFirmadoCuradorRuta,
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
     *
     * Si el curador devolvió solo el acta (la identidad sigue válida), se puede
     * omitir el documento de identidad pasando null; se conserva el ya cargado.
     */
    public function subirFirma(string $pdfFirmadoRuta, ?string $documentoIdentidadRuta = null): void
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

        if ($documentoIdentidadRuta !== null && trim($documentoIdentidadRuta) !== '') {
            $this->documentoIdentidadRuta = trim($documentoIdentidadRuta);
        }

        if ($this->documentoIdentidadRuta === null) {
            throw new InvalidArgumentException('La ruta del documento de identidad no puede estar vacía.');
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::PendienteValidacion;
        $this->pdfFirmadoRuta = trim($pdfFirmadoRuta);
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
     * Solo guarda la imagen de la firma; el estado permanece en PendienteFirma
     * hasta que el investigador suba su documento de identidad. Solo permitido
     * desde PendienteFirma.
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

        $this->pdfFirmadoRuta = trim($firmaImagenRuta);

        $this->events[] = new ActaFirmadaDigitalmente(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            pdfFirmadoRuta: $this->pdfFirmadoRuta,
            ocurridoEn: $ahora,
        );

        // Si la identidad ya estaba validada (devolución de solo el acta), la firma
        // completa el acta. Si no, sigue en PendienteFirma hasta subir la identidad.
        if ($this->documentoIdentidadRuta !== null) {
            $this->estado = EstadoActa::PendienteValidacion;
            $this->firmadaSubidaEn = $ahora;

            $this->events[] = new ActaFirmadaSubida(
                actaId: $this->id,
                solicitudId: $this->solicitudPrestamoId,
                pdfFirmadoRuta: $this->pdfFirmadoRuta,
                documentoIdentidadRuta: $this->documentoIdentidadRuta,
                ocurridoEn: $ahora,
            );
        }
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
     *
     * Puede devolver ambos documentos o solo uno: el acta firmada y/o el
     * documento de identidad. El documento no devuelto se conserva válido y el
     * investigador solo debe volver a cargar el devuelto. Cuál se devolvió se
     * deriva de qué ruta queda en null (ver máquina de estados en la cabecera).
     */
    public function devolver(
        string $investigadorId,
        string $motivo,
        bool $devolverActa = true,
        bool $devolverIdentidad = true,
    ): void {
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

        if (! $devolverActa && ! $devolverIdentidad) {
            throw new InvalidArgumentException('Debe devolver al menos un documento.');
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::PendienteFirma;
        if ($devolverActa) {
            $this->pdfFirmadoRuta = null;
        }
        if ($devolverIdentidad) {
            $this->documentoIdentidadRuta = null;
        }
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
     * El curador valida el acta adjuntando su propia firma al documento que ya firmó
     * el investigador. La firma llega como un PDF ya producido fuera del dominio: bien
     * regenerado desde la plantilla con la firma dibujada del curador (canvas), bien
     * cargado por el curador (archivo firmado). Solo permitido desde PendienteValidacion.
     *
     * Emite {@see ActaValidada} (para iniciar el préstamo) y
     * {@see ActaFirmadaPorCurador} (para el historial).
     */
    public function validarConFirmaCurador(
        string $curadorId,
        string $pdfFirmadoCuradorRuta,
    ): void {
        if (! $this->estado->equals(EstadoActa::PendienteValidacion)) {
            throw TransicionDeEstadoInvalidaException::para(
                'ActaPrestamo',
                $this->estado->value,
                'validarConFirmaCurador',
            );
        }

        if (trim($pdfFirmadoCuradorRuta) === '') {
            throw new InvalidArgumentException('La ruta del PDF firmado por el curador no puede estar vacía.');
        }

        $ahora = new DateTimeImmutable;

        $this->estado = EstadoActa::Validada;
        $this->validadaEn = $ahora;
        $this->validadaPor = $curadorId;
        $this->pdfFirmadoCuradorRuta = trim($pdfFirmadoCuradorRuta);

        $this->events[] = new ActaValidada(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            validadoPor: $curadorId,
            ocurridoEn: $ahora,
        );

        $this->events[] = new ActaFirmadaPorCurador(
            actaId: $this->id,
            solicitudId: $this->solicitudPrestamoId,
            curadorId: $curadorId,
            pdfFirmadoCuradorRuta: $this->pdfFirmadoCuradorRuta,
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

    public function codigoPrestamo(): CodigoPrestamo
    {
        return $this->codigoPrestamo;
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

    public function pdfFirmadoCuradorRuta(): ?string
    {
        return $this->pdfFirmadoCuradorRuta;
    }
}
