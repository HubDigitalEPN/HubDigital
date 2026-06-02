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

final class EvaluarOrdenTaxonomicoCajaHandler
{
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
