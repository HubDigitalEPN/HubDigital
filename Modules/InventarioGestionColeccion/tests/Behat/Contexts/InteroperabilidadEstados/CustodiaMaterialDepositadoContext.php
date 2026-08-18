<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\InteroperabilidadEstados;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables\ConsultarEspecimenesPrestablesHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables\ConsultarEspecimenesPrestablesInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarEspecimenesPrestables\ConsultarEspecimenesPrestablesOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoCustodia;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

/**
 * Fija el defecto que motivó el servicio de "prestable": material devuelto a su
 * depositante seguía apareciendo como disponible, porque el filtro de préstamos solo
 * miraba el estado de circulación y no el régimen de tenencia.
 */
final class CustodiaMaterialDepositadoContext extends BaseContext
{
    private const CODIGO = 'MEPN-INV-DEP-99999-0001';

    private ?string $especimenId = null;

    private ?ConsultarEspecimenesPrestablesOutput $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Dado ─────────────────────────────────────────────────────────────────

    #[Given('que el catálogo tiene un espécimen devuelto a su depositante')]
    public function queElCatalogoTieneUnEspecimenDevuelto(): void
    {
        $this->sembrarEspecimen(EstadoCustodia::Devuelto);
    }

    #[Given('que el catálogo tiene un espécimen en cuarentena')]
    public function queElCatalogoTieneUnEspecimenEnCuarentena(): void
    {
        $this->sembrarEspecimen(EstadoCustodia::Cuarentena);
    }

    #[Given('que el catálogo tiene un espécimen en depósito temporal')]
    public function queElCatalogoTieneUnEspecimenTemporal(): void
    {
        $this->sembrarEspecimen(EstadoCustodia::Temporal);
    }

    #[Given('que el catálogo tiene un espécimen heredado sin régimen de custodia')]
    public function queElCatalogoTieneUnEspecimenHeredado(): void
    {
        $this->sembrarEspecimen(null);
    }

    // ── Cuando ───────────────────────────────────────────────────────────────

    #[When('el curador busca especímenes prestables')]
    public function elCuradorBuscaEspecimenesPrestables(): void
    {
        $handler = $this->make(ConsultarEspecimenesPrestablesHandler::class);

        try {
            $this->ultimaRespuesta = $handler->handle(
                ConsultarEspecimenesPrestablesInput::porIds([(string) $this->especimenId])
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[When('el sistema intenta comprometer ese espécimen en un préstamo')]
    public function elSistemaIntentaComprometerEseEspecimen(): void
    {
        $repo = $this->make(EspecimenRepositoryInterface::class);

        try {
            $especimen = $this->especimenSembrado();
            $especimen->marcarEnPrestamo();
            $repo->guardar($especimen);
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // ── Entonces ─────────────────────────────────────────────────────────────

    #[Then('la colección no ofrece ese espécimen')]
    public function laColeccionNoOfreceEseEspecimen(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'La consulta no debía fallar.');
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame([], $this->ultimaRespuesta->especimenes, 'El espécimen no debía ofrecerse.');
    }

    #[Then('la colección ofrece ese espécimen')]
    public function laColeccionOfreceEseEspecimen(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'La consulta no debía fallar.');
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertCount(1, $this->ultimaRespuesta->especimenes);
        Assert::assertSame(self::CODIGO, $this->ultimaRespuesta->especimenes[0]->codigoCatalogo);
    }

    #[Then('el sistema rechaza comprometerlo porque ya no está en la colección')]
    public function elSistemaRechazaComprometerlo(): void
    {
        Assert::assertInstanceOf(\DomainException::class, $this->excepcionCapturada);
        Assert::assertStringContainsString('ya no está en la colección', $this->excepcionCapturada->getMessage());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function sembrarEspecimen(?EstadoCustodia $custodia): void
    {
        $repo = $this->make(EspecimenRepositoryInterface::class);

        $especimen = Especimen::crear(
            id: $repo->nextIdentity(),
            codigoCatalogo: self::CODIGO,
            taxonId: null,
            localidad: 'Orellana, Ecuador',
            fechaColecta: '2023-02-12',
            colector: 'Padilla, D.',
            individualCount: 3,
            estadoCustodia: $custodia,
        );

        $repo->guardar($especimen);
        $this->especimenId = (string) $especimen->id();
    }

    private function especimenSembrado(): Especimen
    {
        $repo = $this->make(EspecimenRepositoryInterface::class);

        foreach ($repo->buscarTodos() as $especimen) {
            if ((string) $especimen->id() === $this->especimenId) {
                return $especimen;
            }
        }

        throw new \RuntimeException('El espécimen sembrado no está en el repositorio.');
    }
}
