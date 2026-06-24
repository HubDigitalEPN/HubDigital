<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Behat\Contexts\GestionContenidoTaxonomico;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonHandler;
use Modules\CatalogoPublico\Application\UseCases\ConsultarGaleriaTaxon\ConsultarGaleriaTaxonInput;
use Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto\SeleccionarImagenPorDefectoHandler;
use Modules\CatalogoPublico\Application\UseCases\SeleccionarImagenPorDefecto\SeleccionarImagenPorDefectoInput;
use Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen\SubirImagenEspecimenHandler;
use Modules\CatalogoPublico\Application\UseCases\SubirImagenEspecimen\SubirImagenEspecimenInput;
use Modules\CatalogoPublico\Domain\Exceptions\AutorImagenInvalidoException;
use Modules\CatalogoPublico\Domain\Exceptions\NivelNoSobrescribibleException;
use Modules\CatalogoPublico\Domain\Services\PropagadorImagenPorDefecto;
use Modules\CatalogoPublico\Domain\ValueObjects\JerarquiaTaxonomica;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;
use Modules\CatalogoPublico\Infrastructure\Adapters\NullEventPublisher;
use Modules\CatalogoPublico\Tests\Behat\Contexts\BaseContext;
use Modules\CatalogoPublico\Tests\Support\FakeAlmacenamientoImagenes;
use Modules\CatalogoPublico\Tests\Support\FakeGeneradorMarcaAgua;
use Modules\CatalogoPublico\Tests\Support\FakeProveedorJerarquiaDeEspecimen;
use Modules\CatalogoPublico\Tests\Support\FakeTransactionManager;
use Modules\CatalogoPublico\Tests\Support\InMemoryImagenPorDefectoRepository;
use Modules\CatalogoPublico\Tests\Support\InMemoryImagenTaxonomicaRepository;
use PHPUnit\Framework\Assert;

final class GestionImagenesNivelTaxonomicoContext extends BaseContext
{
    private const AUTOR_NOMBRE = 'Ada';

    private const AUTOR_APELLIDO = 'Lovelace';

    // ── Handlers ─────────────────────────────────────────────────────────────
    private SubirImagenEspecimenHandler $subirHandler;

    private SeleccionarImagenPorDefectoHandler $seleccionarHandler;

    private ConsultarGaleriaTaxonHandler $galeriaHandler;

    // ── Fakes ────────────────────────────────────────────────────────────────
    private FakeProveedorJerarquiaDeEspecimen $jerarquias;

    private FakeAlmacenamientoImagenes $almacenamiento;

    private FakeGeneradorMarcaAgua $marcaAgua;

    private InMemoryImagenTaxonomicaRepository $repoImagenes;

    private InMemoryImagenPorDefectoRepository $repoDefectos;

    // ── Estado del escenario ─────────────────────────────────────────────────
    private mixed $ultimaRespuesta = null;

    private ?\Throwable $excepcionCapturada = null;

    private ?string $occurrencePendiente = null;

    private ?string $archivoPendiente = null;

    private string $subarbolActual = '';

    #[BeforeScenario]
    public function inicializar(): void
    {
        $this->jerarquias = new FakeProveedorJerarquiaDeEspecimen;
        $this->almacenamiento = new FakeAlmacenamientoImagenes;
        $this->marcaAgua = new FakeGeneradorMarcaAgua;
        $this->repoImagenes = new InMemoryImagenTaxonomicaRepository($this->jerarquias);
        $this->repoDefectos = new InMemoryImagenPorDefectoRepository;

        $tx = new FakeTransactionManager;
        $eventos = new NullEventPublisher;

        $this->subirHandler = new SubirImagenEspecimenHandler(
            $this->repoImagenes,
            $this->repoDefectos,
            $this->almacenamiento,
            $this->marcaAgua,
            $this->jerarquias,
            new PropagadorImagenPorDefecto,
            $tx,
            $eventos,
        );
        $this->seleccionarHandler = new SeleccionarImagenPorDefectoHandler($this->repoDefectos, $tx);
        $this->galeriaHandler = new ConsultarGaleriaTaxonHandler(
            $this->repoImagenes,
            $this->repoDefectos,
            $this->almacenamiento,
        );

        $this->ultimaRespuesta = null;
        $this->excepcionCapturada = null;
        $this->occurrencePendiente = null;
        $this->archivoPendiente = null;
        $this->subarbolActual = '';
    }

