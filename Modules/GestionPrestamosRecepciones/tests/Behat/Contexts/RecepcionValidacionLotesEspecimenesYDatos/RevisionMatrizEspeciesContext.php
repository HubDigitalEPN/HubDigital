<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica\AceptarSugerenciaTaxonomicaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarSugerenciaTaxonomica\AceptarSugerenciaTaxonomicaInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies\CargarMatrizEspeciesHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarMatrizEspecies\CargarMatrizEspeciesInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\FinalizarEnvioSolicitudDeposito\FinalizarEnvioSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\FinalizarEnvioSolicitudDeposito\FinalizarEnvioSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico\JustificarHallazgoTaxonomicoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\JustificarHallazgoTaxonomico\JustificarHallazgoTaxonomicoInput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Events\HallazgoTaxonomicoJustificado;
use Modules\GestionPrestamosRecepciones\Domain\Events\MatrizEspeciesCargada;
use Modules\GestionPrestamosRecepciones\Domain\Events\SugerenciaTaxonomicaAceptada;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoMatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRegistroEspecimen;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MatrizEspeciesId;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\PassThroughTransactionManagerAdapter;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemoryMatrizEspeciesRepository;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Persistence\InMemorySolicitudDepositoRepository;
use PHPUnit\Framework\Assert;

/**
 * Contexto para: revision_matriz_especies.feature
 * Capability: RecepcionValidacionLotesEspecimenesYDatos
 *
 * Estrategia: 100% In-Memory. Cero persistencia real.
 */
final class RevisionMatrizEspeciesContext extends BaseContext
{
    // ── Repositorios In-Memory (acceso directo para @Given y @Then) ───────────

    private InMemorySolicitudDepositoRepository $solicitudRepo;

    private InMemoryMatrizEspeciesRepository $matrizRepo;

    private FakeEventPublisherAdapter $fakePublisher;

    // ── Handlers ─────────────────────────────────────────────────────────────

    private CargarMatrizEspeciesHandler $cargarMatrizHandler;

    private AceptarSugerenciaTaxonomicaHandler $aceptarSugerenciaHandler;

    private JustificarHallazgoTaxonomicoHandler $justificarHallazgoHandler;

    private FinalizarEnvioSolicitudDepositoHandler $finalizarEnvioHandler;

    // ── Estado del escenario ─────────────────────────────────────────────────

    private ?SolicitudDeposito $solicitudEnCurso = null;

    private ?string $matrizIdEnCurso = null;

    private ?string $registroIdEnCurso = null;

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private string $investigadorId = 'inv-matriz-001';

    private string $campoDwcRequerido = '';

    private string $especieIngresada = '';

    /** @var string[] */
    private array $camposExigidosPorCatalogo = [];

    // ── Constructor — inyecta In-Memory antes de resolver Handlers ────────────

    public function __construct()
    {
        // 0. Asegurar que Laravel está booteado antes de usar el container
        self::bootApp();

        // 1. Crear instancias In-Memory fresh para este escenario
        $this->solicitudRepo = new InMemorySolicitudDepositoRepository;
        $this->matrizRepo = new InMemoryMatrizEspeciesRepository;
        $this->fakePublisher = new FakeEventPublisherAdapter;

        // 2. Interceptar el container para que los Handlers reciban estas instancias
        self::$app->instance(SolicitudDepositoRepositoryInterface::class, $this->solicitudRepo);
        self::$app->instance(MatrizEspeciesRepositoryInterface::class, $this->matrizRepo);
        self::$app->instance(TransactionManagerPort::class, new PassThroughTransactionManagerAdapter);
        self::$app->instance(EventPublisherPort::class, $this->fakePublisher);

        // 3. Resolver Handlers — ya usan las instancias In-Memory
        $this->cargarMatrizHandler = $this->make(CargarMatrizEspeciesHandler::class);
        $this->aceptarSugerenciaHandler = $this->make(AceptarSugerenciaTaxonomicaHandler::class);
        $this->justificarHallazgoHandler = $this->make(JustificarHallazgoTaxonomicoHandler::class);
        $this->finalizarEnvioHandler = $this->make(FinalizarEnvioSolicitudDepositoHandler::class);
    }

