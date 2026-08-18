<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects;

/**
 * De qué trámite de depósito vino este espécimen.
 *
 * Agrupa la junta con el módulo de recepciones en un solo concepto en vez de esparcir
 * seis campos sueltos por la entidad. Es null para los ~48k especímenes heredados de la
 * carga masiva del catálogo, que no provienen de ningún trámite.
 *
 * La identidad fuerte es {@see registroId}: el uuid de la fila de matriz que declaró
 * este espécimen. Antes el vínculo era el `codigo_catalogo` derivado de la POSICIÓN de
 * la fila, lo que hacía que añadir o quitar una fila entre dos aprobaciones corriera
 * todos los índices y duplicara el lote entero.
 *
 * {@see numeroSolicitud} se conserva aunque {@see solicitudId} lo haga redundante: es
 * el único rastro que sobrevive si la solicitud llega a borrarse, y ya pasó — hay 13
 * especímenes cuya solicitud desapareció del módulo de recepciones.
 */
final readonly class ProcedenciaDeposito
{
    private function __construct(
        public string $registroId,
        public string $solicitudId,
        public int $indiceMatriz,
        public string $numeroSolicitud,
        public ?string $tipoTramite,
        public ?\DateTimeImmutable $ingresadoEn,
    ) {}

    public static function crear(
        string $registroId,
        string $solicitudId,
        int $indiceMatriz,
        string $numeroSolicitud,
        ?string $tipoTramite = null,
        ?\DateTimeImmutable $ingresadoEn = null,
    ): self {
        if (trim($registroId) === '') {
            throw new \InvalidArgumentException('La procedencia de depósito exige el registro de matriz que originó el espécimen.');
        }

        if (trim($numeroSolicitud) === '') {
            throw new \InvalidArgumentException('La procedencia de depósito exige el número de solicitud.');
        }

        if ($indiceMatriz < 1) {
            throw new \InvalidArgumentException('El índice de matriz empieza en 1.');
        }

        return new self(
            registroId: trim($registroId),
            solicitudId: trim($solicitudId),
            indiceMatriz: $indiceMatriz,
            numeroSolicitud: trim($numeroSolicitud),
            tipoTramite: $tipoTramite,
            ingresadoEn: $ingresadoEn,
        );
    }

    /**
     * Reconstruye desde persistencia sin exigir la identidad fuerte.
     *
     * Existe para el material ingresado antes de que la junta existiera: puede tener
     * número de solicitud e índice y no tener aún `registro_deposito_id` (lo asigna el
     * comando de vinculación), o tener número y no solicitud si el trámite se borró.
     */
    public static function parcial(
        ?string $registroId,
        ?string $solicitudId,
        ?int $indiceMatriz,
        ?string $numeroSolicitud,
        ?string $tipoTramite = null,
        ?\DateTimeImmutable $ingresadoEn = null,
    ): ?self {
        if ($numeroSolicitud === null || trim($numeroSolicitud) === '') {
            return null;
        }

        return new self(
            registroId: $registroId ?? '',
            solicitudId: $solicitudId ?? '',
            indiceMatriz: $indiceMatriz ?? 0,
            numeroSolicitud: trim($numeroSolicitud),
            tipoTramite: $tipoTramite,
            ingresadoEn: $ingresadoEn,
        );
    }

    /** El espécimen está atado a su fila de matriz por identidad, no por convención. */
    public function tieneVinculoFuerte(): bool
    {
        return $this->registroId !== '';
    }

    /**
     * El trámite que lo trajo ya no existe en el módulo de recepciones.
     *
     * No es motivo para borrar el espécimen: en una colección científica el rastro de
     * qué estuvo bajo custodia es patrimonio documental. Sí es motivo para que el
     * curador lo revise.
     */
    public function esHuerfano(): bool
    {
        return $this->solicitudId === '';
    }

    public function equals(self $other): bool
    {
        return $this->registroId === $other->registroId
            && $this->solicitudId === $other->solicitudId
            && $this->indiceMatriz === $other->indiceMatriz
            && $this->numeroSolicitud === $other->numeroSolicitud;
    }
}
