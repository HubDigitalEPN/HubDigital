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

/**
 * Caso de uso: ingesta del barrido del ESP32. Traduce un evento crudo del lector RFID (tag,
 * gabinete y slot físico) a la caja y la ranura del dominio, y delega en el caso de uso de
 * ingreso o de retiro según el tipo de evento.
 *
 * @see ProcesarEventoEsp32Input
 * @see ProcesarEventoEsp32Output
 */
final class ProcesarEventoEsp32Handler
{
    /**
     * @param  CajaRepository  $cajaRepo  Resuelve la caja a partir del tag RFID emitido por el sensor.
     * @param  RanuraGabineteRepository  $ranuraRepo  Resuelve la ranura a partir del gabinete y el índice de slot.
     * @param  RegistrarIngresoCajaHandler  $ingresoHandler  Caso de uso al que se delega un evento de ingreso.
     * @param  RegistrarRetiroCajaHandler  $retiroHandler  Caso de uso al que se delega un evento de retiro.
     */
    public function __construct(
        private readonly CajaRepository $cajaRepo,
        private readonly RanuraGabineteRepository $ranuraRepo,
        private readonly RegistrarIngresoCajaHandler $ingresoHandler,
        private readonly RegistrarRetiroCajaHandler $retiroHandler,
    ) {}

    /**
     * Mapea el tag RFID a su caja y el slot (índice base 0) a su ranura (número base 1), y
     * delega el evento al handler de ingreso o de retiro, normalizando su salida.
     *
     * @throws \DomainException si el tag RFID no está registrado o la ranura no está configurada.
     */
    public function handle(ProcesarEventoEsp32Input $input): ProcesarEventoEsp32Output
    {
        $caja = $this->cajaRepo->buscarPorCodigoRfid(CodigoRfid::desde($input->tagUid));
        if ($caja === null) {
            throw new \DomainException("Tag RFID '{$input->tagUid}' no está registrado en el sistema.");
        }

        $numeroRanura = $input->slotIndex + 1;

        $ranura = $this->ranuraRepo->buscarPorNumeroEnGabinete(
            GabineteId::desde($input->gabineteId),
            $numeroRanura,
        );
        if ($ranura === null) {
            throw new \DomainException("Ranura {$numeroRanura} del gabinete '{$input->gabineteId}' no está configurada.");
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
            ranuraId: $output->ranuraId !== '' ? $output->ranuraId : $ranuraId,
            estadoCaja: $output->estadoCaja,
            alertaGenerada: $output->alertaGenerada,
            notificacionEnviada: $output->notificacionEnviada,
        );
    }
}
