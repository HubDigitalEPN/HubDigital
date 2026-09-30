<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Base para integraciones que requieren el esquema completo de PostgreSQL.
 *
 * El paquete aplica migraciones en la base PostgreSQL local antes de Pest.
 * Cada prueba revierte sus cambios; no se usa SQLite ni RefreshDatabase.
 */
abstract class PostgresIntegrationTestCase extends TestCase
{
    use DatabaseTransactions;
}
