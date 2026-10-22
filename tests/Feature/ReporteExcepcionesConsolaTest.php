<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Psr\Log\LoggerInterface;

test('un fallo de consola sin request registra el error original sin provocar otra excepcion', function (): void {
    $app = app();
    $request = $app->make('request');
    $handler = $app->make(ExceptionHandler::class);
    $error = new RuntimeException('Fallo original antes de arrancar Artisan');
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()->with(
        $error->getMessage(),
        Mockery::on(fn (array $context): bool => ($context['exception'] ?? null) === $error),
    );
    $app->instance(LoggerInterface::class, $logger);
    $app->forgetInstance('request');
    try {
        $handler->report($error);
        expect($app->bound('request'))->toBeFalse();
    } finally {
        $app->instance('request', $request);
    }
});
