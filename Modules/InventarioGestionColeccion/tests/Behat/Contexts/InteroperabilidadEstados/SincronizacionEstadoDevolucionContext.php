<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\InteroperabilidadEstados;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RestaurarEstadoEspecimenes\RestaurarEstadoEspecimenesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RestaurarEstadoEspecimenes\RestaurarEstadoEspecimenesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RestaurarEstadoEspecimenes\RestaurarEstadoEspecimenesOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Taxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TaxonRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EspecimenId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoEspecimen;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class SincronizacionEstadoDevolucionContext extends BaseContext
{
    private ?string $especimenId = null;

    private ?RestaurarEstadoEspecimenesOutput $ultimaRespuesta = null;

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
            codigoCatalogo: 'MEPN-SYNC-DEVOLUCION',
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

    private function restaurar(array $conNovedad): void
    {
        Assert::assertNotNull($this->especimenId, 'Se esperaba un espécimen del paso Dado anterior');

        $handler = $this->make(RestaurarEstadoEspecimenesHandler::class);

        $this->ultimaRespuesta = $handler->handle(
            new RestaurarEstadoEspecimenesInput(
                especimenIds: [$this->especimenId],
                conNovedad: $conNovedad,
            )
        );
    }

    // ── Dado ─────────────────────────────────────────────────────────────────

    #[Given('que el catálogo tiene un espécimen en préstamo por cerrar')]
    public function queElCatalogoTieneUnEspecimenEnPrestamoPorCerrar(): void
    {
        $this->especimenId = $this->sembrarEspecimen(EstadoEspecimen::EnPrestamo);

        Assert::assertSame(
            EstadoEspecimen::EnPrestamo,
            $this->estadoPersistido(),
            'El espécimen sembrado debía quedar en préstamo'
        );
    }

    #[Given('que el catálogo tiene un espécimen ya disponible por cerrar')]
    public function queElCatalogoTieneUnEspecimenYaDisponiblePorCerrar(): void
    {
        $this->especimenId = $this->sembrarEspecimen();

        Assert::assertSame(
            EstadoEspecimen::Disponible,
            $this->estadoPersistido(),
            'El espécimen sembrado debía quedar disponible'
        );
    }

    #[Given('que el identificador a restaurar no corresponde a ningún espécimen')]
    public function queElIdentificadorARestaurarNoCorrespondeANingunEspecimen(): void
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

    #[When('el sistema restaura ese espécimen sin novedades')]
    public function elSistemaRestauraEseEspecimenSinNovedades(): void
    {
        $this->restaurar(conNovedad: []);
    }

    #[When('el sistema restaura ese espécimen con novedad')]
    public function elSistemaRestauraEseEspecimenConNovedad(): void
    {
        $this->restaurar(conNovedad: [$this->especimenId]);
    }

    // ── Entonces ─────────────────────────────────────────────────────────────

    #[Then('el catálogo deja el espécimen disponible')]
    public function elCatalogoDejaElEspecimenDisponible(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            [$this->especimenId],
            $this->ultimaRespuesta->disponibles,
            'El espécimen debía figurar entre los devueltos a disponible'
        );
        Assert::assertSame(
            EstadoEspecimen::Disponible,
            $this->estadoPersistido(),
            'El espécimen debía quedar disponible tras el cierre'
        );
    }

    #[Then('el catálogo deja el espécimen observado')]
    public function elCatalogoDejaElEspecimenObservado(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertSame(
            [$this->especimenId],
            $this->ultimaRespuesta->observados,
            'El espécimen con novedad debía figurar entre los observados'
        );
        Assert::assertSame(
            [],
            $this->ultimaRespuesta->disponibles,
            'Un espécimen con novedad no debe volver a disponible'
        );
        Assert::assertSame(
            EstadoEspecimen::Observado,
            $this->estadoPersistido(),
            'El espécimen debía quedar observado tras el cierre'
        );
    }

    #[Then('el sistema reporta el espécimen como no encontrado al restaurar')]
    public function elSistemaReportaElEspecimenComoNoEncontradoAlRestaurar(): void
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
