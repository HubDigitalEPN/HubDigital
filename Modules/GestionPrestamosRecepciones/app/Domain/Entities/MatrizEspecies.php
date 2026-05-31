<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use Modules\GestionPrestamosRecepciones\Domain\Events\DomainEvent;
use Modules\GestionPrestamosRecepciones\Domain\Events\HallazgoTaxonomicoJustificado;
use Modules\GestionPrestamosRecepciones\Domain\Events\JustificacionTaxonomicaCambiada;
use Modules\GestionPrestamosRecepciones\Domain\Events\MatrizEspeciesCargada;
use Modules\GestionPrestamosRecepciones\Domain\Events\MatrizValidadaTecnicamente;
use Modules\GestionPrestamosRecepciones\Domain\Events\SugerenciaTaxonomicaAceptada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SugerenciaTaxonomicaRevertida;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\CamposDwCFaltantesException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\RegistroEspecimenNoEncontradoException;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RegistroEspecimenId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

final class MatrizEspecies
{
    // ── Identidad ──────────────────────────────────���─────────────

    private MatrizEspeciesId $id;

    // ── Datos del trámite ────────────────────────────────────────

    private string $solicitudId;

    private TipoTramite $tipoTramite;

    private EstadoMatrizEspecies $estado;

    // ── Campos Darwin Core ───────────────────────────────────────

    /** @var array<string, mixed> */
    private array $camposDwCPresentes;

    // ── Registros de especímenes (entidades locales) ─────────────

    /** @var array<string, RegistroEspecimen> */
    private array $registros = [];

    // ── Flags de comportamiento ──────────────────────────────────

    private bool $identificacionOriginalConservada = false;

    // ── Cola interna de eventos de dominio ───────────────────────

    /** @var DomainEvent[] */
    private array $events = [];

    // ── Constructor ──────────────────────────────────────────────

    private function __construct() {}

    // ── Factory Method ───────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $camposDwCPresentes
     */
    public static function crear(
        MatrizEspeciesId $id,
        string $solicitudId,
        array $camposDwCPresentes,
        string $tipoTramite,
    ): self {
        if (trim($solicitudId) === '') {
            throw new \DomainException('El ID de la solicitud no puede estar vacío');
        }

        $matriz = new self;
        $matriz->id = $id;
        $matriz->solicitudId = $solicitudId;
        $matriz->camposDwCPresentes = $camposDwCPresentes;
        $matriz->tipoTramite = TipoTramite::from($tipoTramite);

        if ($matriz->tipoTramite->equals(TipoTramite::Donacion)) {
            $matriz->estado = EstadoMatrizEspecies::ValidadaTecnicamente;
            $matriz->identificacionOriginalConservada = true;
        } else {
            $matriz->estado = EstadoMatrizEspecies::Pendiente;
        }

        $matriz->events[] = new MatrizEspeciesCargada(
            matrizId: $id,
            solicitudId: $solicitudId,
            tipoTramite: $tipoTramite,
        );

        return $matriz;
    }

    // ── Métodos de Negocio ───────────────────────────────────────

    /**
     * Valida que la matriz contenga todos los campos DwC exigidos por el catálogo.
     * Lanza excepción si falta alguno. No muta estado — es un guard.
     *
     * @param  string[]  $camposExigidosPorCatalogo
     */
    public function validarCamposDwC(array $camposExigidosPorCatalogo): void
    {
        $faltantes = array_filter(
            $camposExigidosPorCatalogo,
            fn (string $campo) => ! array_key_exists($campo, $this->camposDwCPresentes)
        );

        if (! empty($faltantes)) {
            throw CamposDwCFaltantesException::porCamposFaltantes(array_values($faltantes));
        }
    }

