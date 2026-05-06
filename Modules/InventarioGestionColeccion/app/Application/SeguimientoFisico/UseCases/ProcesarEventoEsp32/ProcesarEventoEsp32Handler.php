<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ProcesarEventoEsp32;

use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarIngresoCaja\RegistrarIngresoCajaInput;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\RegistrarRetiroCaja\RegistrarRetiroCajaInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\CajaRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\RanuraGabineteRepository;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CodigoRfid;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\GabineteId;

final class ProcesarEventoEsp32Handler
{
    public function __construct(
        private readonly CajaRepository $cajaRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly RegistrarIngresoCajaHandler $ingresoHandler,
        private readonly RegistrarRetiroCajaHandler $retiroHandler,
    ) {}

    public function handle(ProcesarEventoEsp32Input $input): ProcesarEventoEsp32Output
    {
        $caja = $this->cajaRepo->buscarPorCodigoRfid(CodigoRfid::desde($input->tagUid));
        if ($caja === null) {
            throw new \DomainException("Tag RFID '{$input->tagUid}' no está registrado en el sistema.");
        }

        $ranura = $this->ranuraRepo->buscarPorNumeroEnGabinete(
            GabineteId::desde($input->gabineteId),
            $input->slotIndex,
        );
        if ($ranura === null) {
            throw new \DomainException("Ranura {$input->slotIndex} del gabinete '{$input->gabineteId}' no está configurada.");
        }

        $cajaId = (string) $caja->id();
        $ranuraId = (string) $ranura->id();

        if ($input->evento === 'ingreso') {
            $output = $this->ingresoHandler->handle(new RegistrarIngresoCajaInput(
                cajaId: $cajaId,
                ranuraId: $ranuraId,
            ));

            return new ProcesarEventoEsp32Output(
                cajaId: $output->cajaId,
                ranuraId: $output->ranuraId,
                estadoCaja: $output->estadoCaja,
                alertaGenerada: $output->alertaGenerada,
                notificacionEnviada: false,
            );
        }

        $output = $this->retiroHandler->handle(new RegistrarRetiroCajaInput(
            cajaId: $cajaId,
            ranuraId: $ranuraId,
        ));

        return new ProcesarEventoEsp32Output(
            cajaId: $output->cajaId,
            ranuraId: $ranuraId,
            estadoCaja: $output->estadoCaja,
            alertaGenerada: $output->alertaGenerada,
            notificacionEnviada: $output->notificacionEnviada,
        );
    }
}
