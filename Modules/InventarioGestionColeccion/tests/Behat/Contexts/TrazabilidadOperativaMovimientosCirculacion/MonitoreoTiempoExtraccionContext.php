<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\UseCases\RegistrarDevolucionCaja\RegistrarDevolucionCajaHandler;
use Modules\InventarioGestionColeccion\Application\UseCases\RegistrarDevolucionCaja\RegistrarDevolucionCajaInput;
use Modules\InventarioGestionColeccion\Application\UseCases\RegistrarDevolucionCaja\RegistrarDevolucionCajaOutput;
use Modules\InventarioGestionColeccion\Application\UseCases\VerificarTiemposExtraccion\VerificarTiemposExtraccionHandler;
use Modules\InventarioGestionColeccion\Application\UseCases\VerificarTiemposExtraccion\VerificarTiemposExtraccionInput;
use Modules\InventarioGestionColeccion\Application\UseCases\VerificarTiemposExtraccion\VerificarTiemposExtraccionOutput;
use Modules\InventarioGestionColeccion\Domain\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\Entities\Gabinete;
use Modules\InventarioGestionColeccion\Domain\Entities\RanuraGabinete;
use Modules\InventarioGestionColeccion\Domain\Entities\UbicacionCaja;
use Modules\InventarioGestionColeccion\Domain\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\Repositories\GabineteRepository;
use Modules\InventarioGestionColeccion\Domain\Repositories\NotificacionRepository;
use Modules\InventarioGestionColeccion\Domain\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\Repositories\UbicacionCajaRepository;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoAlerta;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\EstadoCaja;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\ValueObjects\TipoAlerta;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class MonitoreoTiempoExtraccionContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private VerificarTiemposExtraccionHandler $verificarHandler;

    private RegistrarDevolucionCajaHandler $devolucionHandler;

    // ── Repositories ─────────────────────────────────────────────────────────

    private GabineteRepository $gabineteRepo;

    private RanuraGabineteRepository $ranuraRepo;

    private CajaRepository $cajaRepo;

    private UbicacionCajaRepository $ubicacionRepo;

    private AlertaUbicacionRepository $alertaRepo;

    private NotificacionRepository $notificacionRepo;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?CajaId $cajaId = null;

    private ?RanuraId $ranuraOrigenId = null;

    private ?VerificarTiemposExtraccionOutput $ultimaVerificacionOutput = null;

    private ?RegistrarDevolucionCajaOutput $ultimaDevolucionOutput = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->verificarHandler = $this->make(VerificarTiemposExtraccionHandler::class);
        $this->devolucionHandler = $this->make(RegistrarDevolucionCajaHandler::class);
        $this->gabineteRepo = $this->make(GabineteRepository::class);
        $this->ranuraRepo = $this->make(RanuraGabineteRepository::class);
        $this->cajaRepo = $this->make(CajaRepository::class);
        $this->ubicacionRepo = $this->make(UbicacionCajaRepository::class);
        $this->alertaRepo = $this->make(AlertaUbicacionRepository::class);
        $this->notificacionRepo = $this->make(NotificacionRepository::class);
    }

    // ==========================================
    // ESQUEMA: Evaluación del tiempo de extracción
    // ==========================================

    #[Given('/^que existe una caja entomológica fuera de su posición con condición (.+)$/u')]
    public function queExisteUnaCajaEntomológicaFueraDeSuPosiciónConCondición(string $condicion): void
    {
        [$caja, $ranura] = $this->sembrarCajaEnTransitoConRanuraOrigen();

        // Ajustar el tiempo de retiro según la condición para simular cada escenario
        $tiempoRetiro = match (true) {
            str_contains($condicion, 'dentro del límite') => now()->subHours(4),
            str_contains($condicion, 'próxima a superar') => now()->subHours(22),
            str_contains($condicion, 'superando el límite') => now()->subHours(26),
            default => throw new \InvalidArgumentException("Condición desconocida: {$condicion}"),
        };

        // Actualizar la ubicación cerrada con el tiempo de retiro simulado
        $ubicacion = UbicacionCaja::registrarRetiro(
            id: $this->ubicacionRepo->nextIdentity(),
            cajaId: $caja->id(),
            ranuraId: $ranura->id(),
            ingresadaEn: $tiempoRetiro->copy()->subHours(2),
            retiradaEn: $tiempoRetiro,
        );
        $this->ubicacionRepo->guardar($ubicacion);

        $cajaPersistida = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaPersistida, 'La caja debe estar persistida');
        Assert::assertTrue(
            $cajaPersistida->estadoActual()->equals(EstadoCaja::EnTransito),
            'La caja debe estar en tránsito (fuera de su posición)'
        );

        $ubicacionPersistida = $this->ubicacionRepo->buscarUltimaRetiroActivaPorCaja($caja->id());
        Assert::assertNotNull($ubicacionPersistida, 'Debe existir registro de retiro activo');
        Assert::assertNotNull($ubicacionPersistida->retiradaEn(), 'El tiempo de retiro debe estar registrado');
    }

    #[When('se verifican los tiempos de extracción')]
    public function seVerificanLosTiemposDeExtracción(): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja en estado de tránsito (@Given)');

        try {
            $this->ultimaVerificacionOutput = $this->verificarHandler->handle(
                new VerificarTiemposExtraccionInput(
                    cajaId: (string) $this->cajaId,
                    limiteDiasHabiles: 1,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se debe registrar sin alertas y el estado permanece "En Tránsito"')]
    public function seDebeRegistrarSinAlertasYElEstadoPermanece(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción al verificar tiempos');
        Assert::assertNotNull($this->ultimaVerificacionOutput, 'El handler debe retornar una respuesta');
        Assert::assertFalse(
            $this->ultimaVerificacionOutput->alertaGenerada(),
            'No debe generarse alerta cuando la caja está dentro del límite'
        );

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNull($alerta, 'No debe existir ninguna alerta activa para esta caja');

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnTransito),
            'La caja debe permanecer en estado "en_transito"'
        );
    }

    #[Then('se debe enviar una notificación preventiva al curador responsable')]
    public function seDebeEnviarUnaNotificaciónPreventivaAlCuradorResponsable(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción');
        Assert::assertNotNull($this->ultimaVerificacionOutput);
        Assert::assertTrue(
            $this->ultimaVerificacionOutput->notificacionPreventiva(),
            'El output debe indicar que se generó notificación preventiva'
        );

        $notificacion = $this->notificacionRepo->buscarUltimaNotificacionPorCaja($this->cajaId);
        Assert::assertNotNull($notificacion, 'Debe haberse registrado una notificación preventiva');
        Assert::assertSame(
            'extraccion_proxima_al_limite',
            $notificacion->tipo(),
            'La notificación debe ser de tipo preventivo'
        );
    }

    #[Then('se debe generar una alerta de "Tiempo de Extracción Excedido" y el estado cambia a "Extracción Prolongada"')]
    public function seDebeGenerarUnaAlertaDeTiempoDeExtraccionExcedido(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'No se esperaba excepción');
        Assert::assertNotNull($this->ultimaVerificacionOutput);
        Assert::assertTrue(
            $this->ultimaVerificacionOutput->alertaGenerada(),
            'El output debe indicar que se generó alerta de exceso'
        );

        $alerta = $this->alertaRepo->buscarActivaPorCaja($this->cajaId);
        Assert::assertNotNull($alerta, 'Debe existir una alerta activa');
        Assert::assertTrue(
            $alerta->tipo()->equals(TipoAlerta::ExtraccionProlongada),
            'El tipo de alerta debe ser extraccion_prolongada'
        );
        Assert::assertTrue(
            $alerta->estado()->equals(EstadoAlerta::Activa),
            'La alerta debe estar activa'
        );

        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja);
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::ExtraccionProlongada),
            'La caja debe cambiar a estado "extraccion_prolongada"'
        );
    }

    // ==========================================
    // ESCENARIO: Devolución de caja en estado Extracción Prolongada
    // ==========================================

    #[Given('que existe una caja entomológica en estado "Extracción Prolongada"')]
    public function queExisteUnaCajaEntomológicaEnEstadoExtracciónProlongada(): void
    {
        [$caja, $ranura] = $this->sembrarCajaEnTransitoConRanuraOrigen();

        // Forzar estado de extracción prolongada directamente en el dominio
        $caja->marcarExtraccionProlongada();
        $this->cajaRepo->guardar($caja);

        $cajaPersistida = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($cajaPersistida, 'La caja debe estar persistida');
        Assert::assertTrue(
            $cajaPersistida->estadoActual()->equals(EstadoCaja::ExtraccionProlongada),
            'La caja debe estar en estado "extraccion_prolongada" como precondición'
        );
        Assert::assertNotNull($this->ranuraOrigenId, 'Debe existir la ranura de origen para la devolución');
    }

    #[When('el curador registra la devolución de la caja a su ranura en el gabinete')]
    public function elCuradorRegistraLaDevoluciónDeLaCajaASuRanuraEnElGabinete(): void
    {
        Assert::assertNotNull($this->cajaId, 'Se requiere una caja en estado previo');
        Assert::assertNotNull($this->ranuraOrigenId, 'Se requiere la ranura de destino para la devolución');

        try {
            $this->ultimaDevolucionOutput = $this->devolucionHandler->handle(
                new RegistrarDevolucionCajaInput(
                    cajaId: (string) $this->cajaId,
                    ranuraId: (string) $this->ranuraOrigenId,
                    actorId: 'curador-001',
                    actorRol: 'curador',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se debe registrar la devolución')]
    public function seDebeRegistrarLaDevolución(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción al registrar la devolución: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaDevolucionOutput, 'El handler de devolución debe retornar una respuesta');
        Assert::assertTrue($this->ultimaDevolucionOutput->exitoso(), 'La devolución debe reportarse como exitosa');

        $ubicacionActiva = $this->ubicacionRepo->buscarUbicacionActivaPorCaja($this->cajaId);
        Assert::assertNotNull($ubicacionActiva, 'Debe existir un registro activo de ubicación tras la devolución');
        Assert::assertNull($ubicacionActiva->retiradaEn(), 'La ubicación activa no debe tener fecha de retiro');
    }

    #[Then('el estado de la caja debe cambiar a "En Gabinete"')]
    public function elEstadoDeLaCajaDebeCambiarAEnGabinete(): void
    {
        $caja = $this->cajaRepo->buscarPorId($this->cajaId);
        Assert::assertNotNull($caja, 'La caja debe existir en el repositorio');
        Assert::assertTrue(
            $caja->estadoActual()->equals(EstadoCaja::EnGabinete),
            'La caja debe cambiar a estado "en_gabinete" tras la devolución'
        );
    }

    // ==========================================
    // Factory Methods
    // ==========================================

    /** @return array{0: Caja, 1: RanuraGabinete} */
    private function sembrarCajaEnTransitoConRanuraOrigen(): array
    {
        $gabinete = Gabinete::crear(
            id: $this->gabineteRepo->nextIdentity(),
            codigo: 'GAB-EXT-001',
            nombre: 'Gabinete Extracción',
            totalRanuras: 5,
        );
        $this->gabineteRepo->guardar($gabinete);

        $ranura = RanuraGabinete::crear(
            id: $this->ranuraRepo->nextIdentity(),
            gabineteId: $gabinete->id(),
            numeroRanura: 1,
            familiaTaxonomicaEsperadaId: 'Nymphalidae',
        );
        $this->ranuraRepo->guardar($ranura);
        $this->ranuraOrigenId = $ranura->id();

        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: 'CAJA-EXT-001',
            familiaTaxonomicaId: 'Nymphalidae',
            capacidadMaxima: 10,
        );
        $this->cajaRepo->guardar($caja);
        $this->cajaId = $caja->id();

        $persistida = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($persistida, 'La caja de extracción debe estar persistida');
        Assert::assertNotEmpty($persistida->codigo(), 'La caja debe tener código');

        return [$persistida, $ranura];
    }
}
