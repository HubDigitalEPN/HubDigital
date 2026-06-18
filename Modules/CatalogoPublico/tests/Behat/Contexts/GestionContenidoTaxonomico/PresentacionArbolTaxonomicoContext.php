<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Behat\Contexts\GestionContenidoTaxonomico;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\ProveedorEspecimenesParaArbolPort;
use Modules\CatalogoPublico\Application\Ports\TransactionManagerPort;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoHandler;
use Modules\CatalogoPublico\Application\UseCases\ConstruirArbolTaxonomico\ConstruirArbolTaxonomicoInput;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionHandler;
use Modules\CatalogoPublico\Application\UseCases\ModificarConfiguracionDivulgacion\ModificarConfiguracionDivulgacionInput;
use Modules\CatalogoPublico\Domain\Entities\EspecimenDivulgable;
use Modules\CatalogoPublico\Domain\Repositories\EspecimenDivulgableRepositoryInterface;
use Modules\CatalogoPublico\Domain\ValueObjects\ConfiguracionVisibilidad;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Tests\Behat\Contexts\BaseContext;
use Modules\CatalogoPublico\Tests\Support\FakeTransactionManager;
use Modules\CatalogoPublico\Tests\Support\InMemoryEspecimenDivulgableRepository;
use Modules\CatalogoPublico\Tests\Support\InMemoryProveedorEspecimenesParaArbol;
use PHPUnit\Framework\Assert;

final class PresentacionArbolTaxonomicoContext extends BaseContext
{
    // ── Handlers ─────────────────────────────────────────────────────────────

    private ?ConstruirArbolTaxonomicoHandler $construirArbolHandler = null;

    private ?ModificarConfiguracionDivulgacionHandler $modificarConfiguracionHandler = null;

    // ── Estado del escenario ─────────────────────────────────────────────────

    /**
     * Especímenes sembrados en los antecedentes, indexados por occurrenceID.
     *
     * @var array<string, array{
     *     taxonomiaTaxonId: string,
     *     taxonomiaEspecimenId: string,
     *     occurrenceID: string,
     *     phylum: string,
     *     class: string,
     *     order: string,
     *     family: string,
     *     genus: string,
     *     specificEpithet: string,
     *     scientificName: string,
     * }>
     */
    private array $especimenesSembrados = [];

    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private InMemoryEspecimenDivulgableRepository $repoDivulgable;

    private InMemoryProveedorEspecimenesParaArbol $fakeArbolProveedor;

    // ── Inicialización por escenario ─────────────────────────────────────────

    #[BeforeScenario]
    public function initializeHandlers(): void
    {
        $this->repoDivulgable = new InMemoryEspecimenDivulgableRepository;
        self::$app->instance(EspecimenDivulgableRepositoryInterface::class, $this->repoDivulgable);

        $this->fakeArbolProveedor = new InMemoryProveedorEspecimenesParaArbol($this->repoDivulgable);
        self::$app->instance(ProveedorEspecimenesParaArbolPort::class, $this->fakeArbolProveedor);

        self::$app->instance(TransactionManagerPort::class, new FakeTransactionManager);

        $this->construirArbolHandler = $this->make(ConstruirArbolTaxonomicoHandler::class);
        $this->modificarConfiguracionHandler = $this->make(ModificarConfiguracionDivulgacionHandler::class);
        $this->especimenesSembrados = [];
        $this->ultimaRespuesta = null;
        $this->excepcionCapturada = null;
    }

    // =========================================================================
    // ANTECEDENTES
    // =========================================================================