    // ── Helpers de fixture ────────────────────────────────────────────────────

    /**
     * Crea y persiste una SolicitudDeposito en estado PendienteDeRevisionPorCuraduria
     * (documentación legal ya validada). Asigna $this->solicitudEnCurso.
     *
     * Los eventos generados por crear() y avanzarARevisionCuraduria() se drenan
     * para evitar que futuros Handlers los publiquen como si fueran propios.
     */
    private function sembrarSolicitudConDocumentacionValidada(string $tipoTramite = 'Depósito'): SolicitudDeposito
    {
        $solicitud = SolicitudDeposito::crear(
            id: $this->solicitudRepo->nextIdentity(),
            investigadorId: $this->investigadorId,
            tipoTramite: $tipoTramite,
        );
        $solicitud->avanzarARevisionCuraduria();
        $this->solicitudRepo->guardar($solicitud);
        $solicitud->pullEvents(); // Drenar eventos de fixture — no son eventos del @When

        $persistida = $this->solicitudRepo->buscarPorId($solicitud->id());
        Assert::assertNotNull($persistida, 'La solicitud base no fue persistida en el repositorio In-Memory');
        Assert::assertTrue(
            $persistida->estado()->equals(EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria),
            "La solicitud debería estar en 'Pendiente de Revisión por Curaduría' tras avanzar"
        );

        $this->solicitudEnCurso = $solicitud;

        return $solicitud;
    }

    /**
     * Crea y persiste una MatrizEspecies con un único registro de espécimen.
     * Establece $this->matrizIdEnCurso y $this->registroIdEnCurso.
     *
     * Los eventos generados por crear() se drenan para que los Handlers
     * solo publiquen sus propios eventos de dominio.
     *
     * @param  array<string, mixed>  $camposDwC  Campos DwC presentes en la matriz
     */
    private function sembrarMatrizConRegistro(
        string $nombreCientifico,
        array $camposDwC = ['scientificName' => 'present', 'decimalLatitude' => 'present', 'decimalLongitude' => 'present'],
        bool $noCatalogado = false,
    ): void {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso antes de crear la matriz');

        $matriz = MatrizEspecies::crear(
            id: $this->matrizRepo->nextIdentity(),
            solicitudId: (string) $this->solicitudEnCurso->id(),
            camposDwCPresentes: $camposDwC,
            tipoTramite: $this->solicitudEnCurso->tipoTramite(),
        );

        $this->registroIdEnCurso = $matriz->agregarRegistroEspecimen($nombreCientifico, $noCatalogado);
        $this->matrizRepo->guardar($matriz);
        $matriz->pullEvents(); // Drenar eventos de fixture — no son eventos del @When
        $this->matrizIdEnCurso = (string) $matriz->id();

        // Aserción de integridad del fixture
        $persistida = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($persistida, 'La matriz no fue persistida correctamente');
        Assert::assertTrue(
            $persistida->contieneEspecimen($nombreCientifico),
            "Se esperaba que la matriz contuviera el espécimen '{$nombreCientifico}'"
        );
        Assert::assertNotEmpty($this->registroIdEnCurso, 'El ID del registro de espécimen no puede estar vacío');
    }

    // ── Helper para asertar eventos publicados ────────────────────────────────

    /**
     * Retorna los tipos (FQCN) de todos los eventos publicados al FakePublisher.
     *
     * @return class-string[]
     */
    private function tiposDeEventosPublicados(): array
    {
        return array_map(
            static fn (object $e) => $e::class,
            $this->fakePublisher->publishedEvents()
        );
    }

    // =========================================================================
    // ANTECEDENTES (Background)
    // =========================================================================

