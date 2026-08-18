<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts\InteroperabilidadEstados;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\ResolverTaxonomiaDwCPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\IngresarLoteDeposito\IngresarLoteDepositoOutput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EntidadDepositanteRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;
use Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEntidadDepositanteRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\InMemoryEspecimenRepository;
use Modules\InventarioGestionColeccion\Tests\Behat\Infrastructure\InMemory\ResolverTaxonomiaDwCEnMemoria;
use PHPUnit\Framework\Assert;

/**
 * Ejercita el traspaso de un depósito a la colección contra el caso de uso REAL.
 *
 * Existe porque el escenario equivalente del módulo de recepciones corre contra un doble
 * del puerto: afirmaba que los especímenes llegaban a la colección sin que nada de este
 * lado se ejecutara, así que ninguna de las reglas de aquí estaba cubierta. Los dos
 * defectos que más costaron —la corrección taxonómica que no viajaba y la idempotencia
 * atada a la posición de la fila— vivían justo en ese hueco.
 */
final class IngresoLoteDepositoContext extends BaseContext
{
    private const SOLICITUD_ID = '11111111-2222-3333-4444-555555555555';

    private const NUMERO = 'MEPN-INV-DEP-00007';

    private const REGISTRO_ID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    private const CODIGO_QR = 'LOTE-BEHAT01';

    private const PERMISO_RECOLECCION = '006-2025 RVS-FLO/FAU/DZ9/MAATE';

    private const PERMISO_MOVILIZACION = 'MAATE-DZ9-FAU-FLO-2025-018';

    /** Fila que el curador inserta al reordenar la matriz. */
    private const REGISTRO_ID_ANADIDO = 'ffffffff-0000-1111-2222-333333333333';

    private EspecimenRepositoryInterface $especimenRepo;

    private EntidadDepositanteRepositoryInterface $entidadRepo;

    private ResolverTaxonomiaDwCEnMemoria $resolverTaxonomia;

    private IngresarLoteDepositoHandler $ingresarHandler;

    /** @var array<int, array<string, mixed>> */
    private array $filas = [];

    private string $regimen = 'Temporal';

    private ?string $codigoQr = self::CODIGO_QR;

    private ?IngresarLoteDepositoOutput $ultimaRespuesta = null;

    public function __construct()
    {
        $this->especimenRepo = new InMemoryEspecimenRepository;
        $this->entidadRepo = new InMemoryEntidadDepositanteRepository;
        $this->resolverTaxonomia = new ResolverTaxonomiaDwCEnMemoria;

        self::$app->instance(EspecimenRepositoryInterface::class, $this->especimenRepo);
        // El ingreso resuelve la entidad depositante; sin este enlace el escenario
        // acabaría dando de alta instituciones en la base real.
        self::$app->instance(EntidadDepositanteRepositoryInterface::class, $this->entidadRepo);
        // El resolutor real precarga el catálogo de taxones: fuera de lugar en un
        // escenario en memoria, y además aquí lo que se comprueba es cuándo se resuelve.
        self::$app->instance(ResolverTaxonomiaDwCPort::class, $this->resolverTaxonomia);

        $this->ingresarHandler = $this->make(IngresarLoteDepositoHandler::class);
    }

    // ── Dado ─────────────────────────────────────────────────────────────────

    #[Given('que un depósito aprobado trae una matriz con permisos y jerarquía taxonómica')]
    public function queUnDepositoTraeMatrizCompleta(): void
    {
        $this->filas = [$this->fila()];
    }

    #[Given('que un depósito aprobado trae una matriz sin permisos por fila')]
    public function queUnDepositoTraeMatrizSinPermisos(): void
    {
        // La plantilla ya no pide permisos por espécimen: preguntarlos dos veces daba
        // respuestas distintas dentro de un mismo lote.
        $fila = $this->fila();
        unset($fila['datosDwC']['researchPermit']);

        $this->filas = [$fila];
    }

    #[Given('que un depósito aprobado ingresa sin código QR de lote')]
    public function queUnDepositoIngresaSinCodigoQr(): void
    {
        $this->codigoQr = null;
        $this->filas = [$this->fila()];
    }

    #[Given('que un depósito aprobado ya ingresó su material a la colección')]
    public function queUnDepositoYaIngresoSuMaterial(): void
    {
        $this->filas = [$this->fila()];
        $this->ingresar();
    }

