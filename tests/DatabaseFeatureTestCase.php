<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Base para pruebas funcionales que usan el esquema PostgreSQL completo.
 *
 * El validador prepara copias locales aisladas de PostgreSQL por proceso.
 * Cada prueba usa una transacción y la revierte al terminar, evitando que
 * RefreshDatabase deje tablas parciales en los esquemas PostgreSQL no públicos.
 */
abstract class DatabaseFeatureTestCase extends TestCase
{
    use DatabaseTransactions;
}
