---
name: behat
description: >
  Field guide completo para implementar tests de comportamiento con Behat 4 en Hub Digital.
  Activa este skill cuando vayas a crear o modificar archivos .feature, Context classes,
  step definitions, o la configuración behat.php. Cubre bootstrap de Laravel sin extensión
  oficial, patrón BaseContext + Contexts por capability, ciclo de vida de escenarios,
  fixtures de dominio, manejo de excepciones en steps, y tablas de datos Gherkin.
  Todos los ejemplos usan los módulos reales de Hub Digital y el ubiquitous language
  del proyecto (solicitud, curador, investigador, espécimen, ubicación, etc.).
origin: Creado para Hub Digital — Colección Entomológica EPN
version: 1.0.0
stack: Behat 4.x-dev · Laravel 13 · PHP 8.3 · PostgreSQL · nwidart/laravel-modules
---

# Behat — Field Guide Hub Digital

Guía de implementación paso a paso para tests de comportamiento en Hub Digital.
Las **convenciones obligatorias** (qué está prohibido, cómo nombrar, qué actor usar)
están en `.ai/guidelines/behat-conventions.md`. Este skill es el cómo — con código
completo, funcional y listo para copiar.

---

## Tabla de Contenidos

1. [Arquitectura general de Behat en Hub Digital](#1-arquitectura-general)
2. [Configuración — behat.php](#2-configuración--behatphp)
3. [BaseContext — bootstrap de Laravel sin extensión](#3-basecontext--bootstrap-de-laravel)
4. [Context por capability — plantilla completa](#4-context-por-capability)
5. [Ciclo de vida de un escenario y limpieza de BD](#5-ciclo-de-vida-y-limpieza-de-bd)
6. [Fixtures de dominio — cómo sembrar estado en Dado](#6-fixtures-de-dominio)
7. [Step definitions — patrones por tipo de assertion](#7-step-definitions--patrones)
8. [Manejo de excepciones de dominio en steps](#8-manejo-de-excepciones-de-dominio)
9. [Tablas de datos y esquemas de escenario](#9-tablas-de-datos-y-esquemas)
10. [Ejecutar Behat — comandos y filtros](#10-ejecutar-behat)
11. [Añadir un nuevo Context al proyecto](#11-añadir-un-nuevo-context)
12. [Anti-patrones frecuentes](#12-anti-patrones-frecuentes)

---

## 1. Arquitectura general

Behat en Hub Digital cubre el **comportamiento de la capa Application** (Use Cases /
Handlers). No es un test de UI, no es un test HTTP, no habla con Livewire. El Context
llama al Handler directamente, igual que lo haría cualquier otro consumidor.

```
.feature (Gherkin en español)
    │
    ▼
Context::método @Given/@When/@Then
    │
    ▼
Handler::handle(Input DTO)       ← capa Application
    │
    ▼
Domain Entity / Repository       ← capa Domain / Infrastructure
    │
    ▼
PostgreSQL (test DB)
```

### Relación feature ↔ context ↔ handler

Cada `.feature` tiene su Context dedicado. Ese Context inyecta el Handler
del Use Case que describe la feature:

```
Features/TramitacionSolicitudesInvestigador/envio_solicitud_prestamo.feature
                    │
                    ▼
Contexts/TramitacionSolicitudesInvestigador/EnvioSolicitudPrestamoContext.php
                    │  $this->handler = EnvioSolicitudPrestamoHandler
                    ▼
Application/UseCases/EnvioSolicitudPrestamo/EnvioSolicitudPrestamoHandler.php
```

### Estructura de directorios por módulo

```
Modules/<Modulo>/tests/Behat/
├── Features/
│   └── <NombreCapability>/
│       └── <nombre_scenario>.feature
└── Contexts/
    ├── BaseContext.php
    └── <NombreCapability>/
        └── <NombreCapability>Context.php
```

Ejemplo real — GestionPrestamosRecepciones:

```
Modules/GestionPrestamosRecepciones/tests/Behat/
├── Features/
│   ├── TramitacionSolicitudesInvestigador/
│   │   ├── envio_solicitud_prestamo.feature
│   │   └── seguimiento_solicitudes.feature
│   └── AdministracionCuratorialSolicitudesPrestamos/
│       ├── resolucion_solicitudes_prestamo.feature
│       └── definicion_recordatorios_devolucion.feature
└── Contexts/
    ├── BaseContext.php
    ├── TramitacionSolicitudesInvestigador/
    │   ├── EnvioSolicitudPrestamoContext.php
    │   └── SeguimientoSolicitudesContext.php
    └── AdministracionCuratorialSolicitudesPrestamos/
        ├── ResolucionSolicitudesPrestamoContext.php
        └── DefinicionRecordatoriosDevolucionContext.php
```

---

## 2. Configuración — behat.php

La configuración maestra vive en `behat.php` en la raíz del proyecto.
Cada módulo tiene su propia suite. Cuando añades un Context nuevo,
lo registras aquí **antes** de escribir un solo step.

```php
<?php
// behat.php — raíz del proyecto

use Behat\Testwork\ServiceContainer\Configuration\ConfigurationLoader;

return [
    'default' => [
        'suites' => [

            // ── Módulo: GestionPrestamosRecepciones ─────────────────────────
            'GestionPrestamosRecepciones' => [
                'paths'    => [
                    '%paths.base%/Modules/GestionPrestamosRecepciones/tests/Behat/Features',
                ],
                'contexts' => [
                    // BaseContext siempre primero
                    Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext::class,

                    // Contexts por capability
                    Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\EnvioSolicitudPrestamoContext::class,
                    Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador\SeguimientoSolicitudesContext::class,
                    Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\ResolucionSolicitudesPrestamoContext::class,
                    Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\AdministracionCuratorialSolicitudesPrestamos\DefinicionRecordatoriosDevolucionContext::class,
                ],
            ],

            // ── Módulo: InventarioGestionColeccion ───────────────────────────
            'InventarioGestionColeccion' => [
                'paths'    => [
                    '%paths.base%/Modules/InventarioGestionColeccion/tests/Behat/Features',
                ],
                'contexts' => [
                    Modules\InventarioGestionColeccion\Tests\Behat\Contexts\BaseContext::class,
                    Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion\ReubicacionDigitalGuiadaContext::class,
                    Modules\InventarioGestionColeccion\Tests\Behat\Contexts\TrazabilidadOperativaMovimientosCirculacion\MonitoreoTiempoExtraccionContext::class,
                    Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario\RegistroUbicacionCajasContext::class,
                    Modules\InventarioGestionColeccion\Tests\Behat\Contexts\GestionAutonomaSeguridadFisicaInventario\AlertaIncongruenciaTaxonomicaContext::class,
                ],
            ],

            // ── Módulo: CatalogoPublico ──────────────────────────────────────
            'CatalogoPublico' => [
                'paths'    => [
                    '%paths.base%/Modules/CatalogoPublico/tests/Behat/Features',
                ],
                'contexts' => [
                    Modules\CatalogoPublico\Tests\Behat\Contexts\BaseContext::class,
                    // Contexts de CatalogoPublico aquí
                ],
            ],
        ],

        'formatters' => [
            'pretty' => ['verbose' => true],
        ],
    ],
];
```

### Nota sobre namespaces en módulos nwidart

Los módulos cargan sus clases a través del PSR-4 declarado en el `composer.json`
del módulo. El namespace raíz de tests sigue el patrón:
`Modules\<NombreModulo>\Tests\Behat\...`

---

## 3. BaseContext — bootstrap de Laravel

`BaseContext` es el **único lugar** donde se arranca Laravel. Todos los
Contexts de capability lo extienden. No tiene steps.

```php
<?php
// Modules/GestionPrestamosRecepciones/tests/Behat/Contexts/BaseContext.php
declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Application;

class BaseContext implements Context
{
    protected static Application $app;

    // ── Bootstrap (una sola vez por suite) ──────────────────────────────────

    /** @BeforeSuite */
    public static function bootstrapLaravel(): void
    {
        // Carga el autoloader de Composer
        require_once __DIR__ . '/../../../../../../vendor/autoload.php';

        // Crea la aplicación Laravel
        $app = require __DIR__ . '/../../../../../../bootstrap/app.php';

        // Arranca el kernel de consola para que los service providers se registren
        $kernel = $app->make(ConsoleKernel::class);
        $kernel->bootstrap();

        static::$app = $app;
    }

    // ── Limpieza por escenario ───────────────────────────────────────────────

    /** @BeforeScenario */
    public function resetDatabase(): void
    {
        // migrate:fresh garantiza esquema limpio y ejecuta migraciones pendientes
        static::$app->make(ConsoleKernel::class)->call('migrate:fresh');
    }

    // ── Helper de acceso al contenedor ──────────────────────────────────────

    /**
     * Resuelve una dependencia del contenedor de Laravel.
     * Úsalo en los Contexts hijos para obtener Handlers y Repositories.
     *
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    protected function make(string $abstract): mixed
    {
        return static::$app->make($abstract);
    }
}
```

### Por qué `@BeforeSuite` y no `@BeforeScenario` para el bootstrap

`@BeforeSuite` se ejecuta **una vez por suite**. Arrancar Laravel (cargar providers,
compilar el contenedor) es caro. La limpieza de BD sí va en `@BeforeScenario`
porque cada escenario debe partir de estado limpio, pero el contenedor puede
reutilizarse entre escenarios de la misma suite.

---

## 4. Context por capability — plantilla completa

Este es el template que Claude usa para crear cualquier Context nuevo.
Sustituye `<Modulo>`, `<Capability>` y `<Feature>` con los valores reales.

```php
<?php
// Modules/<Modulo>/tests/Behat/Contexts/<Capability>/<Feature>Context.php
declare(strict_types=1);

namespace Modules\<Modulo>\Tests\Behat\Contexts\<Capability>;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Modules\<Modulo>\Tests\Behat\Contexts\BaseContext;
use Modules\<Modulo>\Application\UseCases\<Feature>\<Feature>Handler;
use Modules\<Modulo>\Application\UseCases\<Feature>\<Feature>Input;
use Modules\<Modulo>\Application\UseCases\<Feature>\<Feature>Output;

/**
 * Context para: <descripción breve de la capability>
 * Módulo: <Modulo>
 * Handler: <Feature>Handler
 */
final class <Feature>Context extends BaseContext
{
    // ── Handler inyectado ────────────────────────────────────────────────────

    private <Feature>Handler $handler;

    // ── Estado del escenario ─────────────────────────────────────────────────
    // Propiedades que viajan entre Dado → Cuando → Entonces

    private ?<Feature>Output $ultimaRespuesta = null;
    private ?\Throwable      $excepcionCapturada = null;

    // ── Constructor ──────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->handler = $this->make(<Feature>Handler::class);
    }

    // ── Steps: Dado (precondiciones) ─────────────────────────────────────────

    /**
     * @Given que existe <descripción del estado previo>
     */
    public function queExiste<Estado>(): void
    {
        // Sembrar el estado inicial usando el Repository (no Eloquent directo)
        // Ver sección 6: Fixtures de dominio
    }

    // ── Steps: Cuando (acción del actor) ─────────────────────────────────────

    /**
     * @When el <actor> <acción>
     */
    public function el<Actor><Acción>(): void
    {
        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new <Feature>Input(/* ... */)
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // ── Steps: Entonces (verificaciones) ─────────────────────────────────────

    /**
     * @Then <resultado esperado>
     */
    public function <resultadoEsperado>(): void
    {
        \PHPUnit\Framework\Assert::assertNotNull(
            $this->ultimaRespuesta,
            'Se esperaba una respuesta del handler pero fue null'
        );
        // assertions sobre $this->ultimaRespuesta
    }
}
```

### Ejemplo real — EnvioSolicitudPrestamoContext

```php
<?php
declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador;

use Behat\Gherkin\Node\TableNode;
use Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\BaseContext;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnvioSolicitudPrestamo\EnvioSolicitudPrestamoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnvioSolicitudPrestamo\EnvioSolicitudPrestamoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnvioSolicitudPrestamo\EnvioSolicitudPrestamoOutput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudInvalidaException;
use PHPUnit\Framework\Assert;

final class EnvioSolicitudPrestamoContext extends BaseContext
{
    private EnvioSolicitudPrestamoHandler $handler;

    private ?EnvioSolicitudPrestamoOutput $ultimaRespuesta  = null;
    private ?\Throwable                   $excepcionCapturada = null;

    public function __construct()
    {
        $this->handler = $this->make(EnvioSolicitudPrestamoHandler::class);
    }

    // ── Dado ─────────────────────────────────────────────────────────────────

    /**
     * @Given que el investigador tiene credenciales válidas
     */
    public function queElInvestigadorTieneCredencialesValidas(): void
    {
        // En este proyecto los Handlers reciben Input DTOs con datos del actor.
        // No hay sesión HTTP — el investigador se identifica por ID en el Input.
        // Este step es un marcador semántico; no necesita código si el Handler
        // no requiere autenticación previa.
    }

    /**
     * @Given que existe un espécimen con código :codigo disponible para préstamo
     */
    public function queExisteUnEspecimenDisponible(string $codigo): void
    {
        // Sembrar vía Repository — nunca Eloquent directo
        $repo    = $this->make(\Modules\GestionPrestamosRecepciones\Domain\Repositories\EspecimenRepository::class);
        $especimen = \Modules\GestionPrestamosRecepciones\Domain\Entities\Especimen::registrar(
            id:     $repo->nextIdentity(),
            codigo: $codigo,
        );
        $repo->guardar($especimen);
    }

    // ── Cuando ───────────────────────────────────────────────────────────────

    /**
     * @When el investigador envía una solicitud de préstamo con los datos:
     */
    public function elInvestigadorEnviaUnaSolicitudConLosDatos(TableNode $tabla): void
    {
        $datos = $tabla->getRowsHash();

        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new EnvioSolicitudPrestamoInput(
                    investigadorId: $datos['investigador_id'] ?? 'inv-001',
                    institucion:    $datos['institucion']     ?? '',
                    proposito:      $datos['proposito']       ?? '',
                    especimenes:    explode(',', $datos['especimenes'] ?? ''),
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    /**
     * @When el investigador envía la solicitud sin especificar institución
     */
    public function elInvestigadorEnviaLaSolicitudSinInstitucion(): void
    {
        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new EnvioSolicitudPrestamoInput(
                    investigadorId: 'inv-001',
                    institucion:    '',       // campo vacío — invariante de dominio
                    proposito:      'Investigación sistemática',
                    especimenes:    ['ESP-001'],
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }

    // ── Entonces ─────────────────────────────────────────────────────────────

    /**
     * @Then la solicitud queda registrada en estado :estado
     */
    public function laSolicitudQuedaEnEstado(string $estado): void
    {
        Assert::assertNotNull(
            $this->ultimaRespuesta,
            "Se esperaba que el handler retornara una respuesta pero lanzó: "
            . ($this->excepcionCapturada?->getMessage() ?? 'null')
        );
        Assert::assertSame($estado, $this->ultimaRespuesta->estado);
    }

    /**
     * @Then se asigna un identificador único a la solicitud
     */
    public function seAsignaUnIdentificadorUnico(): void
    {
        Assert::assertNotNull($this->ultimaRespuesta);
        Assert::assertNotEmpty($this->ultimaRespuesta->solicitudId);
    }

    /**
     * @Then el sistema rechaza la solicitud con el mensaje :mensaje
     */
    public function elSistemaRechazaLaSolicitud(string $mensaje): void
    {
        Assert::assertNotNull(
            $this->excepcionCapturada,
            'Se esperaba una excepción de dominio pero el handler no lanzó ninguna'
        );
        Assert::assertStringContainsString(
            $mensaje,
            $this->excepcionCapturada->getMessage()
        );
    }
}
```

---

## 5. Ciclo de vida y limpieza de BD

### Hooks disponibles en Behat

| Hook | Scope | Uso en Hub Digital |
|------|-------|--------------------|
| `@BeforeSuite` | 1 vez por suite | Bootstrap de Laravel (en BaseContext) |
| `@AfterSuite` | 1 vez por suite | Limpieza global si fuera necesario |
| `@BeforeScenario` | Por escenario | `migrate:fresh` — BD limpia |
| `@AfterScenario` | Por escenario | Rara vez necesario |
| `@BeforeStep` | Por step | No usar — demasiado granular |
| `@AfterStep` | Por step | No usar — demasiado granular |

### El ciclo completo de un escenario

```
@BeforeSuite  → (ya ejecutado una vez: Laravel arrancado)
@BeforeScenario → migrate:fresh → BD limpia
    Dado ...    → sembrar fixtures de dominio
    Cuando ...  → ejecutar handler, capturar respuesta o excepción
    Entonces... → hacer assertions sobre respuesta o excepción
@AfterScenario → (no se usa normalmente)
```

### Por qué `migrate:fresh` y no truncar tablas

`migrate:fresh` garantiza que:
1. El esquema está actualizado con las migraciones más recientes.
2. Las secuencias y claves foráneas se reinician limpiamente.
3. Si una migración nueva fue añadida, el escenario la detecta.

Truncar tablas manualmente es frágil — hay que actualizar la lista cada vez que
se añade una tabla y no detecta migraciones pendientes.

### Fixtures persistidas en BD de test

Por defecto Behat no usa `RefreshDatabase` de Laravel porque no extiende `TestCase`.
La estrategia es:

- `@BeforeScenario` ejecuta `migrate:fresh` → BD vacía.
- Los steps `@Given` siembran el estado necesario para ese escenario específico.
- Los seeds globales (`DatabaseSeeder`) **no se usan** en Behat — cada scenario
  es autosuficiente.

Si un escenario necesita datos de catálogo (ej. taxonomía base, roles), los siembra
en su propio `@Given`, no en un seeder compartido de suite.

---

## 6. Fixtures de dominio

El estado inicial de un escenario se siembra **a través de los Repositories de
dominio**, nunca con Eloquent directo ni con `DB::table()->insert()`.

### Patrón correcto

```php
/** @Given que existe una solicitud de préstamo en estado :estado */
public function queExisteUnaSolicitudEnEstado(string $estado): void
{
    $repo = $this->make(SolicitudPrestamoRepository::class);

    // 1. Crear la entidad usando el factory de dominio
    $solicitud = SolicitudPrestamo::crear(
        id:            $repo->nextIdentity(),
        investigadorId: InvestigadorId::fromString('inv-001'),
        institucion:   'Universidad Central',
        proposito:     'Investigación sistemática',
    );

    // 2. Si necesitamos un estado distinto al inicial, mutamos vía métodos de dominio
    if ($estado === 'aprobada') {
        $solicitud->aprobar(curadorId: CuradorId::fromString('cur-001'));
    }

    // 3. Persistir vía repository
    $repo->guardar($solicitud);

    // 4. Guardar referencia si otros steps la necesitan
    $this->solicitudExistente = $solicitud;
}
```

### Guardar referencias entre steps

Cuando un step `@Dado` crea algo que el step `@Cuando` o `@Entonces` necesita
referenciar, guárdalo en una propiedad del Context:

```php
final class ResolucionSolicitudesPrestamoContext extends BaseContext
{
    // Estado del escenario
    private ?SolicitudPrestamo $solicitudExistente   = null;
    private ?ResolucionOutput  $ultimaRespuesta       = null;
    private ?\Throwable        $excepcionCapturada    = null;

    /** @Given que existe una solicitud de préstamo pendiente */
    public function queExisteUnaSolicitudPendiente(): void
    {
        $repo = $this->make(SolicitudPrestamoRepository::class);
        $solicitud = SolicitudPrestamo::crear(/* ... */);
        $repo->guardar($solicitud);
        $this->solicitudExistente = $solicitud; // ← referencia guardada
    }

    /** @When el curador aprueba la solicitud */
    public function elCuradorAprueba(): void
    {
        Assert::assertNotNull($this->solicitudExistente, 'Falta step Dado con solicitud');

        try {
            $this->ultimaRespuesta = $this->handler->handle(
                new AprobarSolicitudInput(
                    solicitudId: (string) $this->solicitudExistente->id(),
                    curadorId:   'cur-001',
                )
            );
        } catch (\Throwable $e) {
            $this->excepcionCapturada = $e;
        }
    }
}
```

### Anti-patrón: Eloquent en fixtures

```php
// ❌ NUNCA — Eloquent directo en fixture
SolicitudPrestamoModel::create([
    'id'          => 'sol-001',
    'estado'      => 'pendiente',
    'institucion' => 'Universidad Central',
]);

// ❌ NUNCA — DB facade en fixture
DB::table('solicitudes_prestamo')->insert([...]);
```

Por qué es un error: si la lógica de dominio valida invariantes al crear la
entidad (ej. institución no puede estar vacía), `Model::create()` lo salta.
El test estaría sembrando un estado que el dominio nunca permitiría, haciendo
la fixture inútil como verificación real.

---

## 7. Step definitions — patrones

### 7.1 Step que ejecuta acción sin tabla

```php
/**
 * El patrón Gherkin entre las comillas del @When/Given/Then
 * se convierte en expresión regular de Behat.
 * Los :parametros se capturan automáticamente como argumentos.
 *
 * @When el curador aprueba la solicitud con id :solicitudId
 */
public function elCuradorApruebaLaSolicitud(string $solicitudId): void
{
    try {
        $this->ultimaRespuesta = $this->handler->handle(
            new AprobarSolicitudInput(solicitudId: $solicitudId, curadorId: 'cur-001')
        );
    } catch (\Throwable $e) {
        $this->excepcionCapturada = $e;
    }
}
```

### 7.2 Step que recibe tabla de datos (TableNode)

```php
/**
 * @When el investigador envía una solicitud con los datos:
 */
public function elInvestigadorEnviaConDatos(TableNode $tabla): void
{
    // getRowsHash() — tabla de dos columnas: | campo | valor |
    $datos = $tabla->getRowsHash();

    // getHash() — tabla con cabecera + filas múltiples (para listas)
    // $filas = $tabla->getHash();  // array de arrays asociativos

    $this->handler->handle(new EnvioSolicitudPrestamoInput(
        investigadorId: $datos['investigador_id'],
        institucion:    $datos['institucion'],
        proposito:      $datos['proposito'],
        especimenes:    explode(',', $datos['especimenes']),
    ));
}
```

Gherkin correspondiente:
```gherkin
Cuando el investigador envía una solicitud con los datos:
  | investigador_id | inv-001                     |
  | institucion     | Universidad Central         |
  | proposito       | Clasificación sistemática   |
  | especimenes     | ESP-001,ESP-002             |
```

### 7.3 Step de assertion sobre el Output DTO

```php
/**
 * @Then la solicitud queda en estado :estadoEsperado
 */
public function laSolicitudQuedaEnEstado(string $estadoEsperado): void
{
    // 1. Verificar que no hubo excepción inesperada
    if ($this->excepcionCapturada !== null) {
        throw new \RuntimeException(
            "El handler lanzó una excepción inesperada: "
            . $this->excepcionCapturada->getMessage(),
            0,
            $this->excepcionCapturada
        );
    }

    // 2. Verificar que hay respuesta
    Assert::assertNotNull(
        $this->ultimaRespuesta,
        'El handler no retornó ninguna respuesta'
    );

    // 3. Assertion sobre el Output DTO
    Assert::assertSame($estadoEsperado, $this->ultimaRespuesta->estado);
}
```

### 7.4 Step de assertion sobre persistencia

Cuando el escenario necesita verificar que algo quedó guardado en BD,
usa el Repository — no SQL directo:

```php
/**
 * @Then la solicitud queda registrada en el sistema
 */
public function laSolicitudQuedaRegistrada(): void
{
    Assert::assertNotNull($this->ultimaRespuesta);

    $repo      = $this->make(SolicitudPrestamoRepository::class);
    $solicitud = $repo->buscarPorId(
        SolicitudPrestamoId::fromString($this->ultimaRespuesta->solicitudId)
    );

    Assert::assertNotNull(
        $solicitud,
        "La solicitud {$this->ultimaRespuesta->solicitudId} no existe en el repository"
    );
}
```

---

## 8. Manejo de excepciones de dominio en steps

El patrón estándar: el `@When` **captura** la excepción en lugar de dejarla
propagarse (lo que haría fallar el escenario con un error de PHP, no con un
mensaje útil). El `@Entonces` **verifica** la excepción capturada.

### Patrón de captura en Cuando

```php
/** @When el investigador envía la solicitud con institución vacía */
public function elInvestigadorEnviaConInstitucionVacia(): void
{
    try {
        $this->ultimaRespuesta = $this->handler->handle(
            new EnvioSolicitudPrestamoInput(
                investigadorId: 'inv-001',
                institucion:    '',   // ← dispara invariante de dominio
                proposito:      'Investigación',
                especimenes:    ['ESP-001'],
            )
        );
    } catch (\Throwable $e) {
        // No relanzar — el step @Entonces verificará el tipo y mensaje
        $this->excepcionCapturada = $e;
    }
}
```

### Patrones de assertion en Entonces

```php
/**
 * @Then el sistema rechaza la solicitud indicando que la institución es obligatoria
 */
public function elSistemaRechazaPorInstitucionObligatoria(): void
{
    Assert::assertNotNull(
        $this->excepcionCapturada,
        'Se esperaba una excepción de dominio pero el handler completó sin error'
    );
    Assert::assertInstanceOf(
        \Modules\GestionPrestamosRecepciones\Domain\Exceptions\SolicitudInvalidaException::class,
        $this->excepcionCapturada
    );
    Assert::assertStringContainsString(
        'institución',
        $this->excepcionCapturada->getMessage()
    );
}

/**
 * @Then el sistema rechaza la solicitud con el mensaje :mensajeEsperado
 */
public function elSistemaRechazaConMensaje(string $mensajeEsperado): void
{
    Assert::assertNotNull($this->excepcionCapturada);
    Assert::assertStringContainsString(
        $mensajeEsperado,
        $this->excepcionCapturada->getMessage()
    );
}
```

### Verificar que NO hubo excepción

En steps de camino feliz, verifica explícitamente que no quedó una excepción
capturada de un step previo:

```php
/** @Then la operación fue exitosa */
public function laOperacionFueExitosa(): void
{
    if ($this->excepcionCapturada !== null) {
        Assert::fail(
            'La operación lanzó una excepción inesperada: '
            . $this->excepcionCapturada->getMessage()
        );
    }
    Assert::assertNotNull($this->ultimaRespuesta);
}
```

---

## 9. Tablas de datos y esquemas de escenario

### TableNode — tabla de datos inline

Usada en steps `@When` o `@Given` para pasar múltiples campos:

```gherkin
Cuando el investigador envía una solicitud de préstamo con los datos:
  | investigador_id | inv-001                   |
  | institucion     | Universidad Central       |
  | proposito       | Revisión taxonómica       |
  | especimenes     | ESP-001,ESP-002,ESP-003    |
```

```php
/** @When el investigador envía una solicitud de préstamo con los datos: */
public function elInvestigadorEnviaConDatos(TableNode $tabla): void
{
    $datos = $tabla->getRowsHash(); // ['investigador_id' => 'inv-001', ...]
}
```

### TableNode con múltiples filas — lista de ítems

```gherkin
Dado que los siguientes especímenes están disponibles:
  | codigo  | taxonomia          |
  | ESP-001 | Morpho helenor     |
  | ESP-002 | Danaus plexippus   |
  | ESP-003 | Heliconius melpomene |
```

```php
/** @Given que los siguientes especímenes están disponibles: */
public function queLosEspecimenesEstandDisponibles(TableNode $tabla): void
{
    $repo = $this->make(EspecimenRepository::class);

    foreach ($tabla->getHash() as $fila) {
        // getHash() → [['codigo' => 'ESP-001', 'taxonomia' => '...'], ...]
        $especimen = Especimen::registrar(
            id:       $repo->nextIdentity(),
            codigo:   $fila['codigo'],
            taxon:    $fila['taxonomia'],
        );
        $repo->guardar($especimen);
    }
}
```

### Esquema del escenario (Outline) — múltiples casos

```gherkin

# language: es

Característica: Validación de datos de solicitud de préstamo
  Como investigador
  Quiero recibir mensajes claros cuando mis datos son inválidos
  Para corregirlos antes de enviar

  Esquema del escenario: El investigador no puede enviar solicitud con campos obligatorios vacíos
    Cuando el investigador envía una solicitud con los datos:
      | investigador_id | inv-001    |
      | institucion     | <valor>    |
      | proposito       | <proposito> |
      | especimenes     | ESP-001    |
    Entonces el sistema rechaza la solicitud con el mensaje "<mensaje>"

    Ejemplos:
      | valor | proposito               | mensaje                            |
      |       | Investigación válida    | institución no puede estar vacía   |
      | USFQ  |                         | propósito no puede estar vacío     |
```

Con `Esquema del escenario`, Behat ejecuta el scenario tantas veces como filas
de `Ejemplos`, sustituyendo `<valor>`, `<proposito>` y `<mensaje>` en cada paso.

---

## 10. Ejecutar Behat

### Comandos básicos

```bash

# Ejecutar todas las suites

php artisan behat

# Ejecutar una suite específica

php artisan behat --suite=GestionPrestamosRecepciones

# Ejecutar un feature file específico

php artisan behat Modules/GestionPrestamosRecepciones/tests/Behat/Features/TramitacionSolicitudesInvestigador/envio_solicitud_prestamo.feature

# Ejecutar un escenario específico por número de línea

php artisan behat Modules/GestionPrestamosRecepciones/tests/Behat/Features/TramitacionSolicitudesInvestigador/envio_solicitud_prestamo.feature:25

# Ejecutar con nombre de escenario (grep)

php artisan behat --name="solicitud de préstamo válida"

# Dry run — verifica que todos los steps están implementados sin ejecutarlos

php artisan behat --suite=GestionPrestamosRecepciones --dry-run

# Listar steps disponibles

php artisan behat --suite=GestionPrestamosRecepciones --definitions

# Formato más verbose

php artisan behat --suite=GestionPrestamosRecepciones --format=pretty --verbose
```

### Si no tienes el comando artisan de Behat

```bash

# Directamente con el binario de vendor

vendor/bin/behat --config=behat.php --suite=GestionPrestamosRecepciones
```

### Interpretar la salida

```
Feature: Envío de solicitud de préstamo

  Escenario: El investigador envía una solicitud válida          # verde = paso

    Dado que existe un espécimen con código ESP-001...  ✓
    Cuando el investigador envía una solicitud...        ✓
    Entonces la solicitud queda en estado "pendiente"   ✓

  Escenario: El investigador envía solicitud sin institución     # rojo = falla

    Dado que existe un espécimen con código ESP-002...  ✓
    Cuando el investigador envía la solicitud...         ✓
    Entonces el sistema rechaza la solicitud...          ✗  ← assertion fallida
      Failed asserting that null is not null.
```

Colores estándar: **verde** = paso exitoso, **azul** = paso pendiente (sin implementación), **rojo** = falla.

---

## 11. Añadir un nuevo Context al proyecto

Checklist completo para cuando se implementa una nueva capability:

### Paso 1 — Crear el feature file

```
Modules/<Modulo>/tests/Behat/Features/<NombreCapability>/<nombre>.feature
```

```gherkin

# language: es

Característica: <Nombre de la capability>
  Como <actor>
  Quiero <acción>
  Para <beneficio>

  Escenario: <Actor> <acción exitosa>
    Dado que <precondición>
    Cuando el <actor> <acción>
    Entonces <resultado>
```

### Paso 2 — Crear el Context

```
Modules/<Modulo>/tests/Behat/Contexts/<NombreCapability>/<Feature>Context.php
```

Usa la plantilla de la sección 4. Asegúrate de:
- Extender `BaseContext` del mismo módulo.
- Inyectar el Handler correcto en el constructor.
- Declarar propiedades de estado (`$ultimaRespuesta`, `$excepcionCapturada`).

### Paso 3 — Registrar en behat.php

```php
// En la suite del módulo correspondiente, añadir:
Modules\<Modulo>\Tests\Behat\Contexts\<Capability>\<Feature>Context::class,
```

**Este paso es obligatorio antes de correr Behat.** Si el Context no está
registrado, Behat no encontrará los steps y los marcará como indefinidos.

### Paso 4 — Verificar con dry-run

```bash
php artisan behat --suite=<Modulo> --dry-run
```

La salida mostrará qué steps están pendientes de implementar (en azul).
Un step sin implementación = método `@Given/@When/@Then` faltante en el Context.

### Paso 5 — Implementar steps y ejecutar

```bash
php artisan behat --suite=<Modulo>
```

Iterar hasta que todos los escenarios de la suite estén en verde.

---

## 12. Anti-patrones frecuentes

### ❌ Eloquent en steps o fixtures

```php
// MAL
public function queExisteUnaSolicitud(): void
{
    \Modules\GestionPrestamosRecepciones\Infrastructure\Models\SolicitudPrestamoModel::create([
        'id' => 'sol-001', 'estado' => 'pendiente'
    ]);
}

// BIEN — Repository de dominio
public function queExisteUnaSolicitud(): void
{
    $repo = $this->make(SolicitudPrestamoRepository::class);
    $sol  = SolicitudPrestamo::crear(id: $repo->nextIdentity(), /* ... */);
    $repo->guardar($sol);
    $this->solicitudExistente = $sol;
}
```

### ❌ Un Context para todo el módulo

```php
// MAL — un Context con steps de múltiples capabilities
class GestionPrestamosContext extends BaseContext
{
    /** @When el investigador envía solicitud */
    /** @When el curador aprueba solicitud */
    /** @When el curador define recordatorio */
    /** @When el investigador consulta seguimiento */
    // ... 40 steps mezclados
}

// BIEN — un Context por capability
class EnvioSolicitudPrestamoContext extends BaseContext { /* solo envío */ }
class ResolucionSolicitudesPrestamoContext extends BaseContext { /* solo resolución */ }
```

### ❌ Lanzar la excepción en el paso @Cuando

```php
// MAL — la excepción propaga y Behat marca el step como error, no como falla
public function elInvestigadorEnviaConDatosInvalidos(): void
{
    $this->handler->handle(new Input(institucion: ''));
    // Si lanza excepción → Behat muestra "Error" no "Failed" → difícil leer
}

// BIEN — capturar en Cuando, verificar en Entonces
public function elInvestigadorEnviaConDatosInvalidos(): void
{
    try {
        $this->handler->handle(new Input(institucion: ''));
    } catch (\Throwable $e) {
        $this->excepcionCapturada = $e;
    }
}
```

### ❌ Estado global o estático entre escenarios

```php
// MAL — propiedad estática persiste entre escenarios de la misma suite
private static ?Output $ultimaRespuesta = null;

// BIEN — propiedad de instancia, se resetea con cada new Context por escenario
private ?Output $ultimaRespuesta = null;
```

Behat instancia los Contexts **una vez por escenario**, así que las propiedades
de instancia se reinician automáticamente. Las estáticas no.

### ❌ HTTP en el Context

```php
// MAL — el Context no habla con HTTP
public function elInvestigadorEnviaLaSolicitud(): void
{
    $response = $this->client->post('/api/solicitudes', [...]);
}

// BIEN — el Context habla con el Handler
public function elInvestigadorEnviaLaSolicitud(): void
{
    $this->ultimaRespuesta = $this->handler->handle(new Input(...));
}
```

Los tests HTTP van en Pest Feature tests, no en Behat.

### ❌ Múltiples Cuando en un escenario

```gherkin

# MAL

Cuando el investigador envía la solicitud
Y el curador aprueba la solicitud   ← segundo Cuando disfrazado de Y
Entonces el estado es "aprobada"

# BIEN — separar en dos escenarios o usar Dado para el primero

Dado que existe una solicitud en estado "pendiente"
Cuando el curador aprueba la solicitud
Entonces la solicitud queda en estado "aprobada"
```

---

## 12. Scaffold automático con `behat:scaffold`

Siempre que agregues una nueva feature, usa este comando en lugar de crear el context manualmente:

```bash
php artisan behat:scaffold {modulo} {capability} {feature}
```

Ejemplo real:
```bash
php artisan behat:scaffold GestionPrestamosRecepciones RecepcionValidacionLotesEspecimenesYDatos recepcion_muestras_biologicas
```

### Qué hace el comando

1. Valida que `Features/<capability>/<feature>.feature` existe
2. Parsea el `.feature` y extrae **todos los step patterns únicos**
3. Genera `Contexts/<capability>/<FeatureName>Context.php` con stubs `throw new PendingException`
4. Registra el context en `behat.php` automáticamente
5. Formatea el archivo con Pint
6. Corre la feature al final para confirmar que los steps son detectados

### Naming del context

El nombre del context se deriva del archivo `.feature`:

| Feature file | Context class |
|---|---|
| `recepcion_muestras_biologicas.feature` | `RecepcionMuestrasBiologicasContext` |
| `gestion_centralizada_entidades_depositantes.feature` | `GestionCentralizadaEntidadesDepositantesContext` |

**Un context por feature. Nunca un context por capability completa.**

### Manejo automático de `Esquema del escenario`

El comando parsea el `.feature` directamente — **no usa `--append-snippets`**.
Esto resuelve el problema de duplicados: los `<param>` del Esquema se convierten en `:param` (un solo método parametrizado):

```gherkin

# Feature con Esquema del escenario

Cuando el curador registra un fallo por "<causa_fallo>" en la muestra
```

```php
// Context generado — 1 método parametrizado ✅
#[When('el curador registra un fallo por ":causa_fallo" en la muestra')]
public function elCuradorRegistraUnFalloPorEnLaMuestra(string $causa_fallo): void
{
    throw new PendingException;
}
```

Si hubieras usado `--append-snippets`, habrías obtenido N métodos duplicados (uno por cada ejemplo). El comando lo evita automáticamente.

---

## Resumen

- **BaseContext** → bootstrap de Laravel (`@BeforeSuite`) + `migrate:fresh` (`@BeforeScenario`). Sin steps.
- **Context por capability** → extiende BaseContext, inyecta el Handler en constructor, captura excepciones en `@When`.
- **Fixtures** → siempre vía Repository de dominio, nunca Eloquent.
- **Estado entre steps** → propiedades de instancia del Context (`$ultimaRespuesta`, `$excepcionCapturada`).
- **Assertions** → `PHPUnit\Framework\Assert::*` en los steps `@Then`.
- **behat.php** → registrar cada nuevo Context antes de ejecutar.
- **Dry-run primero** → verificar steps implementados antes del primer `migrate:fresh`.

> **Regla de oro:** si borras la capa HTTP del proyecto, los tests de Behat
> deben seguir pasando. El Context llama al Handler; el Handler no sabe que
> existe Behat.