    #[Given('que la tabla de divulgación contiene los siguientes especímenes con sus datos de jerarquía taxonómica:')]
    public function queLaTablaDeDivulgacionContieneEspecimenesConJerarquiaTaxonomica(TableNode $tabla): void
    {
        foreach ($tabla->getHash() as $fila) {
            Assert::assertNotEmpty($fila['occurrenceID'], 'occurrenceID es requerido en los antecedentes');
            Assert::assertNotEmpty($fila['phylum'], "phylum es requerido para el espécimen '{$fila['occurrenceID']}'");
            Assert::assertNotEmpty($fila['class'], "class es requerido para el espécimen '{$fila['occurrenceID']}'");
            Assert::assertNotEmpty($fila['order'], "order es requerido para el espécimen '{$fila['occurrenceID']}'");
            Assert::assertNotEmpty($fila['family'], "family es requerido para el espécimen '{$fila['occurrenceID']}'");
            Assert::assertNotEmpty($fila['genus'], "genus es requerido para el espécimen '{$fila['occurrenceID']}'");
            Assert::assertNotEmpty($fila['specificEpithet'], "specificEpithet es requerido para el espécimen '{$fila['occurrenceID']}'");
            Assert::assertNotEmpty($fila['scientificName'], "scientificName es requerido para el espécimen '{$fila['occurrenceID']}'");

            // Verificar que scientificName = genus + ' ' + specificEpithet (convención taxonómica)
            Assert::assertSame(
                $fila['genus'].' '.$fila['specificEpithet'],
                $fila['scientificName'],
                "scientificName de '{$fila['occurrenceID']}' debe ser la combinación de genus + specificEpithet"
            );

            $taxonId = (string) Str::uuid();
            $taxonomiaEspecimenId = (string) Str::uuid();

            // La jerarquía se siembra en el proveedor del árbol; la visibilidad
            // se resolverá dinámicamente contra el repositorio de divulgables.
            $jerarquia = JerarquiaTaxonomica::desde(
                phylum: $fila['phylum'],
                class: $fila['class'],
                order: $fila['order'],
                family: $fila['family'],
                genus: $fila['genus'],
                specificEpithet: $fila['specificEpithet'],
                scientificName: $fila['scientificName'],
            );

            $this->fakeArbolProveedor->agregar($fila['occurrenceID'], $jerarquia);
            $this->repoDivulgable->registrarOccurrence($fila['occurrenceID'], $taxonomiaEspecimenId);

            $this->especimenesSembrados[$fila['occurrenceID']] = [
                'taxonomiaTaxonId' => $taxonId,
                'taxonomiaEspecimenId' => $taxonomiaEspecimenId,
                'occurrenceID' => $fila['occurrenceID'],
                'phylum' => $fila['phylum'],
                'class' => $fila['class'],
                'order' => $fila['order'],
                'family' => $fila['family'],
                'genus' => $fila['genus'],
                'specificEpithet' => $fila['specificEpithet'],
                'scientificName' => $fila['scientificName'],
            ];
        }

        Assert::assertCount(
            count($tabla->getHash()),
            $this->especimenesSembrados,
            'No todos los especímenes del antecedente fueron sembrados correctamente'
        );
    }

    #[Given('/^la configuración de visibilidad para estos especímenes tiene habilitados los campos taxonómicos \(true\)$/')]
    public function laConfiguracionDeVisibilidadTieneHabilitadosLosCamposTaxonomicos(): void
    {
        Assert::assertNotEmpty(
            $this->especimenesSembrados,
            'No hay especímenes sembrados — el paso anterior debe ejecutarse primero'
        );

        $repo = $this->repoDivulgable;

        foreach ($this->especimenesSembrados as $occurrenceID => $datos) {
            $divulgable = EspecimenDivulgable::sincronizar(
                id: $repo->nextIdentity(),
                especimenId: $datos['taxonomiaEspecimenId'],
                configuracion: ConfiguracionVisibilidad::todosHabilitados(),
            );

            $repo->guardar($divulgable);

            $persistido = $repo->buscarPorOccurrenceID($occurrenceID);
            Assert::assertNotNull(
                $persistido,
                "Precondición: EspecimenDivulgable '{$occurrenceID}' no fue persistido en la tabla de divulgación"
            );
            Assert::assertTrue(
                $persistido->genusVisible(),
                "Precondición: genusVisible de '{$occurrenceID}' debe ser true en los antecedentes"
            );
            Assert::assertTrue(
                $persistido->scientificNameVisible(),
                "Precondición: scientificNameVisible de '{$occurrenceID}' debe ser true en los antecedentes"
            );
            Assert::assertTrue(
                $persistido->familyVisible(),
                "Precondición: familyVisible de '{$occurrenceID}' debe ser true en los antecedentes"
            );
        }
    }

