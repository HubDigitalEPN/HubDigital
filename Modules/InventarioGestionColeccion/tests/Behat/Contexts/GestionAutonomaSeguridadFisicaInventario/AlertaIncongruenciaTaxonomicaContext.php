<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\UseCases\InsertarCajaEnRanura\InsertarCajaEnRanuraHandler;
use Modules\InventarioGestionColeccion\Application\UseCases\InsertarCajaEnRanura\InsertarCajaEnRanuraInput;
use Modules\InventarioGestionColeccion\Application\UseCases\InsertarCajaEnRanura\InsertarCajaEnRanuraOutput;
use Modules\InventarioGestionColeccion\Domain\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoAlerta;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\GabineteId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class AlertaIncongruenciaTaxonomicaContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private InsertarCajaEnRanuraHandler $handler;

    // ── Repositories ─────────────────────────────────────────────────────────

    private GabineteRepository $gabineteRepo;

    private RanuraGabineteRepository $ranuraRepo;

    private CajaRepository $cajaRepo;

    private AlertaUbicacionRepository $alertaRepo;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?GabineteId $gabineteId = null;

    private ?RanuraId $ranuraId = null;

    private ?CajaId $cajaId = null;

    private ?InsertarCajaEnRanuraOutput $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private const FAMILIA_GABINETE = 'Nymphalidae';

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->handler = $this->make(InsertarCajaEnRanuraHandler::class);
        $this->gabineteRepo = $this->make(GabineteRepository::class);
        $this->ranuraRepo = $this->make(RanuraGabineteRepository::class);
        $this->cajaRepo = $this->make(CajaRepository::class);
        $this->alertaRepo = $this->make(AlertaUbicacionRepository::class);
    }

    // ==========================================
    // ESQUEMA: Inserción según coincidencia taxonómica
    // ==========================================

    #[Given('que existe un gabinete configurado para una familia taxonómica')]
    public function queExisteUnGabineteConfiguradoParaUnaFamiliaTaxonómica(): void
    {
        $gabinete = $this->sembrarGabineteBase();

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: 1,
            familiaTaxonomicaEsperadaId: self::FAMILIA_GABINETE,
        );
        $this->ranuraRepo->guardar($ranura);
        $this->ranuraId = $ranura->id();

        $ranuraPersistida = $this->ranuraRepo->buscarPorId($ranura->id());
        Assert::assertNotNull($ranuraPersistida, 'La ranura debe estar persistida antes de avanzar');
        Assert::assertSame(self::FAMILIA_GABINETE, $ranuraPersistida->familiaTaxonomicaEsperadaId(), 'La ranura debe tener la familia canónica configurada');
        Assert::assertNull($ranuraPersistida->cajaActualId(), 'La ranura debe estar vacía antes del ingreso');
    }

    #[Given('existe una caja entomológica con especímenes de familia :coincidencia')]
    public function existeUnaCajaEntomológicaConEspecímenesDeFamilia(string $coincidencia): void
    {
        $familiaCaja = match ($coincidencia) {
            'correcta' => self::FAMILIA_GABINETE,
            'incorrecta' => 'Papilionidae',
            default => $coincidencia,
        };

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: 'CAJA-TEST-001',
            familiaTaxonomicaId: $familiaCaja,
            capacidadMaxima: 10,
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaId = $caja->id();

        $cajaPersistida = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaPersistida, 'La caja debe estar persistida antes de avanzar');
        Assert::assertSame($familiaCaja, $cajaPersistida->familiaTaxonomicaId(), 'La familia de la caja debe coincidir con la sembrada');
    }

    #[When('inserto la caja en una ranura vacía del gabinete')]
    public function insertoLaCajaEnUnaRanuraVacíaDelGabinete(): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja en estado previo (@Given)');
        Assert::assertNotNull($this->ranuraId, 'Se requiere una ranura en estado previo (@Given)');

        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new InsertarCajaEnRanuraInput(
                    cajaId: (string) $this->cajaId,
                    ranuraId: (string) $this->ranuraId,
                    actorRol: 'curador',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se debe registrar el ingreso exitoso de la caja')]
    public function seDebeRegistrarElIngresoExitosoDeLaCaja(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba una excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');
        Assert::assertTrue($this->ultimaRespuesta->exitoso(), 'Se esperaba ingreso exitoso pero el handler indicó fallo');
        Assert::assertNull($this->ultimaRespuesta->tipoAlerta(), 'No debe generarse alerta en ingreso exitoso');

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        Assert::assertTrue($caja->estadoActual()->equals(EstadoCaja::EnGabinete), 'La caja debe quedar en estado "en_gabinete"');
    }

    #[Then('se debe generar una alerta de "Incongruencia Taxonómica" y el estado de la caja se marca como "Ubicación Incorrecta"')]
    public function seDebeGenerarUnaAlertaDeIncongruenciaTaxonomica(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba una excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNotNull($alerta, 'Debe existir una alerta activa de incongruencia taxonómica');
        Assert::assertTrue($alerta->tipo()->equals(TipoAlerta::IncongruenciaTaxonomica), 'El tipo de alerta debe ser incongruencia_taxonomica');
        Assert::assertTrue($alerta->estado()->equals(EstadoAlerta::Activa), 'La alerta debe estar activa');

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        Assert::assertTrue($caja->estadoActual()->equals(EstadoCaja::UbicacionIncorrecta), 'La caja debe marcarse como "ubicacion_incorrecta"');
    }

    // ==========================================
    // ESCENARIO: Inserción de caja sin familia taxonómica asignada
    // ==========================================

    #[Given('existe una caja entomológica sin familia taxonómica asignada')]
    public function existeUnaCajaEntomológicaSinFamiliaTaxonómicaAsignada(): void
    {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: 'CAJA-SIN-FAMILIA-001',
            familiaTaxonomicaId: null,
            capacidadMaxima: 10,
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaId = $caja->id();

        $cajaPersistida = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaPersistida, 'La caja debe estar persistida');
        Assert::assertNull($cajaPersistida->familiaTaxonomicaId(), 'La caja no debe tener familia taxonómica asignada');
    }

    #[Then('se debe generar una alerta de "Familia No Asignada"')]
    public function seDebeGenerarUnaAlertaDeFamiliaNoAsignada(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba una excepción de dominio en este escenario');
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler debe retornar una respuesta');

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNotNull($alerta, 'Debe existir una alerta activa');
        Assert::assertTrue($alerta->tipo()->equals(TipoAlerta::FamiliaNoAsignada), 'El tipo de alerta debe ser familia_no_asignada');
        Assert::assertTrue($alerta->estado()->equals(EstadoAlerta::Activa), 'La alerta debe estar activa');
    }

    #[Then('el estado de la caja debe marcarse como "Pendiente de Clasificación"')]
    public function elEstadoDeLaCajaDebeMarcarseComoPendienteDeClasificación(): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja en estado previo');

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja, 'La caja debe existir en el repositorio');
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::PendienteClasificacion),
            'La caja debe estar en estado "pendiente_clasificacion"'
        );
    }

    // ==========================================
    // Factory Methods
    // ==========================================

    private function sembrarGabineteBase(): Gabinete
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: 'GAB-001',
            nombre: 'Gabinete Principal Nymphalidae',
            totalRanuras: 10,
        );
        $this->gabineteRepo->guardar($gabinete);
        $this->gabineteId = $gabinete->id();

        $persistido = $this->gabineteRepo->buscarPorId($gabinete->id());
        Assert::assertNotNull($persistido, 'El gabinete base debe quedar persistido');
        Assert::assertNotEmpty($persistido->codigo(), 'El gabinete debe tener código');

        return $persistido;
    }
}
