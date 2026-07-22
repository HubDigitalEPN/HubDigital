<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\InteroperabilidadEstados;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenesEnPrestamo\MarcarEspecimenesEnPrestamoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenesEnPrestamo\MarcarEspecimenesEnPrestamoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\MarcarEspecimenesEnPrestamo\MarcarEspecimenesEnPrestamoOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class SincronizacionEstadoPrestamoContext extends BaseContext
{
    private ?string $especimenId = null;

    private ?MarcarEspecimenesEnPrestamoOutput $ultimaRespuesta = null;

    // ── Helpers de fixture ───────────────────────────────────────────────────

    private function sembrarEspecimen(?EstadoEspecimen $estado = null): string
    {
        $taxonRepo = $this->make(TaxonRepositoryInterface::class);
        $taxon = Taxon::crear(
            id: $taxonRepo->nextIdentity(),
            nombreCientifico: 'Morpho peleides',
            rango: 'especie',
            autor: 'Kollar',
            anioDescripcion: 1850,
        );
        $taxonRepo->guardar($taxon);

        $repo = $this->make(EspecimenRepositoryInterface::class);
        $especimen = Especimen::crear(
            id: $repo->nextIdentity(),
            codigoCatalogo: 'MEPN-SYNC-PRESTAMO',
            taxonId: (string) $taxon->id(),
            localidad: 'Napo, Ecuador',
            fechaColecta: '2020-03-15',
            colector: 'Juan Pérez',
        );

        // `crear` siempre nace disponible: el estado inicial se ajusta por transición.
        if ($estado === EstadoEspecimen::EnPrestamo) {
            $especimen->marcarEnPrestamo();
        }

        $repo->guardar($especimen);

        return (string) $especimen->id();
    }

    private function estadoPersistido(): EstadoEspecimen
    {
        $repo = $this->make(EspecimenRepositoryInterface::class);
        $persistido = $repo->buscarPorId(EspecimenId::desde((string) $this->especimenId));
        Assert::assertNotNull($persistido, 'El espécimen no fue encontrado en el repositorio');

        return $persistido->estado();
    }

    // ── Dado ─────────────────────────────────────────────────────────────────

    #[Given('que el catálogo tiene un espécimen disponible')]
    public function queElCatalogoTieneUnEspecimenDisponible(): void
    {
        $this->especimenId = $this->sembrarEspecimen();

        Assert::assertSame(
            EstadoEspecimen::Disponible,
            $this->estadoPersistido(),
            'El espécimen sembrado debía quedar disponible'
        );
    }

    #[Given('que el catálogo tiene un espécimen ya prestado')]
    public function queElCatalogoTieneUnEspecimenYaPrestado(): void
    {
        $this->especimenId = $this->sembrarEspecimen(EstadoEspecimen::EnPrestamo);

        Assert::assertSame(
            EstadoEspecimen::EnPrestamo,
            $this->estadoPersistido(),
            'El espécimen sembrado debía quedar en préstamo'
        );
    }

    #[Given('que el identificador a prestar no corresponde a ningún espécimen')]
    public function queElIdentificadorAPrestarNoCorrespondeANingunEspecimen(): void
    {
        $repo = $this->make(EspecimenRepositoryInterface::class);
        $id = $repo->nextIdentity();

        Assert::assertNull(
            $repo->buscarPorId($id),
            'El identificador de prueba no debía existir en el catálogo'
        );

        $this->especimenId = (string) $id;
    }

    // ── Cuando ───────────────────────────────────────────────────────────────

    #[When('el sistema sincroniza la salida en préstamo de ese espécimen')]
    public function elSistemaSincronizaLaSalidaEnPrestamoDeEseEspecimen(): void
    {
        Assert::assertNotNull($this->especimenId, 'Se esperaba un espécimen del paso Dado anterior');

        $handler = $this->make(MarcarEspecimenesEnPrestamoHandler::class);

        $this->ultimaRespuesta = $handler->handle(
            new MarcarEspecimenesEnPrestamoInput(especimenIds: [$this->especimenId])
        );
    }

    // ── Entonces ─────────────────────────────────────────────────────────────

    #[Then('el catálogo deja el espécimen en préstamo')]
    public function elCatalogoDejaElEspecimenEnPrestamo(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            [$this->especimenId],
            $this->ultimaRespuesta->actualizados,
            'El espécimen debía figurar entre los actualizados'
        );
        Assert::assertSame(
            EstadoEspecimen::EnPrestamo,
            $this->estadoPersistido(),
            'El espécimen debía quedar en préstamo tras la sincronización'
        );
    }

    #[Then('el sistema reporta el espécimen como ya prestado')]
    public function elSistemaReportaElEspecimenComoYaPrestado(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            [$this->especimenId],
            $this->ultimaRespuesta->yaEnPrestamo,
            'El espécimen debía reportarse como ya prestado'
        );
        Assert::assertSame(
            [],
            $this->ultimaRespuesta->actualizados,
            'No debía actualizarse ningún espécimen'
        );
        Assert::assertSame(
            EstadoEspecimen::EnPrestamo,
            $this->estadoPersistido(),
            'El espécimen debía seguir en préstamo'
        );
    }

    #[Then('el sistema reporta el espécimen como no encontrado al prestar')]
    public function elSistemaReportaElEspecimenComoNoEncontradoAlPrestar(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            [$this->especimenId],
            $this->ultimaRespuesta->noEncontrados,
            'El identificador debía reportarse como no encontrado'
        );
        Assert::assertTrue(
            $this->ultimaRespuesta->tieneAnomalias(),
            'El resultado debía marcarse como anómalo para que el adaptador lo registre'
        );
    }
}
