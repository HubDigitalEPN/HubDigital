<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\EvaluarOrdenTaxonomicoCaja;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\EventPublisherPort;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\TransactionManagerPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\AlertaUbicacion;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EventoCicloIot;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events\FamiliaNoAsignadaDetectada;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Events\IncongruenciaTaxonomicaDetectada;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\AlertaUbicacionRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EventoCicloIotRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\OrdenEsperadoFamiliasRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\EvaluadorOrdenTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\ActorRol;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CajaId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RanuraId;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoAlerta;

/**
 * Caso de uso: evaluar si una caja recién ubicada respeta el orden taxonómico esperado dentro
 * de su gabinete, comparándola con sus cajas vecinas. Si detecta una incongruencia taxonómica
 * o una familia sin asignar, genera la alerta correspondiente, registra el evento del ciclo
 * IoT y publica el evento de dominio.
 *
 * @see EvaluarOrdenTaxonomicoCajaInput
 * @see EvaluarOrdenTaxonomicoCajaOutput
 */
final class EvaluarOrdenTaxonomicoCajaHandler
{
    /**
     * @param  CajaRepository  $cajaRepo  Recupera la caja evaluada y sus vecinas.
     * @param  RanuraGabineteRepository  $ranuraRepo  Recupera la ranura y localiza las ranuras vecinas ocupadas.
     * @param  AlertaUbicacionRepository  $alertaRepo  Genera y persiste la alerta de ubicación.
     * @param  EventoCicloIotRepository  $eventoRepo  Registra el evento del ciclo IoT generado por la evaluación.
     * @param  TransactionManagerPort  $transactionManager  Envuelve la generación de la alerta en una transacción.
     * @param  EventPublisherPort  $eventPublisher  Publica el evento de dominio resultante.
     * @param  EvaluadorOrdenTaxonomico  $evaluador  Servicio de dominio que decide el tipo de alerta según las vecinas.
     * @param  OrdenEsperadoFamiliasRepository  $ordenFamiliasRepo  Aporta el orden de familias esperado por el curador.
     */
    public function __construct(
        private readonly CajaRepository $cajaRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly AlertaUbicacionRepository $alertaRepo,
        private readonly EventoCicloIotRepository $eventoRepo,
        private readonly TransactionManagerPort $transactionManager,
        private readonly EventPublisherPort $eventPublisher,
        private readonly EvaluadorOrdenTaxonomico $evaluador,
        private readonly OrdenEsperadoFamiliasRepository $ordenFamiliasRepo,
    ) {}

    /**
     * Localiza las cajas vecinas (anterior y siguiente) de la ranura, delega en el evaluador la
     * decisión sobre el tipo de alerta y, si procede, genera la alerta, marca la caja como
     * pendiente de clasificación cuando la familia no está asignada, registra el evento IoT y
     * publica el evento de dominio. Devuelve si se generó alerta y de qué tipo.
     *
     * @throws \DomainException si la caja o la ranura indicadas no existen.
     */
    public function handle(EvaluarOrdenTaxonomicoCajaInput $input): EvaluarOrdenTaxonomicoCajaOutput
    {
        $cajaId = CajaId::desde($input->cajaId);
        $ranuraId = RanuraId::desde($input->ranuraId);

        $caja = $this->cajaRepo->buscarPorId($cajaId);
        if ($caja === null) {
            throw new \DomainException("Caja '{$input->cajaId}' no encontrada.");
        }

        $ranura = $this->ranuraRepo->buscarPorId($ranuraId);
        if ($ranura === null) {
            throw new \DomainException("Ranura '{$input->ranuraId}' no encontrada.");
        }

        $vecinas = $this->ranuraRepo->buscarVecinasOcupadas(
            $ranura->gabineteId(),
            $ranura->numeroRanura()
        );

        $cajaAnterior = null;
        $cajaSiguiente = null;

        foreach ($vecinas as $ranuraVecina) {
            $cajaVecina = $this->cajaRepo->buscarPorId($ranuraVecina->cajaActualId());
            if ($ranuraVecina->numeroRanura() < $ranura->numeroRanura()) {
                $cajaAnterior = $cajaVecina;
            } else {
                $cajaSiguiente = $cajaVecina;
            }
        }

        $ordenFamilias = $this->ordenFamiliasRepo->obtener();
        $tipoAlerta = $this->evaluador->evaluar($caja, $cajaAnterior, $cajaSiguiente, $ordenFamilias);

        if ($tipoAlerta === null) {
            return EvaluarOrdenTaxonomicoCajaOutput::fromPrimitives([
                'cajaId' => (string) $cajaId,
                'estadoCaja' => $caja->estadoActual()->valor(),
                'alertaGenerada' => false,
                'tipoAlerta' => null,
                'alertaId' => null,
            ]);
        }

        $alerta = $this->transactionManager->executeTransactional(
            function () use ($caja, $cajaId, $ranuraId, $ranura, $tipoAlerta): AlertaUbicacion {
                $alerta = AlertaUbicacion::generar(
                    id: $this->alertaRepo->nextIdentity(),
                    cajaId: $cajaId,
                    tipo: $tipoAlerta,
                    datosContexto: ['ranura_id' => (string) $ranuraId],
                );
                $this->alertaRepo->guardar($alerta);

                if ($tipoAlerta->equals(TipoAlerta::FamiliaNoAsignada)) {
                    $caja->marcarPendienteClasificacion();
                    $this->cajaRepo->guardar($caja);
                }

                $this->eventoRepo->guardar(EventoCicloIot::registrar(
                    tipoAgregado: 'caja',
                    agregadoId: (string) $cajaId,
                    tipoEvento: 'alerta_taxonomica_generada',
                    versionEvento: 1,
                    datos: [
                        'tipo_alerta' => $tipoAlerta->value,
                        'ranura_id' => (string) $ranuraId,
                    ],
                    actorId: null,
                    actorRol: ActorRol::Sistema,
                    ocurridoEn: new \DateTimeImmutable,
                ));

                $ocurridoEn = new \DateTimeImmutable;
                $evento = $tipoAlerta->equals(TipoAlerta::FamiliaNoAsignada)
                    ? new FamiliaNoAsignadaDetectada($cajaId, $ranuraId, $ranura->gabineteId(), $ocurridoEn)
                    : new IncongruenciaTaxonomicaDetectada($cajaId, $ranuraId, $ranura->gabineteId(), $ocurridoEn);

                $this->eventPublisher->publish($evento);

                return $alerta;
            }
        );

        return EvaluarOrdenTaxonomicoCajaOutput::fromPrimitives([
            'cajaId' => (string) $cajaId,
            'estadoCaja' => $caja->estadoActual()->valor(),
            'alertaGenerada' => true,
            'tipoAlerta' => $tipoAlerta->value,
            'alertaId' => (string) $alerta->id(),
        ]);
    }
}