    #[Given('que un depósito ya ingresado reordena las filas de su matriz')]
    public function queUnDepositoReordenaSusFilas(): void
    {
        // Primer ingreso: la fila de interés va en la posición 1.
        $this->filas = [$this->fila()];
        $this->ingresar();

        // El curador inserta una fila antes, así que la de interés pasa a la posición 2.
        // Su código de catálogo derivado cambia; su uuid de registro, no. Con la clave
        // vieja este lote se habría duplicado entero.
        $this->filas = [
            $this->fila(indice: 1, registroId: self::REGISTRO_ID_ANADIDO, nombre: 'Heliconius erato'),
            $this->fila(indice: 2),
        ];
    }

    #[Given('que el curador aceptó corregir el nombre científico de un registro')]
    public function queElCuradorAceptoUnaCorreccion(): void
    {
        $this->filas = [$this->fila(
            nombre: 'Morpho sp. nov.',
            nombreCanonico: 'Morpho peleides',
            estadoRegistro: 'Validado Técnicamente',
        )];
    }

    #[Given('que un depósito trae un registro con la taxonomía sin validar')]
    public function queUnDepositoTraeTaxonomiaSinValidar(): void
    {
        $this->filas = [$this->fila(estadoRegistro: 'Validación Manual por Curaduría')];
    }

    #[Given('que un depósito aprobado ingresa con el régimen :regimen')]
    public function queUnDepositoIngresaConElRegimen(string $regimen): void
    {
        $this->regimen = $regimen;
        $this->filas = [$this->fila()];
    }

    // ── Cuando ───────────────────────────────────────────────────────────────

    #[When('el sistema ingresa el lote a la colección')]
    public function elSistemaIngresaElLote(): void
    {
        $this->ingresar();
    }

    // ── Entonces ─────────────────────────────────────────────────────────────

    #[Then('el espécimen conserva los permisos y el determinador declarados')]
    public function elEspecimenConservaLosPermisos(): void
    {
        $especimen = $this->especimenIngresado();

        Assert::assertSame('MAAE-ARSFC-2021-0145', $especimen->researchPermit(), 'Se perdió el permiso de investigación.');
        Assert::assertSame('Yánez, S.', $especimen->identifiedBy(), 'Se perdió el determinador.');
        Assert::assertSame('ECEPN0490', $especimen->recordNumber(), 'Se perdió el número de campo.');
    }

    #[Then('el espécimen queda enganchado a un taxón canónico')]
    public function elEspecimenQuedaEnganchadoAUnTaxon(): void
    {
        $especimen = $this->especimenIngresado();

        Assert::assertNotNull(
            $especimen->taxonId(),
            'Lo que el curador validó debe entrar enlazado al árbol, no esperando en la bandeja de verbatims.',
        );
        // La jerarquía declarada se conserva igual: es la procedencia contra la que se
        // contrasta el enlace.
        Assert::assertSame('Acrididae', $especimen->darwinCoreExtendido()->family);
    }

    #[Then('el espécimen conserva la jerarquía declarada sin taxón canónico')]
    public function elEspecimenConservaLaJerarquia(): void
    {
        $especimen = $this->especimenIngresado();
        $jerarquia = $especimen->darwinCoreExtendido();

        // Resolver crea taxones, y el árbol del museo es patrimonio compartido: un
        // depósito sin revisar no puede dar de alta nombres ahí.
        Assert::assertNull($especimen->taxonId(), 'Sin visto bueno del curador no se toca el árbol.');
        Assert::assertSame('Animalia', $jerarquia->kingdom);
        Assert::assertSame('Acrididae', $jerarquia->family);
        Assert::assertSame('Schistocerca', $jerarquia->genus);
        // Nada se pierde: el nombre sigue disponible para la bandeja de verbatims.
        Assert::assertNotNull($especimen->taxonVerbatim());
    }

    #[Then('el sistema no llegó a consultar el árbol taxonómico')]
    public function elSistemaNoConsultoElArbol(): void
    {
        // El resolutor real precarga los 4.003 taxones del catálogo, casi dos segundos
        // dentro de la petición del curador. Un lote sin nada validado no debe pagarlo.
        Assert::assertSame(0, $this->resolverTaxonomia->llamadas());
    }

