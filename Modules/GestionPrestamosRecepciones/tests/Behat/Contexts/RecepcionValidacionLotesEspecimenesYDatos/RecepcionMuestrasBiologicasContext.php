<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;

final class RecepcionMuestrasBiologicasContext extends BaseContext
{
    #[Given('que un investigador cuenta con la documentación oficial requerida:')]
    public function queUnInvestigadorCuentaConLaDocumentaciónOficialRequerida(TableNode $table): void
    {
        throw new PendingException;
    }

    #[Given('ha preparado una matriz Darwin Core con los datos de las muestras')]
    public function haPreparadoUnaMatrizDarwinCoreConLosDatosDeLasMuestras(): void
    {
        throw new PendingException;
    }

    #[When('el investigador inicia el trámite subiendo la documentación')]
    public function elInvestigadorIniciaElTrámiteSubiendoLaDocumentación(): void
    {
        throw new PendingException;
    }

    #[Then('se extraen automáticamente los siguientes datos:')]
    public function seExtraenAutomáticamenteLosSiguientesDatos(TableNode $table): void
    {
        throw new PendingException;
    }

    #[Then('se autocompletan los campos correspondientes en la solicitud de depósito')]
    public function seAutocompletanLosCamposCorrespondientesEnLaSolicitudDeDepósito(): void
    {
        throw new PendingException;
    }

    #[Given('que la extracción automática no obtiene toda la información obligatoria')]
    public function queLaExtracciónAutomáticaNoObtieneTodaLaInformaciónObligatoria(): void
    {
        throw new PendingException;
    }

