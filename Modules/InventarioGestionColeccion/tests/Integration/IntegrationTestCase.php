<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\ColeccionPortalAislada;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use DatabaseTransactions;
    use ColeccionPortalAislada;

    // getEnvironmentSetUp no existe en Laravel 13 — se usa refreshApplication()
    // que corre antes de setUpTraits(), la transacción y los fixtures aislados.
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        // TEST_DB_* apuntan al pgsql local preparado por crear-paquete-oci.
        // Cada caso restaura la colección mediante DatabaseTransactions.
        $this->app['config']->set('database.default', 'pgsql');
        $this->app['config']->set('database.connections.pgsql.url', null);
        $this->app['config']->set('database.connections.pgsql.host', env('TEST_DB_HOST', '127.0.0.1'));
        $this->app['config']->set('database.connections.pgsql.port', env('TEST_DB_PORT', '5432'));
        $this->app['config']->set('database.connections.pgsql.database', env('TEST_DB_DATABASE', 'hubdigital'));
        $this->app['config']->set('database.connections.pgsql.username', env('TEST_DB_USERNAME', 'postgres'));
        $this->app['config']->set('database.connections.pgsql.password', env('TEST_DB_PASSWORD', ''));
        $this->app['config']->set('database.connections.pgsql.sslmode', 'prefer');
    }
}