    #[Given('que un investigador ha validado la documentación legal de su solicitud')]
    public function queUnInvestigadorHaValidadoLaDocumentacionLegalDeSuSolicitud(): void
    {
        // Siembra una solicitud Depósito con documentación ya validada.
        // Los escenarios de Donación sobreescribirán el tipo de trámite en su propio @Given.
        $this->sembrarSolicitudConDocumentacionValidada('Depósito');
    }

    #[Given('la solicitud está pendiente de la asignación de una matriz de especímenes')]
    public function laSolicitudEstaPendienteDeAsignacionDeMatriz(): void
    {
        Assert::assertNotNull(
            $this->solicitudEnCurso,
            'Se requiere una solicitud en curso del paso Dado anterior'
        );

        $matrizExistente = $this->matrizRepo->buscarPorSolicitudId((string) $this->solicitudEnCurso->id());
        Assert::assertNull(
            $matrizExistente,
            'Se esperaba que la solicitud NO tuviera ninguna matriz asignada en este punto del flujo'
        );
    }

    // =========================================================================
    // ESCENARIO: El investigador no puede finalizar la solicitud sin matriz
    // =========================================================================

    #[Given('que la solicitud no tiene una matriz de especímenes asociada')]
    public function queLaSolicitudNoTieneUnaMatrizDeEspecimenesAsociada(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');

        $matrizExistente = $this->matrizRepo->buscarPorSolicitudId((string) $this->solicitudEnCurso->id());
        Assert::assertNull(
            $matrizExistente,
            'Precondición fallida: la solicitud ya tiene una matriz asociada, pero el escenario requiere que no la tenga'
        );
    }