    // =========================================================================
    // ESCENARIO: Generar la estructura del árbol taxonómico con nodos compartidos
    // =========================================================================

    #[When('el sistema procesa los especímenes para construir el árbol de divulgación')]
    public function elSistemaProcesaLosEspecimenesParaConstruirElArbol(): void
    {
        Assert::assertNotEmpty(
            $this->especimenesSembrados,
            'No hay especímenes sembrados — verifique los Antecedentes'
        );

        try {
            $this->ultimaRespuesta = ($this->construirArbolHandler)(
                new ConstruirArbolTaxonomicoInput
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('la estructura jerárquica resultante debe agrupar los nodos de la siguiente manera:')]
    public function laEstructuraJerarquicaDebeAgruparLosNodos(TableNode $tabla): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'La construcción del árbol falló con excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El handler ConstruirArbolTaxonomico no retornó respuesta');

        $nodosResultantes = $this->ultimaRespuesta->nodosJerarquicos;

        Assert::assertIsArray($nodosResultantes, 'nodosJerarquicos debe ser un array');
        Assert::assertNotEmpty($nodosResultantes, 'El árbol no debe estar vacío con especímenes visibles sembrados');

        foreach ($tabla->getHash() as $nodoEsperado) {
            $nivel = $nodoEsperado['nivel'];
            $taxon = $nodoEsperado['taxon'];
            $padre = $nodoEsperado['padre'];

            $encontrado = false;
            foreach ($nodosResultantes as $nodoResultante) {
                if (
                    $nodoResultante['nivel'] === $nivel
                    && $nodoResultante['taxon'] === $taxon
                    && $nodoResultante['padre'] === $padre
                ) {
                    $encontrado = true;
                    break;
                }
            }

            Assert::assertTrue(
                $encontrado,
                "Nodo no encontrado en el árbol — nivel: '{$nivel}', taxon: '{$taxon}', padre: '{$padre}'"
            );
        }
    }

    #[Then('/^las ramas finales \(Species\) deben formarse por la unión de Genus \+ Specific epithet:$/')]
    public function lasRamasFinalesDebenFormarseConGenusYSpecificEpithet(TableNode $tabla): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'Excepción previa inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'No hay respuesta del árbol taxonómico');

        $especiesResultantes = $this->ultimaRespuesta->especies;

        Assert::assertIsArray($especiesResultantes, 'especies debe ser un array');
        Assert::assertNotEmpty($especiesResultantes, 'Deben existir ramas finales (species) en el árbol');

        foreach ($tabla->getHash() as $especieEsperada) {
            $nombreEsperado = $especieEsperada['especie'];
            $genus = $especieEsperada['genus'];
            $specificEpithet = $especieEsperada['specificEpithet'];
            $padreGenus = $especieEsperada['padre (genus)'];

            // Verificar la convención: especie = genus + ' ' + specificEpithet
            Assert::assertSame(
                $genus.' '.$specificEpithet,
                $nombreEsperado,
                "La especie '{$nombreEsperado}' no respeta la convención genus+specificEpithet"
            );

            $encontrada = false;
            foreach ($especiesResultantes as $especieResultante) {
                if (
                    $especieResultante['especie'] === $nombreEsperado
                    && $especieResultante['genus'] === $genus
                    && $especieResultante['specificEpithet'] === $specificEpithet
                    && $especieResultante['padre'] === $padreGenus
                ) {
                    $encontrada = true;
                    break;
                }
            }

            Assert::assertTrue(
                $encontrada,
                "La especie '{$nombreEsperado}' (genus='{$genus}', epithet='{$specificEpithet}', padre='{$padreGenus}') no está en las ramas finales del árbol"
            );
        }