    #[When('el investigador ingresa los datos faltantes')]
    public function elInvestigadorIngresaLosDatosFaltantes(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud se registra exitosamente')]
    public function laSolicitudSeRegistraExitosamente(): void
    {
        throw new PendingException;
    }

    #[Then('se marca para revisión detallada por parte de un curador')]
    public function seMarcaParaRevisiónDetalladaPorParteDeUnCurador(): void
    {
        throw new PendingException;
    }

    #[When('el investigador envía su Solicitud de Depósito')]
    public function elInvestigadorEnvíaSuSolicitudDeDepósito(): void
    {
        throw new PendingException;
    }

    #[Then('el Nombre del Representante y la Empresa detectados en los documentos deben coincidir con su perfil de usuario registrado')]
    public function elNombreDelRepresentanteYLaEmpresaDetectadosEnLosDocumentosDebenCoincidirConSuPerfilDeUsuarioRegistrado(): void
    {
        throw new PendingException;
    }

    #[Given('que en los documentos aparece un nombre que no coincide con la cuenta del usuario')]
    public function queEnLosDocumentosApareceUnNombreQueNoCoincideConLaCuentaDelUsuario(): void
    {
        throw new PendingException;
    }

    #[When('el investigador fuerza el envío del trámite asumiendo la responsabilidad')]
    public function elInvestigadorFuerzaElEnvíoDelTrámiteAsumiendoLaResponsabilidad(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud es recibida')]
    public function laSolicitudEsRecibida(): void
    {
        throw new PendingException;
    }

    #[Then('se resalta como prioritaria para la verificación manual de identidad por un curador')]
    public function seResaltaComoPrioritariaParaLaVerificaciónManualDeIdentidadPorUnCurador(): void
    {
        throw new PendingException;
    }

    #[Given('que el investigador ha subido una matriz Darwin Core')]
    public function queElInvestigadorHaSubidoUnaMatrizDarwinCore(): void
    {
        throw new PendingException;
    }

    #[Given('la matriz contiene la especie ":especie_ingresada" con posibles errores tipográficos')]
    public function laMatrizContieneLaEspecieConPosiblesErroresTipográficos(string $especie_ingresada): void
    {
        throw new PendingException;
    }

    #[Given('el sistema sugiere la corrección a ":especie_sugerida" según GBIF')]
    public function elSistemaSugiereLaCorrecciónASegúnGbif(string $especie_sugerida): void
    {
        throw new PendingException;
    }

    #[When('el investigador acepta la sugerencia de corrección')]
    public function elInvestigadorAceptaLaSugerenciaDeCorrección(): void
    {
        throw new PendingException;
    }

    #[Then('los registros se marcan como validados')]
    public function losRegistrosSeMarcanComoValidados(): void
    {
        throw new PendingException;
    }

    #[Then('el proceso de recepción continúa normalmente')]
    public function elProcesoDeRecepciónContinúaNormalmente(): void
    {
        throw new PendingException;
    }

    #[Given('la matriz contiene la especie desconocida ":especie"')]
    public function laMatrizContieneLaEspecieDesconocida(string $especie): void
    {
        throw new PendingException;
    }

    #[When('el investigador selecciona el motivo ":motivo_investigador" para justificar el hallazgo')]
    public function elInvestigadorSeleccionaElMotivoParaJustificarElHallazgo(string $motivo_investigador): void
    {
        throw new PendingException;
    }

    #[Then('el registro se marca para validación manual')]
    public function elRegistroSeMarcaParaValidaciónManual(): void
    {
        throw new PendingException;
    }

    #[Given('que un curador evalúa una solicitud marcada como prioritaria')]
    public function queUnCuradorEvalúaUnaSolicitudMarcadaComoPrioritaria(): void
    {
        throw new PendingException;
    }

    #[When('el curador confirma que los documentos son válidos, la identidad es correcta y aprueba el trámite')]
    public function elCuradorConfirmaQueLosDocumentosSonVálidosLaIdentidadEsCorrectaYApruebaElTrámite(): void
    {
        throw new PendingException;
    }

    #[Then('la solicitud queda lista para la recepción física')]
    public function laSolicitudQuedaListaParaLaRecepciónFísica(): void
    {
        throw new PendingException;
    }

    #[Then('se generan las etiquetas QR para que el investigador pueda realizar el depósito')]
    public function seGeneranLasEtiquetasQrParaQueElInvestigadorPuedaRealizarElDepósito(): void
    {
        throw new PendingException;
    }

    #[When('el investigador envía la documentación y la matriz sin presentar alertas ni inconsistencias')]
    public function elInvestigadorEnvíaLaDocumentaciónYLaMatrizSinPresentarAlertasNiInconsistencias(): void
    {
        throw new PendingException;
    }

    #[Then('el trámite se aprueba automáticamente de forma exitosa')]
    public function elTrámiteSeApruebaAutomáticamenteDeFormaExitosa(): void
    {
        throw new PendingException;
    }

    #[Given('que el curador recibe el lote físico transportado por el investigador')]
    public function queElCuradorRecibeElLoteFísicoTransportadoPorElInvestigador(): void
    {
        throw new PendingException;
    }

    #[Given('escanea la etiqueta QR de las muestras para su revisión en el portal')]
    public function escaneaLaEtiquetaQrDeLasMuestrasParaSuRevisiónEnElPortal(): void
    {
        throw new PendingException;
    }

    #[When('el curador verifica que la integridad física de las muestras es correcta')]
    public function elCuradorVerificaQueLaIntegridadFísicaDeLasMuestrasEsCorrecta(): void
    {
        throw new PendingException;
    }

    #[Then('el lote se marca como verificado físicamente')]
    public function elLoteSeMarcaComoVerificadoFísicamente(): void
    {
        throw new PendingException;
    }

    #[Then('el proceso avanza a la generación del acta de entrega')]
    public function elProcesoAvanzaALaGeneraciónDelActaDeEntrega(): void
    {
        throw new PendingException;
    }

    #[When('el curador registra un fallo de integridad por ":causa_fallo" en la muestra')]
    public function elCuradorRegistraUnFalloDeIntegridadPorEnLaMuestra(string $causa_fallo): void
    {
        throw new PendingException;
    }

    #[Then('se detiene el flujo de recepción')]
    public function seDetieneElFlujoDeRecepción(): void
    {
        throw new PendingException;
    }

    #[Then('se inicia el protocolo de corrección pertinente')]
    public function seIniciaElProtocoloDeCorrecciónPertinente(): void
    {
        throw new PendingException;
    }

    #[Given('que un lote físico ha sido evaluado y se encuentra en estado conforme')]
    public function queUnLoteFísicoHaSidoEvaluadoYSeEncuentraEnEstadoConforme(): void
    {
        throw new PendingException;
    }

    #[When('el curador aprueba el proceso de recepción física')]
    public function elCuradorApruebaElProcesoDeRecepciónFísica(): void
    {
        throw new PendingException;
    }

    #[Then('el sistema genera un acta digital de recepción')]
    public function elSistemaGeneraUnActaDigitalDeRecepción(): void
    {
        throw new PendingException;
    }

    #[Then('a todos los especímenes de este lote se les asigna el estado "TEMPORAL"')]
    public function aTodosLosEspecímenesDeEsteLoteSeLesAsignaElEstadoTemporal(): void
    {
        throw new PendingException;
    }

    #[Then('se envía una notificación de recepción finalizada al investigador')]
    public function seEnvíaUnaNotificaciónDeRecepciónFinalizadaAlInvestigador(): void
    {
        throw new PendingException;
    }
}