    // =========================================================================
    // ANTECEDENTES
    // =========================================================================

    #[Given('que existe la siguiente estructura taxonómica en la tabla de divulgación:')]
    public function queExisteLaSiguienteEstructuraTaxonomica(TableNode $table): void
    {
        foreach ($table->getHash() as $fila) {
            $jerarquia = JerarquiaTaxonomica::desde(
                phylum: $fila['phylum'],
                class: $fila['class'],
                order: $fila['order'],
                family: $fila['family'],
                genus: $fila['genus'],
                specificEpithet: $fila['specificEpithet'],
                scientificName: $fila['genus'].' '.$fila['specificEpithet'],
            );

            $this->jerarquias->agregar($fila['occurrenceID'], $jerarquia);
        }
    }

    // =========================================================================
    // ESCENARIO: Subir exige nombre y apellido
    // =========================================================================

    #[When('el curador sube la imagen "cephalotes_dorsal.jpg" al espécimen "EPN-0012"')]
    public function elCuradorSubeLaImagenDorsal(): void
    {
        $this->occurrencePendiente = 'EPN-0012';
        $this->archivoPendiente = 'cephalotes_dorsal.jpg';
    }

    #[When('proporciona el nombre "María" y el apellido "Gómez"')]
    public function proporcionaNombreYApellido(): void
    {
        $this->subir((string) $this->occurrencePendiente, (string) $this->archivoPendiente, 'María', 'Gómez');
    }

    #[Then('la imagen debe quedar vinculada al espécimen "EPN-0012"')]
    public function laImagenDebeQuedarVinculada(): void
    {
        Assert::assertNull($this->excepcionCapturada, 'La subida falló inesperadamente: '.$this->excepcionCapturada?->getMessage());
        Assert::assertNotNull($this->ultimaRespuesta, 'No hubo respuesta de la subida');
        Assert::assertSame('EPN-0012', $this->ultimaRespuesta->occurrenceID);
        Assert::assertNotNull(
            $this->repoImagenes->buscarPorNombreArchivo('cephalotes_dorsal.jpg'),
            'La imagen no quedó vinculada al espécimen'
        );
    }

    #[Then('se debe generar una marca de agua con el texto "María Gómez" sobre la imagen')]
    public function seDebeGenerarMarcaDeAgua(): void
    {
        Assert::assertTrue(
            $this->marcaAgua->seAplicoTexto('María Gómez'),
            'No se generó la marca de agua con el texto del autor'
        );
    }

    #[Then('la imagen debe registrar como autor a "María Gómez"')]
    public function laImagenDebeRegistrarAutor(): void
    {
        Assert::assertSame('María Gómez', $this->ultimaRespuesta->autor);

        $imagen = $this->repoImagenes->buscarPorNombreArchivo('cephalotes_dorsal.jpg');
        Assert::assertNotNull($imagen);
        Assert::assertSame('María Gómez', $imagen->nombreAutor());
    }

    // =========================================================================
    // ESCENARIO: Rechazar si falta nombre o apellido
    // =========================================================================

    #[When('el curador sube la imagen "cephalotes_lateral.jpg" al espécimen "EPN-0012"')]
    public function elCuradorSubeLaImagenLateral(): void
    {
        $this->occurrencePendiente = 'EPN-0012';
        $this->archivoPendiente = 'cephalotes_lateral.jpg';
    }

    #[When('no proporciona el nombre o el apellido de quien la sube')]
    public function noProporcionaNombreOApellido(): void
    {
        $this->subir((string) $this->occurrencePendiente, (string) $this->archivoPendiente, '', '');
    }

    #[Then('el sistema debe rechazar la subida')]
    public function elSistemaDebeRechazarLaSubida(): void
    {
        Assert::assertInstanceOf(
            AutorImagenInvalidoException::class,
            $this->excepcionCapturada,
            'Se esperaba que la subida sin autor fuera rechazada'
        );
    }

    #[Then('la imagen no debe almacenarse')]
    public function laImagenNoDebeAlmacenarse(): void
    {
        Assert::assertFalse(
            $this->almacenamiento->fueAlmacenada('cephalotes_lateral.jpg'),
            'La imagen no debió almacenarse'
        );
        Assert::assertNull(
            $this->repoImagenes->buscarPorNombreArchivo('cephalotes_lateral.jpg'),
            'La imagen no debió persistirse'
        );
    }

    // =========================================================================
    // ESCENARIO: Primera imagen del subárbol = defecto
    // =========================================================================

