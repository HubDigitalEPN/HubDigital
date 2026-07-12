<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Behat\Contexts\ChatBot;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\ClasificadorIntencionPort;
use Modules\CatalogoPublico\Application\Ports\DatosEspecimenProveedor;
use Modules\CatalogoPublico\Application\Ports\GeneradorRespuestaChatBotPort;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesPort;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotInput;
use Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot\ConsultarChatBotOutput;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ChatBotMensajes;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;
use Modules\CatalogoPublico\Domain\ValueObjects\EntidadesExtraidas;
use Modules\CatalogoPublico\Domain\ValueObjects\IntencionConsulta;
use Modules\CatalogoPublico\Tests\Behat\Contexts\BaseContext;
use Modules\CatalogoPublico\Tests\Support\FakeClasificadorIntencion;
use Modules\CatalogoPublico\Tests\Support\FakeGeneradorRespuestaChatBot;
use Modules\CatalogoPublico\Tests\Support\FakeProveedorEspecimenesPort;
use Modules\CatalogoPublico\Tests\Support\InMemoryEspecimenDivulgableRepository;
use PHPUnit\Framework\Assert;

final class ConsultaEnLenguajeNaturalContext extends BaseContext
{
    private FakeProveedorEspecimenesPort $fakeProveedor;

    private InMemoryEspecimenDivulgableRepository $repoDivulgable;

    private FakeClasificadorIntencion $fakeClasificador;

    private FakeGeneradorRespuestaChatBot $fakeGenerador;

    private ?ConsultarChatBotHandler $handler = null;

    private string $pregunta = '';

    private ?ConsultarChatBotOutput $ultimaRespuesta = null;

    /** @var array<string, DatosEspecimenProveedor> occurrenceID => datos internos */
    private array $especimenesInternos = [];

    #[BeforeScenario]
    public function initializeHandlers(): void
    {
        $this->fakeProveedor = new FakeProveedorEspecimenesPort;
        self::$app->instance(ProveedorEspecimenesPort::class, $this->fakeProveedor);

        $this->repoDivulgable = new InMemoryEspecimenDivulgableRepository;
        self::$app->instance(EspecimenDivulgableRepositoryInterface::class, $this->repoDivulgable);

        $this->fakeClasificador = new FakeClasificadorIntencion;
        self::$app->instance(ClasificadorIntencionPort::class, $this->fakeClasificador);

        $this->fakeGenerador = new FakeGeneradorRespuestaChatBot;
        self::$app->instance(GeneradorRespuestaChatBotPort::class, $this->fakeGenerador);

        $this->handler = $this->make(ConsultarChatBotHandler::class);
        $this->pregunta = '';
        $this->ultimaRespuesta = null;
        $this->especimenesInternos = [];
    }

    // =========================================================================
    // ANTECEDENTES
    // =========================================================================

    #[Given('que existen los siguientes especímenes ya divulgados')]
    public function queExistenLosSiguientesEspecimenesYaDivulgados(TableNode $table): void
    {
        foreach ($table->getHash() as $fila) {
            Assert::assertNotEmpty($fila['occurrenceID'], 'occurrenceID es requerido en los antecedentes');

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

            $this->repoDivulgable->guardar(EspecimenDivulgable::sincronizar(
                id: $this->repoDivulgable->nextIdentity(),
                especimenId: $especimenId,
                configuracion: ConfiguracionVisibilidad::todosHabilitados(),
            ));
        }
    }

    // =========================================================================
    // ESCENARIO 1: Recuperación precisa por atributos
    // =========================================================================

    #[Given('/^que el visitante ingresa la pregunta "(.+)"$/u')]
    public function queElVisitanteIngresaLaPregunta(string $pregunta): void
    {
        $this->pregunta = $pregunta;
        $this->fakeClasificador->registrar(
            $pregunta,
            IntencionConsulta::dentroDelDominio($this->deducirEntidadesDesde($pregunta)),
        );
    }

    #[When('el motor de búsqueda recupera el contexto de la base de datos')]
    public function elMotorDeBusquedaRecuperaElContextoDeLaBaseDeDatos(): void
    {
        $this->ultimaRespuesta = $this->handler->handle(new ConsultarChatBotInput($this->pregunta));
    }

    #[Then('/^el contexto del LLM incluye el espécimen "(.+)"$/u')]
    public function elContextoDelLlmIncluyeElEspecimen(string $occurrenceID): void
    {
        $contexto = $this->contextoCapturado();

        Assert::assertTrue(
            $contexto->incluyeOccurrenceID($occurrenceID),
            "El contexto del LLM no incluye el espécimen '{$occurrenceID}'. IDs presentes: ".implode(', ', $contexto->occurrenceIDs()),
        );
    }

    #[Then('/^el contexto del LLM incluye la localidad "(.+)" del espécimen$/u')]
    public function elContextoDelLlmIncluyeLaLocalidadDelEspecimen(string $localidad): void
    {
        $this->afirmarCampoPresenteEnContexto('localityName', $localidad);
    }

    // =========================================================================
    // ESCENARIO 2: Síntesis de espécimen específico
    // =========================================================================

