<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;

$base = __DIR__;

return (new Config)
    ->withProfile(
        (new Profile('recepcion'))
            ->withSuite(
                (new Suite('RegistroSolicitudDeposito'))
                    ->withPaths($base.'/Modules/GestionPrestamosRecepciones/tests/Behat/Features/RecepcionValidacionLotesEspecimenesYDatos/registro_solicitud_deposito.feature')
                    ->withContexts(
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos\RegistroSolicitudDepositoContext',
                    )
            )
    )
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('CatalogoPublico'))
                    ->withPaths($base.'/Modules/CatalogoPublico/tests/Behat/Features')
                    ->withContexts(
                        // AdministracionCambiosInformacionLaboratorio
                        'Modules\CatalogoPublico\Tests\Behat\Contexts\AdministracionCambiosInformacionLaboratorio\SincronizacionInformacionEspecimenesContext',
                    )
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
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\GestionActaPrestamoContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\SeguimientoPrestamosContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\DefinicionRecordatoriosDevolucionContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\CierrePrestamosContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\GestionProrrogasPrestamoContext',
                        // RecepcionValidacionLotesEspecimenesYDatos
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos\GestionCentralizadaEntidadesDepositantesContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos\RecepcionMuestrasBiologicasContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\RecepcionValidacionLotesEspecimenesYDatos\RegistroSolicitudDepositoContext',
                    )
            )
            ->withSuite(
                (new Suite('InventarioGestionColeccion'))
                    ->withPaths($base.'/Modules/InventarioGestionColeccion/tests/Behat/Features')
                    ->withContexts(
                        // GestionAutonomaSeguridadFisicaInventario
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario\AlertaIncongruenciaTaxonomicaContext',
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario\RegistroUbicacionCajasContext',
                        // GestionRegistrosTaxonomicos
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\RegistroTaxonContext',
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\UnicidadNomenclaturaContext',
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\BusquedaArbolTaxonomicoContext',
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\GestionEntidadesDepositantesContext',
                        'Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionRegistrosTaxonomicos\GeneracionActaEntregaContext',
                    )
            )
    );