    /**
     * Agrega un registro de espécimen a la matriz.
     * Para Donaciones, los registros inician como ValidadoTecnicamente.
     * Para Depósitos, los registros inician como Pendiente.
     */
    public function agregarRegistroEspecimen(string $nombreCientifico, bool $noCatalogado = false): string
    {
        $estadoInicial = $this->tipoTramite->equals(TipoTramite::Donacion)
            ? EstadoRegistroEspecimen::ValidadoTecnicamente
            : EstadoRegistroEspecimen::Pendiente;

        $registroId = RegistroEspecimenId::generate();

        $registro = RegistroEspecimen::crear(
            id: $registroId,
            nombreCientifico: $nombreCientifico,
            noCatalogado: $noCatalogado,
            estadoInicial: $estadoInicial,
        );

        $this->registros[(string) $registroId] = $registro;

        return (string) $registroId;
    }

    /**
     * Acepta una sugerencia de corrección tipográfica para un registro.
     * Solo aplica a solicitudes de tipo Depósito.
     */
    public function aceptarSugerencia(string $registroId, string $especieCorregida): void
    {
        if ($this->tipoTramite->equals(TipoTramite::Donacion)) {
            throw new \DomainException(
                'No se permite la corrección tipográfica en solicitudes de tipo Donación'
            );
        }

        $registro = $this->obtenerRegistroOFallar($registroId);
        $especieOriginal = $registro->nombreCientifico();

        $registro->aceptarCorreccion($especieCorregida);

        $this->events[] = new SugerenciaTaxonomicaAceptada(
            matrizId: $this->id,
            registroId: $registroId,
            especieOriginal: $especieOriginal,
            especieCorregida: $especieCorregida,
        );

        $this->recalcularEstadoMatriz();
    }

    /**
     * Justifica un hallazgo taxonómico no catalogado.
     * Transiciona el registro a ValidacionManualPorCuraduria y la matriz a CargadaConAlertas.
     */
    public function justificarRegistro(string $registroId, string $motivoJustificacion): void
    {
        $registro = $this->obtenerRegistroOFallar($registroId);

        $registro->justificar($motivoJustificacion);

        $this->estado = EstadoMatrizEspecies::CargadaConAlertas;

        $this->events[] = new HallazgoTaxonomicoJustificado(
            matrizId: $this->id,
            registroId: $registroId,
            especie: $registro->nombreCientifico(),
            motivoJustificacion: $motivoJustificacion,
        );
    }

    /**
     * Marca un registro como no catalogado tras la validación taxonómica.
     */
    public function marcarRegistroNoCatalogado(string $registroId): void
    {
        $registro = $this->obtenerRegistroOFallar($registroId);
        $registro->marcarComoNoCatalogado();
    }

    /**
     * Revierte una sugerencia de corrección tipográfica previamente aceptada.
     * Solo aplica a solicitudes de tipo Depósito.
     */
    public function revertirSugerencia(string $registroId): void
    {
        if ($this->tipoTramite->equals(TipoTramite::Donacion)) {
            throw new \DomainException(
                'No se permite revertir correcciones en solicitudes de tipo Donación'
            );
        }

        $registro = $this->obtenerRegistroOFallar($registroId);
        $especieOriginal = $registro->nombreCientifico();

        $registro->revertirCorreccion();

        $this->events[] = new SugerenciaTaxonomicaRevertida(
            matrizId: $this->id,
            registroId: $registroId,
            especieOriginal: $especieOriginal,
        );

        $this->recalcularEstadoMatriz();
    }

    /**
     * Cambia el motivo de justificación de un registro ya justificado.
     */
    public function cambiarJustificacionRegistro(string $registroId, string $nuevoMotivo): void
    {
        $registro = $this->obtenerRegistroOFallar($registroId);

        $registro->cambiarJustificacion($nuevoMotivo);

        $this->events[] = new JustificacionTaxonomicaCambiada(
            matrizId: $this->id,
            registroId: $registroId,
            especie: $registro->nombreCientifico(),
            nuevoMotivo: $nuevoMotivo,
        );
    }

    // ── Queries ──────────────────────────────────────────────────

    public function id(): MatrizEspeciesId
    {
        return $this->id;
    }

    public function solicitudId(): string
    {
        return $this->solicitudId;
    }