    #[When('el investigador intenta finalizar el envío de la solicitud')]
    public function elInvestigadorIntentaFinalizarElEnvioDeLaSolicitud(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');

        try {
            $this->ultimaRespuesta = ($this->finalizarEnvioHandler)(
                new FinalizarEnvioSolicitudDepositoInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    investigadorId: $this->investigadorId,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza el envío')]
    public function elSistemaRechazaElEnvio(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el sistema rechazara el envío, pero el handler completó sin lanzar excepción'
        );
        Assert::assertNull(
            $this->ultimaRespuesta,
            'Se esperaba que no hubiera respuesta exitosa cuando el envío es rechazado'
        );
    }

    #[Then('se le notifica que la matriz de especímenes es obligatoria')]
    public function seLaNotificaQueLaMatrizDeEspecimenesEsObligatoria(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba una excepción que notifique sobre la obligatoriedad de la matriz'
        );
        Assert::assertStringContainsStringIgnoringCase(
            'matriz',
            $this->excepcionCapturada->getMessage(),
            'El mensaje de error debería mencionar explícitamente la matriz de especímenes'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Validación de campos dinámicos Darwin Core (DwC)
    // =========================================================================

    #[Given('que el catálogo de curaduría exige el campo :campoDwc')]
    public function queElCatalogoDeCuraduriaExigeElCampo(string $campoDwc): void
    {
        Assert::assertNotEmpty($campoDwc, 'El nombre del campo DwC exigido no puede estar vacío');

        $this->campoDwcRequerido = $campoDwc;
        $this->camposExigidosPorCatalogo = [$campoDwc];
    }

    #[Given('la matriz ingresada carece del campo :campoDwc')]
    public function laMatrizIngresadaCareceDeCampo(string $campoDwc): void
    {
        Assert::assertNotEmpty($campoDwc, 'El campo DwC ausente no puede estar vacío');
        Assert::assertNotEmpty(
            $this->camposExigidosPorCatalogo,
            'Se requiere que el catálogo haya definido al menos un campo obligatorio'
        );

        // El campo ausente se comunica al handler vía $camposExigidosPorCatalogo
        // pero NO estará en los camposDwCPresentes al momento de cargar la matriz.
        // (ver @When correspondiente)
    }

    #[When('el investigador intenta cargar la matriz en el sistema')]
    public function elInvestigadorIntentaCargarLaMatrizEnElSistema(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotEmpty($this->campoDwcRequerido, 'Se requiere un campo DwC requerido definido');
        Assert::assertNotEmpty($this->camposExigidosPorCatalogo, 'Se requieren los campos exigidos por el catálogo');

        // Simular una matriz con campos básicos pero SIN el campo exigido por el catálogo
        $camposPresentes = ['scientificName' => 'Apis mellifera'];

        try {
            $this->ultimaRespuesta = ($this->cargarMatrizHandler)(
                new CargarMatrizEspeciesInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    camposDwCPresentes: $camposPresentes,
                    camposDwCExigidosPorCatalogo: $this->camposExigidosPorCatalogo,
                    registros: [['scientificName' => 'Apis mellifera']],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el sistema rechaza la carga')]
    public function elSistemaRechazaLaCarga(): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba que el sistema rechazara la carga por campos DwC faltantes, pero el handler completó sin error'
        );
        Assert::assertNull(
            $this->ultimaRespuesta,
            'No debería existir una respuesta exitosa cuando la carga es rechazada'
        );
    }

    #[Then('se notifica al investigador que :campoDwc es requerido por la colección')]
    public function seNotificaAlInvestigadorQueCampoEsRequeridoPorLaColeccion(string $campoDwc): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba una excepción notificando al investigador sobre el campo DwC requerido'
        );
        Assert::assertStringContainsString(
            $campoDwc,
            $this->excepcionCapturada->getMessage(),
            "El mensaje de error debería mencionar el campo '{$campoDwc}' como requerido por la colección"
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Validación taxonómica con sugerencias (@deposito)
    // =========================================================================

    #[Given('que la matriz de recolección contiene la especie :especieIngresada')]
    public function queLaMatrizDeRecoleccionContieneEspecie(string $especieIngresada): void
    {
        Assert::assertNotEmpty($especieIngresada, 'El nombre de la especie ingresada no puede estar vacío');

        $this->especieIngresada = $especieIngresada;
        $this->sembrarMatrizConRegistro($especieIngresada);
    }

    #[Given('el sistema detecta una posible inconsistencia tipográfica en el catálogo')]
    public function elSistemaDetectaInconsistenciaTypograficaEnElCatalogo(): void
    {
        // Este paso documenta la pre-condición: el sistema ya identificó una inconsistencia.
        // La detección real ocurre en el adaptador de validación taxonómica
        // (InMemoryValidacionTaxonomicaAdapter, pendiente de implementar con InventarioGestionColeccion).
        // Lo que este escenario testea es el comportamiento del sistema al ACEPTAR la sugerencia.
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotEmpty($this->especieIngresada, 'Se requiere una especie ingresada del paso anterior');
        Assert::assertNotNull($this->matrizIdEnCurso, 'Se requiere una matriz en curso del paso anterior');
    }

    #[When('el investigador acepta la sugerencia de corrección a :especieSugerida')]
    public function elInvestigadorAceptaLaSugerenciaDeCorreccion(string $especieSugerida): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotNull($this->matrizIdEnCurso, 'Se requiere una matriz en curso');
        Assert::assertNotNull($this->registroIdEnCurso, 'Se requiere un registro de espécimen en curso');
        Assert::assertNotEmpty($especieSugerida, 'La especie sugerida no puede estar vacía');
        Assert::assertNotSame(
            $this->especieIngresada,
            $especieSugerida,
            "La especie sugerida '{$especieSugerida}' debe ser distinta a la ingresada '{$this->especieIngresada}' para representar una corrección real"
        );

        try {
            $this->ultimaRespuesta = ($this->aceptarSugerenciaHandler)(
                new AceptarSugerenciaTaxonomicaInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    matrizId: $this->matrizIdEnCurso,
                    registroId: $this->registroIdEnCurso,
                    especieCorregida: $especieSugerida,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el registro se actualiza con el estado :estadoEsperado')]
    public function elRegistroSeActualizaConElEstado(string $estadoEsperado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->estadoRegistro->equals(EstadoRegistroEspecimen::from($estadoEsperado)),
            "Se esperaba estado de registro '{$estadoEsperado}', se obtuvo: {$this->ultimaRespuesta->estadoRegistro->value}"
        );
    }

    #[Then('el registro queda marcado como :estadoMarcado')]
    public function elRegistroQuedaMarcadoComo(string $estadoMarcado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotNull($this->matrizIdEnCurso, 'Se requiere un ID de matriz para verificar el registro');

        // Verificar el estado del registro en el repositorio In-Memory
        $matriz = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($matriz, 'La matriz no fue encontrada en el repositorio tras la corrección');
        Assert::assertTrue(
            $matriz->estadoRegistro($this->registroIdEnCurso)->equals(EstadoRegistroEspecimen::from($estadoMarcado)),
            "Se esperaba que el registro '{$this->registroIdEnCurso}' estuviera marcado como '{$estadoMarcado}'"
        );

        // Verificar que el Handler publicó el evento de dominio correspondiente
        Assert::assertContains(
            SugerenciaTaxonomicaAceptada::class,
            $this->tiposDeEventosPublicados(),
            'Se esperaba que el handler publicara el evento SugerenciaTaxonomicaAceptada'
        );
    }

    // =========================================================================
    // ESQUEMA DE ESCENARIO: Justificación de hallazgos taxonómicos (@deposito)
    // =========================================================================

    #[Given('que la matriz de recolección contiene la especie no registrada :especie')]
    public function queLaMatrizDeRecoleccionContieneEspecieNoRegistrada(string $especie): void
    {
        Assert::assertNotEmpty($especie, 'El nombre de la especie no catalogada no puede estar vacío');

        $this->especieIngresada = $especie;
        $this->sembrarMatrizConRegistro($especie, noCatalogado: true);

        $persistida = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertTrue(
            $persistida->especimenEsNoCatalogado($this->registroIdEnCurso),
            "Se esperaba que '{$especie}' estuviera marcado como espécimen no catalogado en el catálogo de referencia"
        );
    }

    #[When('el investigador justifica el hallazgo con el motivo :motivoJustificacion')]
    public function elInvestigadorJustificaElHallazgoConElMotivo(string $motivoJustificacion): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertNotNull($this->matrizIdEnCurso, 'Se requiere una matriz en curso');
        Assert::assertNotNull($this->registroIdEnCurso, 'Se requiere un registro de espécimen en curso');
        Assert::assertNotEmpty($motivoJustificacion, 'El motivo de justificación no puede estar vacío');

        try {
            $this->ultimaRespuesta = ($this->justificarHallazgoHandler)(
                new JustificarHallazgoTaxonomicoInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    matrizId: $this->matrizIdEnCurso,
                    registroId: $this->registroIdEnCurso,
                    motivoJustificacion: $motivoJustificacion,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el registro se etiqueta para :etiquetaEsperada')]
    public function elRegistroSeEtiquetaPara(string $etiquetaEsperada): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotNull($this->matrizIdEnCurso, 'Se requiere un ID de matriz para verificar la etiqueta');

        $matriz = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($matriz, 'La matriz no fue encontrada en el repositorio tras justificar el hallazgo');
        Assert::assertTrue(
            $matriz->estadoRegistro($this->registroIdEnCurso)->equals(EstadoRegistroEspecimen::from($etiquetaEsperada)),
            "Se esperaba que el registro estuviera etiquetado como '{$etiquetaEsperada}', ".
            "se obtuvo: {$matriz->estadoRegistro($this->registroIdEnCurso)->value}"
        );

        // Verificar que el Handler publicó el evento de dominio correspondiente
        Assert::assertContains(
            HallazgoTaxonomicoJustificado::class,
            $this->tiposDeEventosPublicados(),
            'Se esperaba que el handler publicara el evento HallazgoTaxonomicoJustificado'
        );
    }

    #[Then('la matriz asume el estado de :estadoMatrizEsperado')]
    public function laMatrizAsumeElEstadoDe(string $estadoMatrizEsperado): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotNull($this->matrizIdEnCurso, 'Se requiere un ID de matriz para verificar su estado');

        $matriz = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($matriz, 'La matriz no fue encontrada en el repositorio');
        Assert::assertTrue(
            $matriz->estado()->equals(EstadoMatrizEspecies::from($estadoMatrizEsperado)),
            "Se esperaba que la matriz asumiera el estado '{$estadoMatrizEsperado}', ".
            "se obtuvo: {$matriz->estado()->value}"
        );
    }

    // =========================================================================
    // ESCENARIO: Avance de solicitud con alertas taxonómicas justificadas (@deposito)
    // =========================================================================

    #[Given('que la matriz de recolección tiene el estado :estadoMatriz')]
    public function queLaMatrizDeRecoleccionTieneElEstado(string $estadoMatriz): void
    {
        Assert::assertNotEmpty($estadoMatriz, 'El estado de la matriz no puede estar vacío');

        // Sembrar la matriz con un espécimen no catalogado y justificarlo vía dominio
        // para que la matriz transite naturalmente a "Cargada con Alertas"
        $this->sembrarMatrizConRegistro('Morpho sp. nov.', noCatalogado: true);

        $matriz = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($matriz, 'La matriz no fue encontrada tras su creación');

        // Justificar el registro directamente vía dominio para establecer el estado precondición
        $matriz->justificarRegistro($this->registroIdEnCurso, 'Especie nueva — justificación de test');
        $this->matrizRepo->guardar($matriz);
        $matriz->pullEvents(); // Drenar eventos de fixture — no son eventos del @When

        // Aserción de precondición
        $verificacion = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($verificacion, 'La matriz no fue encontrada tras la justificación');
        Assert::assertTrue(
            $verificacion->estado()->equals(EstadoMatrizEspecies::from($estadoMatriz)),
            "Se esperaba que la matriz estuviera en estado '{$estadoMatriz}' tras justificar el hallazgo, ".
            "se obtuvo: {$verificacion->estado()->value}"
        );
    }

    #[Given('el investigador ha justificado todos los hallazgos no catalogados')]
    public function elInvestigadorHaJustificadoTodosLosHallazgosNoCatalogados(): void
    {
        Assert::assertNotNull($this->matrizIdEnCurso, 'Se requiere una matriz en curso del paso anterior');

        $matriz = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($matriz, 'La matriz no fue encontrada en el repositorio');

        // Justificar cualquier registro pendiente para garantizar la precondición
        foreach ($matriz->registrosPendientesDeJustificacion() as $registroId) {
            $matriz->justificarRegistro($registroId, 'Justificación canónica de test — todos los hallazgos');
        }
        $this->matrizRepo->guardar($matriz);
        $matriz->pullEvents(); // Drenar eventos de fixture — no son eventos del @When

        // Aserción de precondición
        $matrizActualizada = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($matrizActualizada);
        Assert::assertTrue(
            $matrizActualizada->todosLosHallazgosJustificados(),
            'Se esperaba que todos los hallazgos no catalogados estuvieran justificados antes de intentar finalizar el envío'
        );
    }

    #[Then('el sistema procesa el envío exitosamente')]
    public function elSistemaProcesaElEnvioExitosamente(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada al finalizar el envío: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->enviada,
            'Se esperaba que el campo "enviada" en el Output fuera true al procesar exitosamente'
        );
    }

    #[Then('las alertas se derivan a la bandeja de revisión manual de curaduría')]
    public function lasAlertasSeDerivanALaBandejaDeRevisionManualDeCuraduria(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertTrue(
            $this->ultimaRespuesta->alertasDerivadasACuraduria,
            'Se esperaba que las alertas taxonómicas justificadas fueran derivadas a la bandeja de revisión manual de curaduría'
        );
    }

    // =========================================================================
    // ESCENARIO: Aceptación automática de taxonomía por transferencia (@donacion)
    // =========================================================================

    #[Given('que la solicitud es una transferencia por :tipoTramite de una colección establecida')]
    public function queLaSolicitudEsUnaTransferenciaPorTipoTramite(string $tipoTramite): void
    {
        Assert::assertSame(
            'Donación',
            $tipoTramite,
            "Este escenario aplica exclusivamente a 'Donación', se recibió: '{$tipoTramite}'"
        );

        // Re-sembrar la solicitud como Donación — sobreescribe la del Antecedente (Depósito)
        $this->solicitudEnCurso = null;
        $this->sembrarSolicitudConDocumentacionValidada('Donación');

        Assert::assertTrue(
            $this->solicitudEnCurso->estado()->equals(EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria),
            'La solicitud Donación debería estar en Pendiente de Revisión por Curaduría'
        );
    }

    #[When('el investigador carga la matriz en formato Darwin Core')]
    public function elInvestigadorCargaLaMatrizEnFormatoDarwinCore(): void
    {
        Assert::assertNotNull($this->solicitudEnCurso, 'Se requiere una solicitud en curso');
        Assert::assertSame(
            'Donación',
            $this->solicitudEnCurso->tipoTramite(),
            'Este paso aplica exclusivamente a solicitudes de tipo Donación'
        );

        $registrosCanonicos = [
            ['scientificName' => 'Morpho peleides', 'decimalLatitude' => '-0.22', 'decimalLongitude' => '-78.52'],
        ];
        $camposPresentes = ['scientificName' => 'present', 'decimalLatitude' => 'present', 'decimalLongitude' => 'present'];

        try {
            $this->ultimaRespuesta = ($this->cargarMatrizHandler)(
                new CargarMatrizEspeciesInput(
                    solicitudId: (string) $this->solicitudEnCurso->id(),
                    camposDwCPresentes: $camposPresentes,
                    camposDwCExigidosPorCatalogo: [],
                    registros: $registrosCanonicos,
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }

        if ($this->ultimaRespuesta !== null) {
            $this->matrizIdEnCurso = $this->ultimaRespuesta->matrizId;
            Assert::assertNotEmpty(
                $this->matrizIdEnCurso,
                'El handler retornó éxito pero el matrizId del Output está vacío — los @Then posteriores dependen de este valor'
            );
        }
    }

    #[Then('el sistema omite la validación de inconsistencias tipográficas')]
    public function elSistemaOmiteLaValidacionDeInconsistenciasTipograficas(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'Se esperaba que el sistema NO lanzara errores de inconsistencia tipográfica para Donaciones: '.
            $this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertFalse(
            $this->ultimaRespuesta->validacionTipograficaAplicada,
            'Se esperaba que el campo "validacionTipograficaAplicada" fuera false para Donaciones'
        );
    }

    #[Then('se conserva la identificación taxonómica original de forma íntegra')]
    public function seConservaLaIdentificacionTaxonomicaOriginalDeFormaIntegra(): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El handler lanzó una excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler no retornó ninguna respuesta');
        Assert::assertNotNull($this->matrizIdEnCurso, 'Se requiere un ID de matriz para verificar la integridad');

        $matriz = $this->matrizRepo->buscarPorId(MatrizEspeciesId::from($this->matrizIdEnCurso));
        Assert::assertNotNull($matriz, 'La matriz no fue encontrada en el repositorio');
        Assert::assertTrue(
            $matriz->identificacionOriginalConservada(),
            'Se esperaba que la identificación taxonómica original se conservara íntegra (sin correcciones aplicadas)'
        );

        // Verificar que el Handler publicó el evento de dominio correspondiente
        Assert::assertContains(
            MatrizEspeciesCargada::class,
            $this->tiposDeEventosPublicados(),
            'Se esperaba que el handler publicara el evento MatrizEspeciesCargada'
        );
    }
}
