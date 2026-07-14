<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\Support;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\PrestamoId;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryRecordatorioDevolucionRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudProrrogaRepository;

/**
 * Holder de estado in-memory compartido entre los Contexts de prórroga (curador e
 * investigador), que conviven en la misma suite Behat.
 *
 * Se usa una clase dedicada (no propiedades estáticas de un trait) porque en PHP las
 * estáticas de un trait son independientes por cada clase que lo usa; aquí necesitamos
 * que ambos Contexts lean y escriban exactamente el mismo estado.
 */
final class ProrrogaTestState
{
    public static InMemoryPrestamoRepository $prestamoRepo;

    public static InMemorySolicitudProrrogaRepository $solicitudRepo;

    public static InMemoryRecordatorioDevolucionRepository $recordatorioRepo;

    public static FakeEventPublisherAdapter $publisher;

    public static ?PrestamoId $prestamoId = null;

    public static ?DateTimeImmutable $fechaFinOriginal = null;

    public static function reiniciar(): void
    {
        self::$prestamoRepo = new InMemoryPrestamoRepository;
        self::$solicitudRepo = new InMemorySolicitudProrrogaRepository;
        self::$recordatorioRepo = new InMemoryRecordatorioDevolucionRepository;
        self::$publisher = new FakeEventPublisherAdapter;
        self::$prestamoId = null;
        self::$fechaFinOriginal = null;
    }
}