    public function tipoTramite(): string
    {
        return $this->tipoTramite->value;
    }

    public function estado(): EstadoMatrizEspecies
    {
        return $this->estado;
    }

    public function estadoRegistro(string $registroId): EstadoRegistroEspecimen
    {
        return $this->obtenerRegistroOFallar($registroId)->estado();
    }

    public function contieneEspecimen(string $nombreCientifico): bool
    {
        foreach ($this->registros as $registro) {
            if ($registro->nombreCientifico() === $nombreCientifico) {
                return true;
            }
        }

        return false;
    }

    public function especimenEsNoCatalogado(string $registroId): bool
    {
        return $this->obtenerRegistroOFallar($registroId)->esNoCatalogado();
    }

    /**
     * Retorna los IDs de registros no catalogados que aún no han sido justificados.
     *
     * @return string[]
     */
    public function registrosPendientesDeJustificacion(): array
    {
        $pendientes = [];

        foreach ($this->registros as $id => $registro) {
            if ($registro->esNoCatalogado() && $registro->motivoJustificacion() === null) {
                $pendientes[] = $id;
            }
        }

        return $pendientes;
    }

    public function todosLosHallazgosJustificados(): bool
    {
        return empty($this->registrosPendientesDeJustificacion());
    }

    public function identificacionOriginalConservada(): bool
    {
        return $this->identificacionOriginalConservada;
    }

    /**
     * @return array<string, RegistroEspecimen>
     */
    public function registros(): array
    {
        return $this->registros;
    }

    /**
     * @return array<string, mixed>
     */
    public function camposDwCPresentes(): array
    {
        return $this->camposDwCPresentes;
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

    // ── Reconstitución desde persistencia ────────────────────────

    /**
     * @param  array<string, mixed>  $camposDwCPresentes
     * @param  array<string, RegistroEspecimen>  $registros
     */
    public static function reconstituir(
        MatrizEspeciesId $id,
        string $solicitudId,
        TipoTramite $tipoTramite,
        EstadoMatrizEspecies $estado,
        array $camposDwCPresentes,
        array $registros,
        bool $identificacionOriginalConservada,
    ): self {
        $matriz = new self;
        $matriz->id = $id;
        $matriz->solicitudId = $solicitudId;
        $matriz->tipoTramite = $tipoTramite;
        $matriz->estado = $estado;
        $matriz->camposDwCPresentes = $camposDwCPresentes;
        $matriz->registros = $registros;
        $matriz->identificacionOriginalConservada = $identificacionOriginalConservada;

        return $matriz;
    }

    // ── Helpers privados ─────────────────────────────────────────

    private function obtenerRegistroOFallar(string $registroId): RegistroEspecimen
    {
        if (! isset($this->registros[$registroId])) {
            throw RegistroEspecimenNoEncontradoException::conId($registroId);
        }

        return $this->registros[$registroId];
    }

    /**
     * Recalcula el estado de la matriz basándose en el estado de todos sus registros.
     * Si todos los registros están validados, la matriz transiciona a ValidadaTecnicamente.
     */
    private function recalcularEstadoMatriz(): void
    {
        $todosValidados = true;

        foreach ($this->registros as $registro) {
            if (! $registro->estado()->equals(EstadoRegistroEspecimen::ValidadoTecnicamente)
                && ! $registro->estado()->equals(EstadoRegistroEspecimen::ValidacionManualPorCuraduria)) {
                $todosValidados = false;
                break;
            }
        }

        if ($todosValidados && ! $this->estado->equals(EstadoMatrizEspecies::ValidadaTecnicamente)) {
            $this->estado = EstadoMatrizEspecies::ValidadaTecnicamente;

            $this->events[] = new MatrizValidadaTecnicamente(
                matrizId: $this->id,
                solicitudId: $this->solicitudId,
            );
        } elseif (! $todosValidados && $this->estado->equals(EstadoMatrizEspecies::ValidadaTecnicamente)) {
            $this->estado = EstadoMatrizEspecies::Pendiente;
        }
    }
}