    #[Given('/^que aún no existen imágenes para el nivel "(.+)" con valor "(.+)"$/u')]
    public function queAunNoExistenImagenes(string $nivel, string $valor): void
    {
        Assert::assertNull(
            $this->repoDefectos->obtener($this->rango($nivel), $valor),
            "No debería existir portada para {$nivel}:{$valor}"
        );
        $this->subarbolActual = $valor;
    }

    #[When('/^se sube la imagen "(.+)" como la primera del subárbol de "(.+)"$/u')]
    public function seSubeLaImagenComoPrimera(string $primeraImagen, string $valor): void
    {
        $this->subarbolActual = $valor;
        $this->subir('EPN-0012', $primeraImagen, self::AUTOR_NOMBRE, self::AUTOR_APELLIDO);
    }

    #[When('/^posteriormente se sube la imagen "(.+)" al mismo subárbol$/u')]
    public function posteriormenteSeSubeLaImagen(string $segundaImagen): void
    {
        $this->subir('EPN-0012', $segundaImagen, self::AUTOR_NOMBRE, self::AUTOR_APELLIDO);
    }

    #[Then('/^la imagen por defecto del nivel "(.+)" para "(.+)" debe ser "(.+)"$/u')]
    public function laImagenPorDefectoDelNivelDebeSer(string $nivel, string $valor, string $esperada): void
    {
        Assert::assertSame(
            $esperada,
            $this->primeraImagenGaleria($this->rango($nivel), $valor),
            "La portada de {$nivel}:{$valor} no es la esperada"
        );
    }

    #[Then('/^al consultar la galería pública de "(.+)", la primera imagen devuelta debe ser "(.+)"$/u')]
    public function alConsultarLaGaleriaLaPrimeraImagenDebeSer(string $valor, string $esperada): void
    {
        Assert::assertSame(
            $esperada,
            $this->primeraImagenGaleria($this->nivelDesdeValor($valor), $valor),
            "La primera imagen de la galería de '{$valor}' no es la esperada"
        );
    }

    // =========================================================================
    // ESCENARIO: La imagen subida se vuelve defecto de especie y género
    // =========================================================================

    #[Given('que no existe ninguna imagen en el subárbol de "Atta cephalotes"')]
    public function queNoExisteNingunaImagenEnElSubarbol(): void
    {
        Assert::assertEmpty(
            $this->repoImagenes->listarPorSubarbol(RangoTaxonomico::Species, 'Atta cephalotes'),
            'El subárbol no debería tener imágenes'
        );
        $this->subarbolActual = 'Atta cephalotes';
    }

    #[When('el curador sube la imagen "cephalotes_pupa.jpg" al espécimen "EPN-0012"')]
    public function elCuradorSubeLaImagenPupa(): void
    {
        $this->subir('EPN-0012', 'cephalotes_pupa.jpg', self::AUTOR_NOMBRE, self::AUTOR_APELLIDO);
    }

    #[Then('la imagen por defecto debe ser "cephalotes_pupa.jpg" únicamente en los niveles:')]
    public function laImagenPorDefectoDebeSerPupaUnicamenteEn(TableNode $table): void
    {
        foreach ($table->getHash() as $fila) {
            Assert::assertSame(
                'cephalotes_pupa.jpg',
                $this->primeraImagenGaleria($this->rango($fila['nivel_taxón']), $fila['valor_taxón']),
                "La portada de {$fila['nivel_taxón']}:{$fila['valor_taxón']} debería ser cephalotes_pupa.jpg"
            );
        }

        // "únicamente": los niveles superiores a género no reciben portada.
        $superiores = [
            [RangoTaxonomico::Family, 'Formicidae'],
            [RangoTaxonomico::Order, 'Hymenoptera'],
            [RangoTaxonomico::Class_, 'Insecta'],
            [RangoTaxonomico::Phylum, 'Arthropoda'],
        ];

        foreach ($superiores as [$nivel, $valor]) {
            Assert::assertNull(
                $this->repoDefectos->obtener($nivel, $valor),
                "El nivel superior {$nivel->value}:{$valor} no debería tener portada"
            );
        }
    }

    // =========================================================================
    // ESCENARIO: Sobrescribir en género/especie
    // =========================================================================