    #[Then('el espécimen queda atado a su solicitud y a su fila de matriz')]
    public function elEspecimenQuedaAtadoASuTramite(): void
    {
        $procedencia = $this->especimenIngresado()->procedenciaDeposito();

        Assert::assertNotNull($procedencia, 'El espécimen debe conocer el trámite que lo trajo.');
        Assert::assertTrue($procedencia->tieneVinculoFuerte(), 'Debe quedar atado por el uuid del registro.');
        Assert::assertSame(self::REGISTRO_ID, $procedencia->registroId);
        Assert::assertSame(self::SOLICITUD_ID, $procedencia->solicitudId);
        Assert::assertSame(self::NUMERO, $procedencia->numeroSolicitud);
    }

    #[Then('la colección conserva un solo espécimen por fila de matriz')]
    public function laColeccionConservaUnSoloEspecimenPorFila(): void
    {
        $delRegistro = array_filter(
            $this->especimenRepo->buscarTodos(),
            fn (Especimen $e): bool => $e->procedenciaDeposito()?->registroId === self::REGISTRO_ID,
        );

        Assert::assertCount(1, $delRegistro, 'La fila no puede producir dos especímenes.');
        Assert::assertGreaterThan(
            0,
            $this->ultimaRespuesta?->omitidosPorDuplicado ?? 0,
            'La fila ya ingresada debe reconocerse como duplicada.',
        );
    }

    #[Then('la colección incorpora la fila nueva sin descartarla')]
    public function laColeccionIncorporaLaFilaNueva(): void
    {
        $nuevos = array_filter(
            $this->especimenRepo->buscarTodos(),
            fn (Especimen $e): bool => $e->procedenciaDeposito()?->registroId === self::REGISTRO_ID_ANADIDO,
        );

        Assert::assertCount(
            1,
            $nuevos,
            'La fila añadida heredaba el código derivado de otra y se descartaba en silencio.',
        );
        Assert::assertSame(1, $this->ultimaRespuesta?->especimenesCreados);
    }

    #[Then('el acta de recepción del espécimen es el código QR del lote')]
    public function elActaEsElCodigoQrDelLote(): void
    {
        Assert::assertSame(self::CODIGO_QR, $this->especimenIngresado()->actaRecepcion());
    }

    #[Then('el acta de recepción del espécimen queda vacía')]
    public function elActaQuedaVacia(): void
    {
        // Null es una respuesta legítima: significa que aún no hay recepción física. El
        // número de solicitud, que antes se colaba aquí, vive en su propia columna.
        Assert::assertNull($this->especimenIngresado()->actaRecepcion());
    }

    #[Then('el espécimen queda amparado por el permiso del trámite')]
    public function elEspecimenQuedaAmparadoPorElPermisoDelTramite(): void
    {
        $especimen = $this->especimenIngresado();

        Assert::assertSame(self::PERMISO_RECOLECCION, $especimen->researchPermit());
        Assert::assertSame(self::PERMISO_MOVILIZACION, $especimen->transportPermit());
    }

    #[Then('el resultado indica el espécimen que produjo cada fila')]
    public function elResultadoIndicaElEspecimenDeCadaFila(): void
    {
        $mapa = $this->ultimaRespuesta?->especimenPorRegistro() ?? [];

        Assert::assertArrayHasKey(
            self::REGISTRO_ID,
            $mapa,
            'El ingreso debe decir qué espécimen produjo cada fila, no solo cuántos entraron.',
        );
        Assert::assertSame(
            (string) $this->especimenIngresado()->id(),
            $mapa[self::REGISTRO_ID],
        );
    }

    #[Then('el resultado indica por qué esa fila quedó para revisión')]
    public function elResultadoIndicaElMotivoDeRevision(): void
    {
        $resultados = $this->ultimaRespuesta?->resultados ?? [];

        Assert::assertCount(1, $resultados);
        Assert::assertTrue($resultados[0]->requiereRevision());
        Assert::assertStringContainsString('taxonomía sin validar', (string) $resultados[0]->motivoRevision);
        Assert::assertSame(self::REGISTRO_ID, $resultados[0]->registroId);
    }

