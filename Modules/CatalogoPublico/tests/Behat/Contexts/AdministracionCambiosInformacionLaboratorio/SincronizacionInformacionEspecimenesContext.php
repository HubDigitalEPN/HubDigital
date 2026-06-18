<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Behat\Contexts\AdministracionCambiosInformacionLaboratorio;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada\ConsultarInformacionDivulgadaHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarInformacionDivulgada\ConsultarInformacionDivulgadaInput;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionHandler;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionInput;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesInput;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Tests\Behat\Contexts\BaseContext;
use Modules\CatalogoPublico\Tests\Support\FakeProveedorEspecimenesPort;
use Modules\CatalogoPublico\Tests\Support\FakeTransactionManager;
use Modules\CatalogoPublico\Tests\Support\InMemoryEspecimenDivulgableRepository;
use PHPUnit\Framework\Assert;

final class SincronizacionInformacionEspecimenesContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private ?SincronizarEspecimenesHandler $sincronizarHandler = null;

    private ?ModificarConfiguracionDivulgacionHandler $modificarConfiguracionHandler = null;

    private ?ConsultarInformacionDivulgadaHandler $consultarInformacionHandler = null;

    // ── Estado del escenario ─────────────────────────────────────────────────

    /** @var array<string, DatosEspecimenProveedor> Datos internos del Supplier, indexados por occurrenceID */
    private array $especimenesInternos = [];

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private FakeProveedorEspecimenesPort $fakeProveedor;

    private InMemoryEspecimenDivulgableRepository $repoDivulgable;

    // ── Inicialización por escenario ─────────────────────────────────────────

    #[BeforeScenario]
    public function initializeHandlers(): void
    {
        $this->repoDivulgable = new InMemoryEspecimenDivulgableRepository;
        self::$app->instance(EspecimenDivulgableRepositoryInterface::class, $this->repoDivulgable);

        $this->fakeProveedor = new FakeProveedorEspecimenesPort;
        self::$app->instance(ProveedorEspecimenesPort::class, $this->fakeProveedor);

        self::$app->instance(TransactionManagerPort::class, new FakeTransactionManager);

        $this->sincronizarHandler = $this->make(SincronizarEspecimenesHandler::class);
        $this->modificarConfiguracionHandler = $this->make(ModificarConfiguracionDivulgacionHandler::class);
        $this->consultarInformacionHandler = $this->make(ConsultarInformacionDivulgadaHandler::class);
        $this->especimenesInternos = [];
        $this->ultimaRespuesta = null;
        $this->excepcionCapturada = null;
    }

    // =========================================================================
    // ANTECEDENTES
    // =========================================================================

    #[Given('que existen los siguientes especímenes en la base de información interna del laboratorio:')]
    public function queExistenLosEspecimenesEnLaBaseInternaDelLaboratorio(TableNode $tabla): void
    {
        foreach ($tabla->getHash() as $fila) {
            Assert::assertNotEmpty($fila['occurrenceID'], 'occurrenceID es requerido en los antecedentes');
            Assert::assertNotEmpty($fila['scientificName'], 'scientificName es requerido en los antecedentes');
            Assert::assertNotEmpty($fila['occurrenceStatus'], 'occurrenceStatus es requerido en los antecedentes');

            // El especimenId identifica al espécimen interno; el repositorio InMemory
            // resuelve occurrenceID → especimenId con este mapeo registrado aquí.
            $especimenId = (string) Str::uuid();
            $this->repoDivulgable->registrarOccurrence($fila['occurrenceID'], $especimenId);

            $datos = new DatosEspecimenProveedor(
                especimenId: $especimenId,
                occurrenceId: $fila['occurrenceID'],
                scientificName: $fila['scientificName'],
                individualCount: (int) $fila['individualCount'],
                typeStatus: $fila['typeStatus'] !== '' ? $fila['typeStatus'] : null,
                typeNotes: $fila['typeNotes'] !== '' ? $fila['typeNotes'] : null,
                specimenNotes: $fila['specimenNotes'] !== '' ? $fila['specimenNotes'] : null,
                samplingProtocol: $fila['samplingProtocol'] !== '' ? $fila['samplingProtocol'] : null,
                recordedBy: $fila['recordedBy'] !== '' ? $fila['recordedBy'] : null,
                occurrenceStatus: $fila['occurrenceStatus'],
                family: $fila['family'] !== '' ? $fila['family'] : null,
                genus: $fila['genus'] !== '' ? $fila['genus'] : null,
                country: $fila['country'] !== '' ? $fila['country'] : null,
                localityName: $fila['localityName'] !== '' ? $fila['localityName'] : null,
                decimalLatitude: $fila['decimalLatitude'] !== '' ? (float) $fila['decimalLatitude'] : null,
                decimalLongitude: $fila['decimalLongitude'] !== '' ? (float) $fila['decimalLongitude'] : null,
            );

            $this->fakeProveedor->agregar($datos);
            $this->especimenesInternos[$fila['occurrenceID']] = $datos;
        }

        Assert::assertCount(
            count($tabla->getHash()),
            $this->especimenesInternos,
            'No todos los especímenes de antecedentes fueron registrados en el proveedor'
        );
    }

    // =========================================================================
    // ESCENARIO: Sincronizar nuevos especímenes con todos los datos divulgables habilitados
    // =========================================================================

    #[Given('que los siguientes especímenes no existen en la tabla de divulgación:')]
    public function queLosEspecimenesNoExistenEnLaTablaDeDivulgacion(TableNode $tabla): void
    {
        $repo = $this->repoDivulgable;

        foreach ($tabla->getHash() as $fila) {
            $occurrenceID = $fila['occurrenceID'];

            Assert::assertArrayHasKey(
                $occurrenceID,
                $this->especimenesInternos,
                "El espécimen '{$occurrenceID}' no existe en la base interna — revisa los Antecedentes"
            );

            $existente = $repo->buscarPorOccurrenceID($occurrenceID);
            Assert::assertNull(
                $existente,
                "Precondición violada: el espécimen '{$occurrenceID}' ya existe en la tabla de divulgación"
            );
        }
    }

    #[When('el curador sincroniza los siguientes especímenes para divulgación:')]
    public function elCuradorSincronizaLosEspecimenesParaDivulgacion(TableNode $tabla): void
    {
        $occurrenceIDs = array_column($tabla->getHash(), 'occurrenceID');

        foreach ($occurrenceIDs as $id) {
            Assert::assertArrayHasKey(
                $id,
                $this->especimenesInternos,
                "El espécimen '{$id}' no fue registrado en la base interna"
            );
        }

        $especimenesInput = array_map(
            fn (string $id) => ['occurrenceID' => $id, 'configuracion' => null],
            $occurrenceIDs
        );

        try {
            $this->ultimaRespuesta = $this->sincronizarHandler->handle(
                new SincronizarEspecimenesInput(
                    especimenes: $especimenesInput,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('los siguientes especímenes deben existir en la tabla de divulgación:')]
    public function losEspecimenesDbenExistirEnLaTablaDeDivulgacion(TableNode $tabla): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'La sincronización falló con excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler de sincronización no retornó ninguna respuesta');

        $repo = $this->repoDivulgable;

        foreach ($tabla->getHash() as $fila) {
            $occurrenceID = $fila['occurrenceID'];
            $divulgable = $repo->buscarPorOccurrenceID($occurrenceID);

            Assert::assertNotNull(
                $divulgable,
                "El espécimen '{$occurrenceID}' no fue creado en la tabla de divulgación tras la sincronización"
            );
        }
    }

    #[Then('los datos divulgables de los especímenes deben quedar habilitados:')]
    public function losDatosDivulgablesDebenQuedarHabilitados(TableNode $tabla): void
    {
        $repo = $this->repoDivulgable;

        $camposVisibilidad = [
            'occurrenceIDVisible', 'scientificNameVisible', 'individualCountVisible',
            'typeStatusVisible', 'typeNotesVisible', 'specimenNotesVisible',
            'samplingProtocolVisible', 'recordedByVisible', 'occurrenceStatusVisible',
            'familyVisible', 'genusVisible', 'countryVisible',
            'localityNameVisible', 'decimalLatitudeVisible', 'decimalLongitudeVisible',
        ];

        foreach ($tabla->getHash() as $fila) {
            $occurrenceID = $fila['occurrenceID'];
            $divulgable = $repo->buscarPorOccurrenceID($occurrenceID);

            Assert::assertNotNull($divulgable, "Espécimen '{$occurrenceID}' no encontrado en la tabla de divulgación");

            foreach ($camposVisibilidad as $campo) {
                $valorEsperado = filter_var($fila[$campo], FILTER_VALIDATE_BOOLEAN);
                Assert::assertSame(
                    $valorEsperado,
                    $divulgable->$campo(),
                    "El campo '{$campo}' del espécimen '{$occurrenceID}' debería ser ".($valorEsperado ? 'true' : 'false')
                );
            }
        }
    }

    // =========================================================================
    // ESCENARIO: Sincronizar nuevos especímenes especificando datos divulgables
    // =========================================================================

    #[When('el curador sincroniza los siguientes especímenes para divulgación con esta configuración:')]
    public function elCuradorSincronizaConConfiguracionEspecifica(TableNode $tabla): void
    {
        $especimenesInput = [];

        foreach ($tabla->getHash() as $fila) {
            $occurrenceID = $fila['occurrenceID'];

            Assert::assertArrayHasKey(
                $occurrenceID,
                $this->especimenesInternos,
                "El espécimen '{$occurrenceID}' no fue registrado en la base interna"
            );

            $especimenesInput[] = [
                'occurrenceID' => $occurrenceID,
                'configuracion' => $this->extraerConfiguracionVisibilidad($fila),
            ];
        }

        try {
            $this->ultimaRespuesta = $this->sincronizarHandler->handle(
                new SincronizarEspecimenesInput(
                    especimenes: $especimenesInput,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('los datos divulgables de los especímenes deben quedar configurados:')]
    public function losDatosDivulgablesDebenQuedarConfigurados(TableNode $tabla): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'La operación falló con excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');

        $repo = $this->repoDivulgable;

        foreach ($tabla->getHash() as $fila) {
            $occurrenceID = $fila['occurrenceID'];
            $divulgable = $repo->buscarPorOccurrenceID($occurrenceID);

            Assert::assertNotNull($divulgable, "Espécimen '{$occurrenceID}' no encontrado en la tabla de divulgación");

            foreach ($fila as $campo => $valorStr) {
                if ($campo === 'occurrenceID') {
                    continue;
                }

                $metodoVisibilidad = $campo.'Visible';
                $valorEsperado = filter_var($valorStr, FILTER_VALIDATE_BOOLEAN);

                Assert::assertSame(
                    $valorEsperado,
                    $divulgable->$metodoVisibilidad(),
                    "El campo '{$metodoVisibilidad}' del espécimen '{$occurrenceID}' debería ser ".($valorEsperado ? 'true' : 'false')
                );
            }
        }
    }

    // =========================================================================
    // ESCENARIO: Modificar los datos divulgables de especímenes ya sincronizados
    // =========================================================================

    #[Given('que los siguientes especímenes existen en la tabla de divulgación:')]
    public function queLosEspecimenesExistenEnLaTablaDeDivulgacion(TableNode $tabla): void
    {
        $repoDivulgable = $this->repoDivulgable;

        foreach ($tabla->getHash() as $fila) {
            $occurrenceID = $fila['occurrenceID'];
            $configuracion = $this->extraerConfiguracionVisibilidad($fila);

            Assert::assertArrayHasKey(
                $occurrenceID,
                $this->especimenesInternos,
                "El espécimen '{$occurrenceID}' no existe en la base interna — revisa los Antecedentes"
            );

            $divulgable = EspecimenDivulgable::sincronizar(
                id: $repoDivulgable->nextIdentity(),
                especimenId: $this->especimenesInternos[$occurrenceID]->especimenId,
                configuracion: ConfiguracionVisibilidad::desde($configuracion),
            );

            $repoDivulgable->guardar($divulgable);

            $persistido = $repoDivulgable->buscarPorOccurrenceID($occurrenceID);
            Assert::assertNotNull($persistido, "EspecimenDivulgable '{$occurrenceID}' no fue encontrado tras guardarlo");

            foreach ($configuracion as $campoVisible => $valorEsperado) {
                Assert::assertSame(
                    $valorEsperado,
                    $persistido->$campoVisible(),
                    "Precondición: '{$campoVisible}' de '{$occurrenceID}' debería ser ".($valorEsperado ? 'true' : 'false')
                );
            }
        }
    }

    #[When('el curador modifica la configuración de divulgación de los siguientes especímenes:')]
    public function elCuradorModificaLaConfiguracionDeDivulgacion(TableNode $tabla): void
    {
        $repoDivulgable = $this->repoDivulgable;
        $especimenesInput = [];

        foreach ($tabla->getHash() as $fila) {
            $occurrenceID = $fila['occurrenceID'];
            $configuracionNueva = $this->extraerConfiguracionVisibilidad($fila);

            $divulgableActual = $repoDivulgable->buscarPorOccurrenceID($occurrenceID);
            Assert::assertNotNull(
                $divulgableActual,
                "El espécimen '{$occurrenceID}' no existe en la tabla de divulgación — el escenario requiere que esté previamente sincronizado"
            );

            $hayAlgunCambio = false;
            foreach ($configuracionNueva as $campo => $nuevoValor) {
                if ($divulgableActual->$campo() !== $nuevoValor) {
                    $hayAlgunCambio = true;
                    break;
                }
            }
            Assert::assertTrue(
                $hayAlgunCambio,
                "La nueva configuración de '{$occurrenceID}' es idéntica a la actual — la modificación no sería significativa"
            );

            $especimenesInput[] = [
                'occurrenceID' => $occurrenceID,
                'configuracion' => $configuracionNueva,
            ];
        }

        try {
            $this->ultimaRespuesta = $this->modificarConfiguracionHandler->handle(
                new ModificarConfiguracionDivulgacionInput(
                    especimenes: $especimenesInput,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('al consultar la información divulgada del espécimen :occurrenceID solo debe estar disponible la información:')]
    public function alConsultarLaInformacionDivulgadaSoloDebenEstarDisponibles(string $occurrenceID, TableNode $tabla): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'La operación previa falló con excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );

        $consultaRespuesta = null;
        $excepcionConsulta = null;

        try {
            $consultaRespuesta = $this->consultarInformacionHandler->handle(
                new ConsultarInformacionDivulgadaInput(occurrenceID: $occurrenceID)
            );
        } catch (\Throwable $e) {
            $excepcionConsulta = $e;
        }

        Assert::assertNull(
            $excepcionConsulta,
            "La consulta de '{$occurrenceID}' falló: ".$excepcionConsulta?->getMessage()
        );
        Assert::assertNotNull($consultaRespuesta, "La consulta no retornó información para '{$occurrenceID}'");

        $hash = $tabla->getHash();
        $datosEsperados = array_combine(
            array_column($hash, 'dato'),
            array_column($hash, 'valor')
        );

        foreach ($datosEsperados as $dato => $valorEsperado) {
            Assert::assertArrayHasKey(
                $dato,
                $consultaRespuesta->datosVisibles,
                "El dato '{$dato}' no está disponible en la información divulgada de '{$occurrenceID}'"
            );
            Assert::assertSame(
                $valorEsperado,
                (string) $consultaRespuesta->datosVisibles[$dato],
                "El valor del dato '{$dato}' del espécimen '{$occurrenceID}' no coincide con el esperado"
            );
        }

        Assert::assertEqualsCanonicalizing(
            array_keys($datosEsperados),
            array_keys($consultaRespuesta->datosVisibles),
            "Los datos disponibles en la consulta no coinciden exactamente con los esperados para '{$occurrenceID}'"
        );
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $fila
     * @return array<string, bool>
     */
    private function extraerConfiguracionVisibilidad(array $fila): array
    {
        $config = ['occurrenceIDVisible' => true];
        foreach ($fila as $campo => $valorStr) {
            if ($campo === 'occurrenceID') {
                continue;
            }
            $config[$campo.'Visible'] = filter_var($valorStr, FILTER_VALIDATE_BOOLEAN);
        }

        return $config;
    }
}
