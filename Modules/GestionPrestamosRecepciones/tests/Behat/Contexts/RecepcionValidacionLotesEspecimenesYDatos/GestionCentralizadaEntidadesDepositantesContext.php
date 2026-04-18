<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

/**
 * Contexto para: gestion_centralizada_entidades_depositantes.feature
 *
 * Steps propios de esta feature — implementar aquí:
 *   - "no existe una institución con el nombre :nombre"
 *   - "intento registrar una nueva institución con los datos:"
 *   - "la institución debe ser registrada exitosamente"
 *   - "debo ver un mensaje de confirmación :mensaje"
 *   - "podré usar la institución para realizar depósitos de lotes"
 *   - "existe una institución registrada con el nombre :nombre"
 *   - "intento registrar una nueva institución con el mismo nombre :nombre"
 *   - "debo ver un error :mensaje"
 *   - "la institución no debe ser registrada"
 *   - "intento registrar una institución sin completar los campos obligatorios"
 *   - "envío el formulario sin llenar el campo :campo_faltante"
 */
final class GestionCentralizadaEntidadesDepositantesContext extends BaseContext
{
    #[Given('que no existe una institución con el nombre :arg1')]
    public function queNoExisteUnaInstituciónConElNombre($arg1): void
    {
        throw new PendingException;
    }

    #[When('intento registrar una nueva institución con los datos:')]
    public function intentoRegistrarUnaNuevaInstituciónConLosDatos(TableNode $table): void
    {
        throw new PendingException;
    }

    #[Then('la institución debe ser registrada exitosamente')]
    public function laInstituciónDebeSerRegistradaExitosamente(): void
    {
        throw new PendingException;
    }

    #[Then('debo ver un mensaje de confirmación :arg1')]
    public function deboVerUnMensajeDeConfirmación($arg1): void
    {
        throw new PendingException;
    }

    #[Then('podré usar la institución para realizar depósitos de lotes')]
    public function podréUsarLaInstituciónParaRealizarDepósitosDeLotes(): void
    {
        throw new PendingException;
    }

    #[Given('que existe una institución registrada con el nombre :arg1')]
    public function queExisteUnaInstituciónRegistradaConElNombre($arg1): void
    {
        throw new PendingException;
    }

    #[When('intento registrar una nueva institución con el mismo nombre :arg1')]
    public function intentoRegistrarUnaNuevaInstituciónConElMismoNombre($arg1): void
    {
        throw new PendingException;
    }

    #[Then('debo ver un error :arg1')]
    public function deboVerUnError($arg1): void
    {
        throw new PendingException;
    }

    #[Then('la institución no debe ser registrada')]
    public function laInstituciónNoDebeSerRegistrada(): void
    {
        throw new PendingException;
    }

    #[Given('que intento registrar una institución sin completar los campos obligatorios')]
    public function queIntentoRegistrarUnaInstituciónSinCompletarLosCamposObligatorios(): void
    {
        throw new PendingException;
    }

    #[When('envío el formulario sin llenar el campo :campo')]
    public function envíoElFormularioSinLlenarElCampo(string $campo): void
    {
        throw new PendingException;
    }
}