    #[Then('el espécimen queda atribuido a la institución depositante')]
    public function elEspecimenQuedaAtribuidoALaInstitucion(): void
    {
        $entidadId = $this->especimenIngresado()->entidadDepositanteId();

        Assert::assertNotNull($entidadId, 'El material depositado debe conocer a su depositante.');

        // La entidad es la contraparte del depósito: manda la institución, y la persona
        // que tramitó queda como contacto.
        $entidad = $this->entidadRepo->buscarPorNombre('EcoSambito C. Ltda');

        Assert::assertNotNull($entidad, 'Debió crearse la institución depositante.');
        Assert::assertSame($entidadId, (string) $entidad->id(), 'El espécimen debe apuntar a esa entidad.');
        Assert::assertSame('institucion', $entidad->tipo()?->value);
        Assert::assertStringContainsString('Juan Pérez', (string) $entidad->contacto());
    }

    #[Then('el espécimen adopta el nombre corregido por el curador')]
    public function elEspecimenAdoptaElNombreCorregido(): void
    {
        Assert::assertSame(
            'Morpho peleides',
            $this->especimenIngresado()->taxonVerbatim(),
            'Debe ganar la corrección del curador, no el nombre del depositante.',
        );
    }

    #[Then('el espécimen queda pendiente de revisión indicando el motivo')]
    public function elEspecimenQuedaPendienteDeRevision(): void
    {
        $especimen = $this->especimenIngresado();

        Assert::assertSame('pendiente', $especimen->estadoRevision()->value);
        Assert::assertStringContainsString('taxonomía sin validar', (string) $especimen->motivoRevision());
    }

    #[Then('el espécimen queda bajo el régimen :regimen')]
    public function elEspecimenQuedaBajoElRegimen(string $regimen): void
    {
        Assert::assertSame($regimen, $this->especimenIngresado()->estadoCustodia()?->value);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function ingresar(): void
    {
        $this->ultimaRespuesta = $this->ingresarHandler->handle(new IngresarLoteDepositoInput(
            numeroSolicitud: self::NUMERO,
            codigoQrLote: $this->codigoQr,
            estadoCustodia: $this->regimen,
            filas: $this->filas,
            solicitudDepositoId: self::SOLICITUD_ID,
            tipoTramite: 'Depósito',
            depositanteNombre: 'Juan Pérez',
            depositanteInstitucion: 'EcoSambito C. Ltda',
            depositanteEmail: 'jperez@ecosambito.test',
            permisoRecoleccion: self::PERMISO_RECOLECCION,
            permisoMovilizacion: self::PERMISO_MOVILIZACION,
        ));
    }

    /**
     * Fila de matriz con datos reales de la plantilla, incluida la jerarquía taxonómica
     * y los permisos: son justo los campos que el ingreso descartaba.
     *
     * @return array<string, mixed>
     */
    private function fila(
        int $indice = 1,
        string $registroId = self::REGISTRO_ID,
        string $nombre = 'Schistocerca nitens',
        ?string $nombreCanonico = null,
        string $estadoRegistro = 'Validado Técnicamente',
    ): array {
        return [
            'indice' => $indice,
            'registroId' => $registroId,
            'estadoRegistro' => $estadoRegistro,
            'motivoJustificacion' => null,
            'nombreCientificoCanonico' => $nombreCanonico ?? $nombre,
            'datosDwC' => [
                'scientificName' => $nombre,
                'kingdom' => 'Animalia',
                'phylum' => 'Arthropoda',
                'class' => 'Insecta',
                'order' => 'Orthoptera',
                'family' => 'Acrididae',
                'genus' => 'Schistocerca',
                'specificEpithet' => 'nitens',
                'taxonRank' => 'species',
                'recordNumber' => 'ECEPN0490',
                'identifiedBy' => 'Yánez, S.',
                'researchPermit' => 'MAAE-ARSFC-2021-0145',
                'recordedBy' => 'Troya, A.',
                'eventDate' => '2023-02-12',
                'verbatimLocality' => 'Ecuador, Orellana, Yasuní',
                'decimalLatitude' => '-0.6710',
                'decimalLongitude' => '-76.4000',
                'individualCount' => '3',
            ],
        ];
    }

    private function especimenIngresado(): Especimen
    {
        foreach ($this->especimenRepo->buscarTodos() as $especimen) {
            if ($especimen->procedenciaDeposito()?->registroId === self::REGISTRO_ID) {
                return $especimen;
            }
        }

        throw new \RuntimeException('El lote no produjo ningún espécimen para la fila de interés.');
    }
}
