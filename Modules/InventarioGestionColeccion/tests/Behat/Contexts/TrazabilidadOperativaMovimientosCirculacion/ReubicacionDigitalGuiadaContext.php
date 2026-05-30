<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarHistorialCustodiaEspecimen\ConsultarHistorialCustodiaEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarHistorialCustodiaEspecimen\ConsultarHistorialCustodiaEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConsultarHistorialCustodiaEspecimen\ConsultarHistorialCustodiaEspecimenOutput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IniciarReubicacionEspecimen\IniciarReubicacionEspecimenHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IniciarReubicacionEspecimen\IniciarReubicacionEspecimenInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IniciarReubicacionEspecimen\IniciarReubicacionEspecimenOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Caja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EspecimenEnCaja;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\TrasladoEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenEnCajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\TrasladoEspecimenRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTraslado;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use PHPUnit\Framework\Assert;

final class ReubicacionDigitalGuiadaContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private IniciarReubicacionEspecimenHandler $reubicacionHandler;

    private ConsultarHistorialCustodiaEspecimenHandler $historialHandler;

    // ── Repositories ─────────────────────────────────────────────────────────

    private CajaRepository $cajaRepo;

    private EspecimenEnCajaRepository $especimenRepo;

    private TrasladoEspecimenRepository $trasladoRepo;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?string $especimenCodigo = null;

    private ?CajaId $cajaOrigenId = null;

    private ?CajaId $cajaDestinoId = null;

    private ?IniciarReubicacionEspecimenOutput $ultimaReubicacionOutput = null;

    private ?ConsultarHistorialCustodiaEspecimenOutput $ultimoHistorialOutput = null;

    private ?\Throwable $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->reubicacionHandler = $this->make(IniciarReubicacionEspecimenHandler::class);
        $this->historialHandler = $this->make(ConsultarHistorialCustodiaEspecimenHandler::class);
        $this->cajaRepo = $this->make(CajaRepository::class);
        $this->especimenRepo = $this->make(EspecimenEnCajaRepository::class);
        $this->trasladoRepo = $this->make(TrasladoEspecimenRepository::class);
    }

    // ==========================================
    // ESCENARIO: Traslado exitoso de un espécimen de una caja a otra
    // ==========================================

    #[Given('que existe un espécimen ubicado en una caja de origen')]
    public function queExisteUnEspécimenUbicadoEnUnaCajaDeOrigen(): void
    {
        $cajaOrigen = $this->sembrarCajaBase(
            codigo: 'CAJA-ORIGEN-001',
            familia: 'Nymphalidae',
            especimenesActuales: 1,
        );
        $this->cajaOrigenId = $cajaOrigen->id();

        $especimen = EspecimenEnCaja::asignar(
            id: $this->especimenRepo->nextIdentity(),
            especimenCodigoExterno: 'ESP-REUB-001',
            cajaId: $cajaOrigen->id(),
        );
        $this->especimenRepo->guardar($especimen);
        $this->especimenCodigo = 'ESP-REUB-001';

        $especimenPersistido = $this->especimenRepo->buscarPorCodigoExterno('ESP-REUB-001');
        Assert::assertNotNull($especimenPersistido, 'El espécimen debe estar persistido en la caja de origen');
        Assert::assertTrue(
            $especimenPersistido->cajaId()->equals($cajaOrigen->id()),
            'El espécimen debe pertenecer a la caja de origen'
        );
        Assert::assertFalse($especimenPersistido->enPrestamo(), 'El espécimen no debe estar en préstamo');
    }

    #[Given('existe una caja de destino con espacio disponible y familia taxonómica compatible')]
    public function existeUnaCajaDeDestinoConEspacioDisponibleYFamiliaTaxonómicaCompatible(): void
    {
        Assert::assertNotNull($this->cajaOrigenId, 'Se requiere una caja de origen como precondición');

        $cajaOrigen = $this->cajaRepo->buscarPorId($this->cajaOrigenId);
        Assert::assertNotNull($cajaOrigen, 'La caja de origen debe existir en el repositorio');

        $cajaDestino = $this->sembrarCajaBase(
            codigo: 'CAJA-DESTINO-001',
            familia: $cajaOrigen->familiaTaxonomicaId(),
            especimenesActuales: 3,
        );
        $this->cajaDestinoId = $cajaDestino->id();

        Assert::assertSame(
            $cajaOrigen->familiaTaxonomicaId(),
            $cajaDestino->familiaTaxonomicaId(),
            'La familia taxonómica de destino debe ser compatible con la de origen'
        );
        Assert::assertTrue(
            $cajaDestino->tieneEspacioDisponible(),
            'La caja de destino debe tener espacio disponible'
        );
    }

    #[When('inicio el proceso de reubicación digital guiada para el espécimen')]
    public function inicioElProcesoDeReubicaciónDigitalGuiadaParaElEspécimen(): void
    {
        // El proceso de reubicación es de dos pasos: este step guarda el espécimen seleccionado.
        // El handler se ejecuta en el siguiente @When al confirmar la caja de destino.
        Assert::assertNotNull($this->especimenCodigo, 'Se requiere un espécimen seleccionado');
    }

    #[When('selecciono la caja de destino')]
    public function seleccionoLaCajaDeDestino(): void
    {
        Assert::assertNotNull($this->especimenCodigo, 'Se requiere un espécimen seleccionado antes de confirmar destino');
        Assert::assertNotNull($this->cajaOrigenId, 'Se requiere la caja de origen');
        Assert::assertNotNull($this->cajaDestinoId, 'Se requiere la caja de destino seleccionada');

        try {
            $this->ultimaReubicacionOutput = $this->reubicacionHandler->handle(
                new IniciarReubicacionEspecimenInput(
                    especimenCodigoExterno: $this->especimenCodigo,
                    cajaOrigenId: (string) $this->cajaOrigenId,
                    cajaDestinoId: (string) $this->cajaDestinoId,
                    iniciadorId: 'curador-001',
                    motivo: 'reubicacion',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('se debe actualizar la ubicación del espécimen a la caja de destino')]
    public function seDebeActualizarLaUbicaciónDelEspécimenALaCajaDeDestino(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción: '.($this->excepcionCapturada?->getMessage() ?? '')
        );
        Assert::assertNotNull($this->ultimaReubicacionOutput, 'El handler debe retornar una respuesta');

        $especimen = $this->especimenRepo->buscarPorCodigoExterno($this->especimenCodigo);
        Assert::assertNotNull($especimen, 'El espécimen debe seguir existiendo');
        Assert::assertTrue(
            $especimen->cajaId()->equals($this->cajaDestinoId),
            'La caja del espécimen debe ser ahora la caja de destino'
        );
    }

    #[Then('se debe guardar un registro en el historial de custodia con los contenedores de origen y destino')]
    public function seDebeGuardarUnRegistroEnElHistorialDeCustodiaConLosContenedoresDeOrigenYDestino(): void
    {
        Assert::assertNotNull($this->ultimaReubicacionOutput);

        $traslado = $this->trasladoRepo->buscarPorEspecimenYCajas(
            especimenCodigo: $this->especimenCodigo,
            cajaOrigenId: $this->cajaOrigenId,
            cajaDestinoId: $this->cajaDestinoId,
        );
        Assert::assertNotNull($traslado, 'Debe existir un registro de traslado en el historial de custodia');
        Assert::assertTrue(
            $traslado->cajaOrigenId()->equals($this->cajaOrigenId),
            'El traslado debe registrar la caja de origen correcta'
        );
        Assert::assertTrue(
            $traslado->cajaDestinoId()->equals($this->cajaDestinoId),
            'El traslado debe registrar la caja de destino correcta'
        );
        Assert::assertTrue(
            $traslado->estado()->equals(EstadoTraslado::Confirmado),
            'El traslado debe estar en estado confirmado'
        );
    }

    #[Then('el estado del espécimen debe reflejar "Reubicado"')]
    public function elEstadoDelEspécimenDebeReflejarReubicado(): void
    {
        Assert::assertNotNull($this->ultimaReubicacionOutput);
        Assert::assertSame(
            'reubicado',
            $this->ultimaReubicacionOutput->estadoEspecimen(),
            'El output debe reportar el espécimen como "reubicado"'
        );
    }

    // ==========================================
    // ESCENARIO: Intento de traslado a contenedor con familia taxonómica incompatible
    // ==========================================

    #[Given('que existe un espécimen de una familia taxonómica específica en una caja de origen')]
    public function queExisteUnEspécimenDeUnaFamiliaTaxonómicaEspecíficaEnUnaCajaDeOrigen(): void
    {
        $cajaOrigen = $this->sembrarCajaBase(
            codigo: 'CAJA-FAM-ORIGEN-001',
            familia: 'Nymphalidae',
            especimenesActuales: 1,
        );
        $this->cajaOrigenId = $cajaOrigen->id();

        $especimen = EspecimenEnCaja::asignar(
            id: $this->especimenRepo->nextIdentity(),
            especimenCodigoExterno: 'ESP-FAM-001',
            cajaId: $cajaOrigen->id(),
        );
        $this->especimenRepo->guardar($especimen);
        $this->especimenCodigo = 'ESP-FAM-001';

        $especimenPersistido = $this->especimenRepo->buscarPorCodigoExterno('ESP-FAM-001');
        Assert::assertNotNull($especimenPersistido, 'El espécimen debe estar persistido');
        Assert::assertTrue(
            $especimenPersistido->cajaId()->equals($cajaOrigen->id()),
            'El espécimen debe pertenecer a la caja de origen'
        );
    }

    #[Given('la caja de destino está configurada para una familia taxonómica diferente')]
    public function laCajaDeDestinoEstáConfiguradaParaUnaFamiliaTaxonómicaDiferente(): void
    {
        Assert::assertNotNull($this->cajaOrigenId);

        $cajaOrigen = $this->cajaRepo->buscarPorId($this->cajaOrigenId);
        Assert::assertNotNull($cajaOrigen);

        $familiaDestino = 'Papilionidae';
        Assert::assertNotSame(
            $cajaOrigen->familiaTaxonomicaId(),
            $familiaDestino,
            'La familia de destino debe ser diferente a la de origen para este escenario'
        );

        $cajaDestino = $this->sembrarCajaBase(
            codigo: 'CAJA-FAM-DEST-001',
            familia: $familiaDestino,
            especimenesActuales: 0,
        );
        $this->cajaDestinoId = $cajaDestino->id();

        Assert::assertNotSame(
            $cajaOrigen->familiaTaxonomicaId(),
            $cajaDestino->familiaTaxonomicaId(),
            'Las familias taxonómicas de origen y destino deben ser diferentes'
        );
    }

    #[Then('se debe mostrar un mensaje de error "Incompatibilidad Taxonómica"')]
    public function seDebeMostrarUnMensajeDeErrorIncompatibilidadTaxonómica(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba una excepción de dominio por incompatibilidad taxonómica'
        );
        Assert::assertStringContainsString(
            'Incompatibilidad Taxonómica',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debe indicar incompatibilidad taxonómica'
        );
    }

    // ==========================================
    // ESQUEMA: Consulta del historial de custodia de un espécimen
    // ==========================================

    #[Given('/^que existe un espécimen con (.+)$/u')]
    public function queExisteUnEspécimenCon(string $condicion): void
    {
        $cajaOrigen = $this->sembrarCajaBase(
            codigo: 'CAJA-HIST-001',
            familia: 'Nymphalidae',
            especimenesActuales: 1,
        );
        $this->cajaOrigenId = $cajaOrigen->id();

        $especimen = EspecimenEnCaja::asignar(
            id: $this->especimenRepo->nextIdentity(),
            especimenCodigoExterno: 'ESP-HIST-001',
            cajaId: $cajaOrigen->id(),
        );
        $this->especimenRepo->guardar($especimen);
        $this->especimenCodigo = 'ESP-HIST-001';

        if (str_contains($condicion, 'reubicaciones previas')) {
            // Sembrar traslados históricos confirmados para este espécimen
            $cajaAnterior = $this->sembrarCajaBase(
                codigo: 'CAJA-HIST-PREV-001',
                familia: 'Nymphalidae',
                especimenesActuales: 0,
            );

            $traslado = TrasladoEspecimen::registrar(
                id: $this->trasladoRepo->nextIdentity(),
                especimenCodigoExterno: 'ESP-HIST-001',
                cajaOrigenId: $cajaAnterior->id(),
                cajaDestinoId: $cajaOrigen->id(),
                motivo: 'reubicacion',
                iniciadorId: 'curador-001',
            );
            $traslado->confirmar();
            $this->trasladoRepo->guardar($traslado);

            $trasladoPersistido = $this->trasladoRepo->buscarPorEspecimenYCajas(
                especimenCodigo: 'ESP-HIST-001',
                cajaOrigenId: $cajaAnterior->id(),
                cajaDestinoId: $cajaOrigen->id(),
            );
            Assert::assertNotNull($trasladoPersistido, 'El traslado histórico debe estar persistido');
            Assert::assertTrue(
                $trasladoPersistido->estado()->equals(EstadoTraslado::Confirmado),
                'El traslado histórico debe estar confirmado'
            );
        }
        // Si la condición es "sin reubicaciones", no se crean traslados — historial vacío
    }

    #[When('el curador solicita el historial de custodia del espécimen')]
    public function elCuradorSolicitaElHistorialDeCustodiaDelEspécimen(): void
    {
        Assert::assertNotNull($this->especimenCodigo, 'Se requiere un espécimen en estado previo');

        try {
            $this->ultimoHistorialOutput = $this->historialHandler->handle(
                new ConsultarHistorialCustodiaEspecimenInput(
                    especimenCodigoExterno: $this->especimenCodigo,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('/^el historial es retornado (.+)$/u')]
    public function elHistorialEsRetornado(string $resultado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'No se esperaba excepción al consultar el historial'
        );
        Assert::assertNotNull($this->ultimoHistorialOutput, 'El handler debe retornar una respuesta');

        if (str_contains($resultado, 'movimientos en orden cronológico')) {
            Assert::assertNotEmpty(
                $this->ultimoHistorialOutput->traslados(),
                'El historial debe contener al menos un movimiento'
            );

            $traslados = $this->ultimoHistorialOutput->traslados();
            for ($i = 1; $i < count($traslados); $i++) {
                Assert::assertGreaterThanOrEqual(
                    $traslados[$i - 1]->iniciadoEn()->timestamp,
                    $traslados[$i]->iniciadoEn()->timestamp,
                    'Los traslados deben estar ordenados cronológicamente (más antiguo primero)'
                );
            }
        }

        if (str_contains($resultado, 'vacío')) {
            Assert::assertEmpty(
                $this->ultimoHistorialOutput->traslados(),
                'El historial debe estar vacío para un espécimen sin reubicaciones previas'
            );
        }
    }

    // ==========================================
    // Factory Methods
    // ==========================================

    private function sembrarCajaBase(
        string $codigo,
        string $familia,
        int $especimenesActuales,
    ): Caja {
        $caja = Caja::crear(
            id: $this->cajaRepo->nextIdentity(),
            codigo: $codigo,
            familiaTaxonomicaId: $familia,
            especimenesActuales: $especimenesActuales,
        );
        $this->cajaRepo->guardar($caja);

        $persistida = $this->cajaRepo->buscarPorId($caja->id());
        Assert::assertNotNull($persistida, "La caja {$codigo} debe estar persistida");
        Assert::assertSame($familia, $persistida->familiaTaxonomicaId(), 'La familia de la caja debe coincidir');

        return $persistida;
    }
}
