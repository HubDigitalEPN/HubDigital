<?php

use Tests\DatabaseFeatureTestCase;
use Tests\InfrastructureTestCase;
use Tests\PostgresIntegrationTestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// Los flujos funcionales que persisten datos usan la base local hubdigital. Se
// enumeran expresamente para que una prueba nueva no ejecute DDL sin declarar
// su dependencia de PostgreSQL.
pest()->extend(DatabaseFeatureTestCase::class)
    ->in(
        'Feature/Auth',
        'Feature/Settings',
        'Feature/DashboardCuraduriaTest.php',
        'Feature/DashboardTest.php',
        'Feature/DepositosApiDocumentacionSeguraTest.php',
        'Feature/DepositosCincoPuntosTest.php',
        'Feature/DepositosPortalTest.php',
        'Feature/ExampleTest.php',
        'Feature/FlujoDepositoE2ETest.php',
        'Feature/FlujoDepositoPersistenciaE2ETest.php',
        'Feature/OperacionesDocumentalesDepositosTest.php',
        'Feature/PwaPushSubscriptionTest.php',
        'Feature/PortalChatConocimientoTest.php',
        'Feature/PortalChatSegundaIteracionTest.php',
        'Feature/PortalChatTerceraIteracionTest.php',
        'Feature/PortalChatCuartaIteracionTest.php',
        'Feature/SolicitudFirmadaIntegridadTest.php',
    );

// El bootstrap inicial solo aplica a una instalacion sin usuarios. Se conserva
// para validarlo expresamente, pero se omite al empaquetar con la base existente.
pest()->group('bootstrap-inicial')->in('Feature/Auth/AdminBootstrapTest.php');

// Estas pruebas sólo cubren adaptadores y el directorio temporal. Arrancan el
// contenedor Laravel, pero no necesitan ni deben tocar una base de datos.
pest()->extend(InfrastructureTestCase::class)
    ->in(
        'Feature/AlmacenamientoDepositosTest.php',
        'Feature/DirectorioTemporalHubDigitalTest.php',
    );

// Las rutas que verifican R2 con relaciones reales se ejecutan contra una
// base PostgreSQL local previamente migrada. No emplean SQLite ni hacen
// DDL por caso de prueba: el procedimiento de integración crea y elimina esa
// base efímera completa.
pest()->extend(PostgresIntegrationTestCase::class)
    ->in('Feature/RutasObjetosR2Test.php');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** PDF de una página con estructura real para rutas que inspeccionan archivos. */
function pdfValidoParaDepositos(string $texto = 'Documento de prueba'): string
{
    $pdf = new \setasign\Fpdi\Fpdi;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Cell(0, 10, $texto);

    return $pdf->Output('S');
}

/** Configura un R2 S3 simulado en memoria, sin red ni fallback local. */
function configurarR2FalsoParaPruebas(): void
{
    $objetos = [];

    \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$objetos) {
        $componentes = parse_url($request->url());
        $segmentos = array_values(array_filter(explode('/', trim((string) ($componentes['path'] ?? ''), '/'))));
        $ruta = urldecode(implode('/', array_slice($segmentos, 1)));
        parse_str((string) ($componentes['query'] ?? ''), $consulta);

        if ($request->method() === 'GET' && ($consulta['list-type'] ?? null) === '2') {
            $prefijo = (string) ($consulta['prefix'] ?? '');
            $contenido = '';
            foreach ($objetos as $clave => $objeto) {
                if (! str_starts_with($clave, $prefijo)) {
                    continue;
                }
                $claveXml = htmlspecialchars($clave, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $contenido .= '<Contents><Key>'.$claveXml.'</Key><Size>'.strlen($objeto['body']).'</Size><ETag>"'.hash('md5', $objeto['body']).'"</ETag></Contents>';
            }

            return \Illuminate\Support\Facades\Http::response(
                '<?xml version="1.0" encoding="UTF-8"?><ListBucketResult>'.$contenido.'<IsTruncated>false</IsTruncated></ListBucketResult>',
                200,
                ['Content-Type' => 'application/xml'],
            );
        }

        if ($request->method() === 'PUT') {
            $objetos[$ruta] = ['body' => $request->body(), 'mime' => $request->header('Content-Type')[0] ?? 'application/octet-stream'];

            return \Illuminate\Support\Facades\Http::response('', 200);
        }
        if ($request->method() === 'HEAD') {
            return isset($objetos[$ruta])
                ? \Illuminate\Support\Facades\Http::response('', 200, [
                    'Content-Type' => $objetos[$ruta]['mime'],
                    'Content-Length' => (string) strlen($objetos[$ruta]['body']),
                    'ETag' => '"'.hash('md5', $objetos[$ruta]['body']).'"',
                ])
                : \Illuminate\Support\Facades\Http::response('', 404);
        }
        if ($request->method() === 'GET' && isset($objetos[$ruta])) {
            return \Illuminate\Support\Facades\Http::response($objetos[$ruta]['body'], 200, ['Content-Type' => $objetos[$ruta]['mime']]);
        }
        if ($request->method() === 'DELETE') {
            unset($objetos[$ruta]);

            return \Illuminate\Support\Facades\Http::response('', 204);
        }

        return \Illuminate\Support\Facades\Http::response('', 404);
    });

    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.verify_after_write', true);
    config()->set('deposit-storage.prefix', '');
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta-prueba.r2.cloudflarestorage.com',
        'bucket' => 'hubdigital-pruebas',
        'access_key_id' => 'clave-prueba',
        'secret_access_key' => 'secreto-prueba',
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
        'max_attempts' => 1,
    ]);
}
