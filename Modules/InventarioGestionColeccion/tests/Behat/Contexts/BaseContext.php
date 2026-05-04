<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Dotenv\Dotenv;
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

        $root = dirname(__DIR__, 5);

        // Load behat-specific env overrides (local DB) before bootstrapping
        if (file_exists($root.'/.env.behat')) {
            $dotenv = Dotenv::createMutable($root, '.env.behat');
            $dotenv->load();
        }

        // dirname 5 sube: Contexts/ -> Behat/ -> tests/ -> InventarioGestionColeccion/ -> Modules/ -> [raiz]
        self::$app = require $root.'/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();
    }

    private static ?string $lastMigratedScenario = null;

    #[BeforeScenario]
    public function resetDatabase(BeforeScenarioScope $scope): void
    {
        $scenarioId = $scope->getFeature()->getFile().':'.$scope->getScenario()->getLine();

        if (self::$lastMigratedScenario === $scenarioId) {
            return;
        }

        self::$lastMigratedScenario = $scenarioId;

        // migrate:fresh only drops public-schema tables; the iot schema must be dropped manually
        DB::statement('DROP SCHEMA IF EXISTS iot CASCADE');

        self::$app->make(Kernel::class)->call('migrate:fresh');
    }

    protected function make(string $abstract): mixed
    {
        return self::$app->make($abstract);
    }
}
