<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;

$base = __DIR__;

return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('CatalogoPublico'))
                    ->withPaths($base . '/Modules/CatalogoPublico/tests/Behat/Features')
                    ->withContexts('Modules\CatalogoPublico\Tests\Behat\Contexts\CatalogoPublicoContext')
            )
            ->withSuite(
                (new Suite('GestionPrestamosRecepciones'))
                    ->withPaths($base . '/Modules/GestionPrestamosRecepciones/tests/Behat/Features')
                    ->withContexts(
                        // TramitacionSolicitudesInvestigador
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\EnvioSolicitudPrestamoContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\SeguimientoSolicitudesContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\RecordatoriosDevolucionPrestamosContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\CierrePrestamoDevolucionContext',
                        // AdministracionCuratorialSolicitudesPrestamos
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\ResolucionSolicitudesPrestamoContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\SeguimientoPrestamosContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\DefinicionRecordatoriosDevolucionContext',
                        'Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\CierrePrestamosContext',
                    )
            )
            ->withSuite(
                (new Suite('InventarioGestionColeccion'))
                    ->withPaths($base . '/Modules/InventarioGestionColeccion/tests/Behat/Features')
                    ->withContexts('Modules\InventarioGestionColeccion\Tests\Behat\Contexts\InventarioGestionColeccionContext')
            )
    );