        // No deben existir especies en el árbol con nombres que no sean genus+epithet
        foreach ($especiesResultantes as $especieResultante) {
            $especieCombinada = $especieResultante['genus'].' '.$especieResultante['specificEpithet'];
            Assert::assertSame(
                $especieCombinada,
                $especieResultante['especie'],
                "La especie '{$especieResultante['especie']}' no respeta la regla genus+specificEpithet"
            );
        }
    }

    // =========================================================================
    // ESCENARIO: Asignar los especímenes correspondientes a las hojas del árbol
    // =========================================================================

    #[When('el usuario consulta el árbol taxonómico de divulgación')]
    public function elUsuarioConsultaElArbolTaxonomico(): void
    {
        Assert::assertNotEmpty(
            $this->especimenesSembrados,
            'No hay especímenes sembrados — verifique los Antecedentes'
        );

        try {
            $this->ultimaRespuesta = ($this->construirArbolHandler)(
                new ConstruirArbolTaxonomicoInput
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('debajo del nodo de cada especie se deben listar sus respectivos especímenes:')]
    public function debajoDeCadaEspecieSeDebemListarSusEspecimenes(TableNode $tabla): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'La consulta del árbol falló con excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El árbol taxonómico no retornó respuesta');

        $especimenesPorEspecie = $this->ultimaRespuesta->especimenesPorEspecie;

        Assert::assertIsArray($especimenesPorEspecie, 'especimenesPorEspecie debe ser un array');

        foreach ($tabla->getHash() as $fila) {
            $especie = $fila['especie'];
            $occurrenceIDsEsperados = array_map('trim', explode(',', $fila['especímenes_asociados']));

            Assert::assertArrayHasKey(
                $especie,
                $especimenesPorEspecie,
                "La especie '{$especie}' no tiene especímenes asociados en el árbol taxonómico"
            );

            foreach ($occurrenceIDsEsperados as $occurrenceID) {
                Assert::assertContains(
                    $occurrenceID,
                    $especimenesPorEspecie[$especie],
                    "El espécimen '{$occurrenceID}' no está listado bajo la especie '{$especie}' en el árbol"
                );
            }

            Assert::assertCount(
                count($occurrenceIDsEsperados),
                $especimenesPorEspecie[$especie],
                "La especie '{$especie}' tiene ".count($especimenesPorEspecie[$especie]).' especímenes pero se esperaban '.count($occurrenceIDsEsperados)
            );
        }
    }

    // =========================================================================
    // ESCENARIO: Exclusión de especímenes no visibles en el árbol
    // =========================================================================

    #[Given("que se cambia la configuración de divulgación y el espécimen :occurrenceID tiene 'genusVisible' o 'scientificNameVisible' en false")]
    public function queSeOcultanLosCamposTaxonomicosPrincipalesDelEspecimen(string $occurrenceID): void
    {
        Assert::assertArrayHasKey(
            $occurrenceID,
            $this->especimenesSembrados,
            "El espécimen '{$occurrenceID}' no está en los antecedentes — no se puede modificar su visibilidad"
        );

        $repo = $this->repoDivulgable;
        $divulgableActual = $repo->buscarPorOccurrenceID($occurrenceID);

        Assert::assertNotNull(
            $divulgableActual,
            "El espécimen '{$occurrenceID}' no existe en la tabla de divulgación — debe estar previamente configurado en los Antecedentes"
        );

        // Precondición: al menos uno está habilitado antes del cambio
        Assert::assertTrue(
            $divulgableActual->genusVisible() || $divulgableActual->scientificNameVisible(),
            "Precondición violada: genusVisible y scientificNameVisible de '{$occurrenceID}' ya están false — el cambio no sería significativo"
        );

        $configuracionOculta = ConfiguracionVisibilidad::desde(
            array_merge(
                ConfiguracionVisibilidad::todosHabilitados()->toArray(),
                [
                    'genusVisible' => false,
                    'scientificNameVisible' => false,
                ]
            )
        );

        $excepcionModificacion = null;

        try {
            $this->modificarConfiguracionHandler->handle(
                new ModificarConfiguracionDivulgacionInput(
                    especimenes: [[
                        'occurrenceID' => $occurrenceID,
                        'configuracion' => $configuracionOculta->toArray(),
                    ]]
                )
            );
        } catch (\Throwable $e) {
            $excepcionModificacion = $e;
        }

        Assert::assertNull(
            $excepcionModificacion,
            "La modificación de visibilidad de '{$occurrenceID}' falló inesperadamente: ".$excepcionModificacion?->getMessage()
        );

        // Verificar que el cambio quedó persistido correctamente
        $divulgableActualizado = $repo->buscarPorOccurrenceID($occurrenceID);
        Assert::assertNotNull($divulgableActualizado, "No se encontró '{$occurrenceID}' tras la modificación de visibilidad");
        Assert::assertFalse(
            $divulgableActualizado->genusVisible(),
            "genusVisible de '{$occurrenceID}' debe ser false tras el cambio"
        );
        Assert::assertFalse(
            $divulgableActualizado->scientificNameVisible(),
            "scientificNameVisible de '{$occurrenceID}' debe ser false tras el cambio"
        );
    }

    #[When('el sistema renderiza el árbol taxonómico para el público')]
    public function elSistemaRenderizaElArbolParaElPublico(): void
    {
        try {
            $this->ultimaRespuesta = ($this->construirArbolHandler)(
                new ConstruirArbolTaxonomicoInput
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    #[Then('el nodo de la especie :especie y su espécimen :occurrenceID no deben aparecer en el árbol')]
    public function elNodoDeLaEspecieYSuEspecimenNoDbenAparecer(string $especie, string $occurrenceID): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'El renderizado del árbol falló con excepción inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'El árbol taxonómico no retornó respuesta');

        $especiesResultantes = $this->ultimaRespuesta->especies;
        $especimenesPorEspecie = $this->ultimaRespuesta->especimenesPorEspecie;

        // La especie no debe aparecer en las ramas finales
        $especieEncontrada = false;
        foreach ($especiesResultantes as $especieResultante) {
            if ($especieResultante['especie'] === $especie) {
                $especieEncontrada = true;
                break;
            }
        }

        Assert::assertFalse(
            $especieEncontrada,
            "La especie '{$especie}' no debe aparecer en el árbol cuando genusVisible o scientificNameVisible están deshabilitados"
        );

        // El espécimen no debe aparecer bajo ninguna especie
        foreach ($especimenesPorEspecie as $especieKey => $ocurrences) {
            Assert::assertNotContains(
                $occurrenceID,
                $ocurrences,
                "El espécimen '{$occurrenceID}' no debe aparecer bajo ninguna especie en el árbol (encontrado bajo '{$especieKey}')"
            );
        }
    }

    #[Then('/^los nodos superiores huérfanos sin otros hijos divulgados \(como la familia "([^"]+)" si no tiene más miembros\) deben quedar ocultos$/')]
    public function losNodosSuperioresHuerfanosDbenQuedarOcultos(string $familia): void
    {
        Assert::assertNull(
            $this->excepcionCapturada,
            'Excepción previa inesperada: '.$this->excepcionCapturada?->getMessage()
        );
        Assert::assertNotNull($this->ultimaRespuesta, 'No hay respuesta del árbol taxonómico');

        $nodosJerarquicos = $this->ultimaRespuesta->nodosJerarquicos;

        Assert::assertIsArray($nodosJerarquicos, 'nodosJerarquicos debe ser un array');

        // La familia no debe aparecer si todos sus hijos (géneros/especies) están ocultos
        $familiaEncontrada = false;
        foreach ($nodosJerarquicos as $nodo) {
            if ($nodo['nivel'] === 'family' && $nodo['taxon'] === $familia) {
                $familiaEncontrada = true;
                break;
            }
        }

        Assert::assertFalse(
            $familiaEncontrada,
            "La familia '{$familia}' debe quedar oculta en el árbol cuando no tiene hijos (géneros/especies) con visibilidad habilitada"
        );

        // Verificar que sí siguen apareciendo familias con hijos visibles (árbol no quedó vacío)
        $algunaFamiliaVisible = false;
        foreach ($nodosJerarquicos as $nodo) {
            if ($nodo['nivel'] === 'family') {
                $algunaFamiliaVisible = true;
                break;
            }
        }

        Assert::assertTrue(
            $algunaFamiliaVisible,
            'El árbol no debe quedar completamente vacío — deben existir familias con hijos visibles'
        );
    }
}