    #[Given('/^que el subárbol de "(.+)" contiene las imágenes\:$/u')]
    public function queElSubarbolContieneLasImagenes(string $valor, TableNode $table): void
    {
        $this->subarbolActual = $valor;

        foreach ($table->getHash() as $fila) {
            $this->subir('EPN-0012', $fila['archivo_imagen'], self::AUTOR_NOMBRE, self::AUTOR_APELLIDO);
        }
    }

    #[Given('/^la imagen por defecto actual es "(.+)"$/u')]
    public function laImagenPorDefectoActualEs(string $imagenInicial): void
    {
        Assert::assertSame(
            $imagenInicial,
            $this->primeraImagenGaleria($this->nivelDesdeValor($this->subarbolActual), $this->subarbolActual),
            'La portada inicial no coincide con la precondición'
        );
    }

    #[When('/^el curador selecciona "(.+)" como imagen por defecto del nivel "(.+)" para "(.+)"$/u')]
    public function elCuradorSeleccionaComoPorDefecto(string $imagenElegida, string $nivel, string $valor): void
    {
        $this->seleccionar($imagenElegida, $nivel, $valor);
    }

    // =========================================================================
    // ESCENARIO: No admite imágenes en niveles superiores a género
    // =========================================================================

    #[Given('que el subárbol de "Atta cephalotes" contiene la imagen "cephalotes_pupa.jpg"')]
    public function queElSubarbolContieneLaImagenPupa(): void
    {
        $this->subarbolActual = 'Atta cephalotes';
        $this->subir('EPN-0012', 'cephalotes_pupa.jpg', self::AUTOR_NOMBRE, self::AUTOR_APELLIDO);
    }

    #[When('/^el curador intenta seleccionar "cephalotes_pupa\.jpg" como imagen por defecto del nivel "(.+)" para "(.+)"$/u')]
    public function elCuradorIntentaSeleccionarPupa(string $nivel, string $valor): void
    {
        $this->seleccionar('cephalotes_pupa.jpg', $nivel, $valor);
    }

    #[Then('el sistema debe rechazar la operación')]
    public function elSistemaDebeRechazarLaOperacion(): void
    {
        Assert::assertInstanceOf(
            NivelNoSobrescribibleException::class,
            $this->excepcionCapturada,
            'Se esperaba que la operación sobre un nivel superior fuera rechazada'
        );
    }

    #[Then('/^no debe existir imagen por defecto para el nivel "(.+)" con valor "(.+)"$/u')]
    public function noDebeExistirImagenPorDefecto(string $nivel, string $valor): void
    {
        Assert::assertNull(
            $this->repoDefectos->obtener($this->rango($nivel), $valor),
            "No debería existir portada para {$nivel}:{$valor}"
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function subir(string $occurrenceID, string $nombreArchivo, string $nombre, string $apellido): void
    {
        $this->ultimaRespuesta = null;
        $this->excepcionCapturada = null;

        try {
            $this->ultimaRespuesta = $this->subirHandler->handle(new SubirImagenEspecimenInput(
                occurrenceID: $occurrenceID,
                nombreArchivo: $nombreArchivo,
                contenido: 'bytes-de-'.$nombreArchivo,
                nombreAutor: $nombre,
                apellidoAutor: $apellido,
            ));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    private function seleccionar(string $nombreArchivo, string $nivel, string $valor): void
    {
        $this->ultimaRespuesta = null;
        $this->excepcionCapturada = null;

        $imagen = $this->repoImagenes->buscarPorNombreArchivo($nombreArchivo);
        Assert::assertNotNull($imagen, "La imagen '{$nombreArchivo}' no existe en el subárbol");

        try {
            $this->ultimaRespuesta = $this->seleccionarHandler->handle(new SeleccionarImagenPorDefectoInput(
                nivel: $this->rango($nivel)->value,
                valorTaxon: $valor,
                imagenId: $imagen->id()->toString(),
            ));
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    private function primeraImagenGaleria(RangoTaxonomico $nivel, string $valor): ?string
    {
        $salida = $this->galeriaHandler->handle(new ConsultarGaleriaTaxonInput($nivel->value, $valor));

        return $salida->imagenes[0]['nombreArchivo'] ?? null;
    }

    private function rango(string $etiqueta): RangoTaxonomico
    {
        return $etiqueta === 'specificEpithet'
            ? RangoTaxonomico::Species
            : RangoTaxonomico::desde($etiqueta);
    }

    private function nivelDesdeValor(string $valor): RangoTaxonomico
    {
        return str_contains($valor, ' ') ? RangoTaxonomico::Species : RangoTaxonomico::Genus;
    }
}
