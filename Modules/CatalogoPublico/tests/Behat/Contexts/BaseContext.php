<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

abstract class BaseContext implements Context
{
    protected static mixed $app = null;

    #[BeforeSuite]
    public static function bootstrapLaravel(): void
    {
        if (self::$app !== null) {
            return;
        }

        // dirname 5: Contexts/ -> Behat/ -> tests/ -> CatalogoPublico/ -> Modules/ -> [raiz]
        self::$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();
        self::$app->make(Kernel::class)->call('migrate');
    }

    #[BeforeScenario]
    public function resetDatabase(): void
    {
        // migrate:fresh sólo borra el schema 'public'; los schemas custom (taxonomia, divulgacion)
        // no se dropean, por lo que los datos filtran entre escenarios y las migraciones no-idempotentes revientan.
        // TRUNCATE CASCADE es atómico y propaga por FKs: taxones → especimenes → especimenes_divulgables.
        DB::statement('TRUNCATE TABLE taxonomia.taxones, divulgacion.especimenes CASCADE');
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $abstract
     * @return T
     */
    protected function make(string $abstract): mixed
    {
        return static::$app->make($abstract);
    }
}
