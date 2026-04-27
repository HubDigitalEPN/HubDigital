<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;

$base = __DIR__;

return (new Config)
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('CatalogoPublico'))
                    ->withPaths($base.'/Modules/CatalogoPublico/tests/Behat/Features')
                    ->withContexts('Modules\CatalogoPublico\Tests\Behat\Contexts\CatalogoPublicoContext')
            )
            ->withSuite(
                (new Suite('GestionPrestamosRecepciones'))
                    ->withPaths($base.'/Modules/GestionPrestamosRecepciones/tests/Behat/Features')
                    ->withContexts(
                        // TramitacionSolicitudesInvestigador
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\EnvioSolicitudPrestamoContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\SeguimientoSolicitudesContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\RecordatoriosDevolucionPrestamosContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\CierrePrestamoDevolucionContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\SolicitudProrrogaPrestamoContext',
                        // AdministracionCuratorialSolicitudesPrestamos
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\ResolucionSolicitudesPrestamoContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\SeguimientoPrestamosContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\DefinicionRecordatoriosDevolucionContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\CierrePrestamosContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\GestionProrrogasPrestamoContext',
                        // RecepcionValidacionLotesEspecimenesYDatos
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos\GestionCentralizadaEntidadesDepositantesContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos\RecepcionMuestrasBiologicasContext',
                    )
            )
            ->withSuite(
                (new Suite('InventarioGestionColeccion'))
                    ->withPaths($base.'/Modules/InventarioGestionColeccion/tests/Behat/Features')
                    ->withContexts(
                        // GestionAutonomaSeguridadFisicaInventario
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario\AlertaIncongruenciaTaxonomicaContext',
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario\RegistroUbicacionCajasContext',
                        // TrazabilidadOperativaMovimientosCirculacion
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion\MonitoreoTiempoExtraccionContext',
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion\ReubicacionDigitalGuiadaContext',
                    )
            )
            ->withSuite(
                (new Suite('GestionInformacionTaxonomica'))
                    ->withPaths($base.'/Modules/GestionInformacionTaxonomica/tests/Behat/Features')
                    ->withContexts(
                        // GestionRegistrosTaxonomicos
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\RegistroTaxonContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\UnicidadNomenclaturaContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\BusquedaArbolTaxonomicoContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\GestionEntidadesDepositantesContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\GeneracionActaEntregaContext',
                        // IdentificacionFisicaEspecimenes
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\IdentificacionFisicaEspecimenes\AsignacionGuidEspecimenContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\IdentificacionFisicaEspecimenes\GeneracionCodigoQrContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\IdentificacionFisicaEspecimenes\LecturaQrMovilContext',
                        // ValidacionYCalidadDatos
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\ValidacionYCalidadDatos\ValidacionDarwinCoreContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\ValidacionYCalidadDatos\VerificacionDocumentosLegalesContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\ValidacionYCalidadDatos\MigracionEtlHistoricosContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\ValidacionYCalidadDatos\TrazabilidadErroresMigracionContext',
                        // InteroperabilidadEstados
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\InteroperabilidadEstados\SincronizacionEstadoPrestamoContext',
                        'Modules\GestionInformacionTaxonomica\Tests\Behat\Contexts\InteroperabilidadEstados\SincronizacionEstadoDevolucionContext',
                    )
            )
    );