    #[Given('/^que el visitante realiza la pregunta "Sintetiza la información del espécimen (.+)"$/u')]
    public function queElVisitanteRealizaLaPreguntaSintetizaLaInformacionDelEspecimen(string $occurrenceID): void
    {
        $this->pregunta = "Sintetiza la información del espécimen {$occurrenceID}";
        $this->fakeClasificador->registrar(
            $this->pregunta,
            IntencionConsulta::dentroDelDominio(EntidadesExtraidas::desde(occurrenceID: $occurrenceID)),
        );
    }

    #[When('el motor de búsqueda recupera los datos tabulares del espécimen para el LLM')]
    public function elMotorDeBusquedaRecuperaLosDatosTabularesDelEspecimenParaElLlm(): void
    {
        $this->ultimaRespuesta = $this->handler->handle(new ConsultarChatBotInput($this->pregunta));
    }

    #[Then('/^el contexto del LLM incluye la familia "(.+)" del espécimen$/u')]
    public function elContextoDelLlmIncluyeLaFamiliaDelEspecimen(string $familia): void
    {
        $this->afirmarCampoPresenteEnContexto('family', $familia);
    }

    #[Then('/^el contexto del LLM incluye el método de recolección "(.+)"$/u')]
    public function elContextoDelLlmIncluyeElMetodoDeRecoleccion(string $metodo): void
    {
        $this->afirmarCampoPresenteEnContexto('samplingProtocol', $metodo);
    }

    // =========================================================================
    // ESCENARIO 3: Guardrail
    // =========================================================================

    #[Given('/^que el visitante realiza una pregunta fuera del dominio como "(.+)"$/u')]
    public function queElVisitanteRealizaUnaPreguntaFueraDelDominioComo(string $pregunta): void
    {
        $this->pregunta = $pregunta;
        $this->fakeClasificador->registrar($pregunta, IntencionConsulta::fueraDelDominio());
    }

    #[When('el clasificador de intención evalúa la pertinencia de la consulta')]
    public function elClasificadorDeIntencionEvaluaLaPertinenciaDeLaConsulta(): void
    {
        $this->ultimaRespuesta = $this->handler->handle(new ConsultarChatBotInput($this->pregunta));
    }

    #[Then('la consulta se marca como fuera de dominio y no se envía al LLM')]
    public function laConsultaSeMarcaComoFueraDeDominioYNoSeEnviaAlLlm(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'No se ejecutó ninguna consulta al chatbot');
        Assert::assertFalse($this->ultimaRespuesta->dentroDeDominio, 'La consulta debería haberse marcado como fuera de dominio');
        Assert::assertSame(0, $this->fakeGenerador->llamadas, 'El generador del LLM no debía haberse invocado en una consulta fuera de dominio');
        Assert::assertNull($this->fakeGenerador->ultimoContexto, 'No debía haberse construido contexto para el LLM');
    }

    #[Then('el chatbot responde con el mensaje predeterminado de dominio no soportado')]
    public function elChatbotRespondeConElMensajePredeterminadoDeDominioNoSoportado(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertSame(ChatBotMensajes::FUERA_DE_DOMINIO, $this->ultimaRespuesta->respuesta);
    }

    // =========================================================================
    // Utilidades
    // =========================================================================

    private function contextoCapturado(): ContextoLLM
    {
        Assert::assertNotNull($this->ultimaRespuesta, 'No se ejecutó ninguna consulta al chatbot');
        Assert::assertTrue($this->ultimaRespuesta->dentroDeDominio, 'La consulta debía marcarse como del dominio');
        Assert::assertNotNull($this->fakeGenerador->ultimoContexto, 'El generador no recibió ningún ContextoLLM');

        return $this->fakeGenerador->ultimoContexto;
    }

    private function afirmarCampoPresenteEnContexto(string $campo, string $valorEsperado): void
    {
        $contexto = $this->contextoCapturado();

        foreach ($contexto->especimenes as $especimen) {
            if ($especimen->tieneCampo($campo) && (string) $especimen->valor($campo) === $valorEsperado) {
                return;
            }
        }

        $observados = array_map(
            static fn ($e) => $e->occurrenceID.'/'.($e->tieneCampo($campo) ? (string) $e->valor($campo) : '∅'),
            $contexto->especimenes,
        );

        Assert::fail("Ningún espécimen del contexto tiene '{$campo}' = '{$valorEsperado}'. Observados: ".implode(', ', $observados));
    }

    private function deducirEntidadesDesde(string $pregunta): EntidadesExtraidas
    {
        if (preg_match('/g[eé]nero\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)/u', $pregunta, $m)) {
            return EntidadesExtraidas::desde(genero: $m[1]);
        }

        if (preg_match('/especie de\s+([A-ZÁÉÍÓÚÑ][A-Za-záéíóúñ\s]+?)(?:$|[.?!,])/u', $pregunta, $m)) {
            return EntidadesExtraidas::desde(localidad: trim($m[1]));
        }

        if (preg_match('/(EPN-\d+)/u', $pregunta, $m)) {
            return EntidadesExtraidas::desde(occurrenceID: $m[1]);
        }

        return EntidadesExtraidas::vacio();
    }
}
