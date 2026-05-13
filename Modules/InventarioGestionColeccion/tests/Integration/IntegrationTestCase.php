<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql.host', env('DB_HOST', '127.0.0.1'));
        $app['config']->set('database.connections.pgsql.port', env('DB_PORT', '5432'));
        $app['config']->set('database.connections.pgsql.database', env('DB_DATABASE', 'hubdigital'));
        $app['config']->set('database.connections.pgsql.username', env('DB_USERNAME', 'postgres'));
        $app['config']->set('database.connections.pgsql.password', env('DB_PASSWORD', ''));
    }
}
