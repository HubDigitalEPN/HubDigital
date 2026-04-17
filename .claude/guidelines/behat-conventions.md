# behat-conventions.md
# Convenciones de Behat — Hub Digital

> **Propósito de este archivo:** reglas no negociables que Claude Code debe respetar
> en TODO momento al crear, modificar o ejecutar tests de Behat. No es un tutorial;
> es una lista de restricciones. Para ejemplos completos de implementación, usa el
> skill `behat`.

---

## 1. Estructura de archivos — dónde va cada cosa

La estructura es por módulo nwidart. No existe un directorio global `features/` en la raíz.

```
Modules/<Modulo>/tests/Behat/
├── Features/
│   └── <NombreCapabilidad>/          # Carpeta por capability del módulo
│       └── <nombre_scenario>.feature
└── Contexts/
    ├── BaseContext.php               # Bootstrap de Laravel — SOLO eso
    └── <NombreCapabilidad>/
        └── <NombreCapabilidad>Context.php
```

**Reglas de estructura:**

- Un `.feature` = una funcionalidad cohesiva, no un módulo completo.
- Los features se agrupan en subcarpetas por **capability** (ej. `TramitacionSolicitudesInvestigador`), no por actor ni por módulo.
- Los Contexts se agrupan en subcarpetas que **espejean** las carpetas de Features.
- `BaseContext.php` existe en todos los módulos y contiene **únicamente** el bootstrap de Laravel. No tiene steps.

**Nunca:**

- ❌ Un solo Context para todo el módulo (patrón `InventarioGestionColeccionContext` — legado, no replicar).
- ❌ Features en la raíz del proyecto.
- ❌ Un Context que mezcle steps de dos capabilities distintas.

---

## 2. Configuración — behat.php

La configuración maestra vive en `behat.php` en la raíz. Cada módulo tiene su propia suite.

**Reglas:**

- Una suite por módulo nwidart. No una suite global.
- Cada suite lista **todos** sus Contexts explícitamente. Behat no autodescubre.
- Cuando se añade un nuevo Context, se agrega a la suite correspondiente en `behat.php` antes de implementar cualquier step.

```php
// Ejemplo de estructura de suite (no copiar literalmente — leer behat.php actual)
'GestionPrestamosRecepciones' => [
    'paths'    => ['Modules/GestionPrestamosRecepciones/tests/Behat/Features'],
    'contexts' => [
        BaseContext::class,
        EnvioSolicitudPrestamoContext::class,
        SeguimientoSolicitudesContext::class,
        ResolucionSolicitudesPrestamoContext::class,
    ],
],
```

---

## 3. Idioma y palabras clave Gherkin

- **Todos** los archivos `.feature` están en español.
- La primera línea de cada feature es `# language: es` (con espacio después de `#`).
- Las keywords son las españolas: `Característica`, `Escenario`, `Esquema del escenario`, `Dado`, `Cuando`, `Entonces`, `Y`, `Pero`.
- El **lenguaje de negocio** (sustantivos, verbos del dominio) viene del glosario de Hub Digital — ver `ubiquitous-language.md`.

**Nunca:**

- ❌ Mezclar keywords en inglés y español en el mismo archivo.
- ❌ `Feature:` o `Scenario:` en un archivo con `# language: es`.

---

## 4. Redacción de scenarios — reglas Gherkin

### 4.1 Actor en el título del scenario

Cada `Escenario` debe nombrar al actor que ejecuta la acción:

```gherkin
Escenario: El investigador envía una solicitud de préstamo válida
Escenario: El curador aprueba una solicitud pendiente
Escenario: El sistema registra la ubicación de una caja nueva
```

Los actores válidos por módulo son:

| Módulo | Actores |
|--------|---------|
| GestionPrestamosRecepciones | El investigador, El curador |
| InventarioGestionColeccion | El curador, El sistema |
| CatalogoPublico | El visitante, El investigador |

**Nunca** usar "el usuario" como actor — es ambiguo y no pertenece al ubiquitous language.

### 4.2 Redacción de steps

- `Dado` → precondición de estado del sistema (qué existe en la BD antes de actuar).
- `Cuando` → acción que ejecuta el actor (una sola acción por `Cuando`).
- `Entonces` → resultado observable esperado (sin lógica condicional).
- No usar `Y` ni `Pero` como primer step de un bloque.

```gherkin
# ✅ Correcto
Dado que existe una solicitud de préstamo en estado "pendiente"
Cuando el curador aprueba la solicitud
Entonces la solicitud queda en estado "aprobada"
Y el investigador recibe una notificación de aprobación

# ❌ Incorrecto — acción y verificación mezcladas en Cuando
Cuando el curador aprueba la solicitud y verifica el estado
```

### 4.3 Un Cuando por escenario

Un `Cuando` por `Escenario`. Si necesitas dos acciones, son dos escenarios.

### 4.4 Datos en tablas, no en el título

```gherkin
# ✅ Correcto
Esquema del escenario: El investigador no puede enviar solicitud con datos inválidos
  Cuando el investigador envía la solicitud con los datos:
    | campo            | valor |
    | institucion      |       |
  Entonces el sistema rechaza la solicitud con el mensaje "<mensaje>"
  Ejemplos:
    | mensaje                          |
    | La institución no puede estar vacía |

# ❌ Incorrecto — datos en el título del escenario
Escenario: El investigador envía solicitud con institución vacía y recibe error
```

---

## 5. Contexts — responsabilidades y restricciones

### 5.1 BaseContext — solo bootstrap

`BaseContext.php` en cada módulo hace **una sola cosa**: arrancar Laravel.

```php
// Lo que BaseContext PUEDE tener:
// - @BeforeSuite con bootstrap de la app
// - @BeforeScenario con migración/limpieza de BD
// - Propiedades compartidas: $app, $kernel

// Lo que BaseContext NUNCA puede tener:
// - Métodos @Given / @When / @Then
// - Lógica de negocio
// - Llamadas a Use Cases o repositorios
```

### 5.2 Contexts específicos — una capability, una responsabilidad

Cada Context de capability:

- Extiende `BaseContext`.
- Contiene **únicamente** los steps de su capability.
- Accede a la capa de aplicación **inyectando el Handler** del Use Case, nunca llamando Facades ni Eloquent directamente.

```php
// ✅ Correcto — el Context habla con la capa Application
private EnvioSolicitudPrestamoHandler $handler;

public function __construct()
{
    parent::__construct();
    $this->handler = $this->app->make(EnvioSolicitudPrestamoHandler::class);
}

/** @When el investigador envía la solicitud con los datos: */
public function elInvestigadorEnviaLaSolicitud(TableNode $tabla): void
{
    $datos = $tabla->getRowsHash();
    $this->ultimaRespuesta = ($this->handler)(new EnvioSolicitudPrestamoInput(
        institucion: $datos['institucion'] ?? '',
        // ...
    ));
}

// ❌ Incorrecto — acceso directo a Eloquent desde el Context
public function elInvestigadorEnviaLaSolicitud(): void
{
    SolicitudPrestamo::create([...]); // Nunca
}

// ❌ Incorrecto — Facade desde el Context
public function elInvestigadorEnviaLaSolicitud(): void
{
    DB::table('solicitudes')->insert([...]); // Nunca
}
```

### 5.3 Estado compartido entre steps

El estado entre `Dado`, `Cuando` y `Entonces` dentro de un escenario se guarda en **propiedades del Context** (`$this->ultimaRespuesta`, `$this->excepcionCapturada`, etc.). Nunca en variables estáticas ni en el contenedor de Laravel.

```php
private ?EnvioSolicitudPrestamoOutput $ultimaRespuesta = null;
private ?\Throwable $excepcionCapturada = null;
```

---

## 6. Limpieza de base de datos entre escenarios

Cada escenario parte de una BD limpia. La limpieza se ejecuta en `BaseContext` con `@BeforeScenario`:

```php
/** @BeforeScenario */
public function limpiarBaseDeDatos(): void
{
    Artisan::call('migrate:fresh');
}
```

**Reglas:**

- `migrate:fresh` en `@BeforeScenario`, no en `@BeforeSuite`.
- No usar truncate manual de tablas — deja pasar migraciones pendientes sin avisarte.
- Si el escenario necesita datos base (catálogos, roles), sembrarlos en el propio `Dado` del scenario, no en un seeder global de suite.

---

## 7. Nombrado de archivos y clases

| Artefacto | Convención | Ejemplo |
|-----------|-----------|---------|
| Feature file | `snake_case.feature` | `envio_solicitud_prestamo.feature` |
| Carpeta capability (Features) | `PascalCase` | `TramitacionSolicitudesInvestigador/` |
| Carpeta capability (Contexts) | `PascalCase` (igual que Features) | `TramitacionSolicitudesInvestigador/` |
| Context class | `<Capability>Context.php` | `EnvioSolicitudPrestamoContext.php` |
| Namespace de Context | `Modules\<Modulo>\Tests\Behat\Contexts\<Capability>` | `Modules\GestionPrestamosRecepciones\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador` |

---

## 8. Qué NO hace Behat en este proyecto

Behat cubre el **comportamiento de la capa Application** (Use Cases). No es el lugar para:

- ❌ Tests de unidad de entidades o Value Objects → eso es Pest/Unit.
- ❌ Tests de repositorios o infraestructura → eso es Pest/Integration.
- ❌ Tests de UI o HTTP → no hay controladores en los scenarios; el Context llama handlers directamente.
- ❌ Tests del chatbot de CatálogoPublico → esa integración con el AI SDK se cubre con tests de Pest específicos.

---

## 9. Checklist antes de marcar un scenario como implementado

- [ ] El archivo `.feature` tiene `# language: es` en la primera línea.
- [ ] El actor del scenario existe en la tabla de actores válidos (sección 4.1).
- [ ] Hay exactamente un `Cuando` por `Escenario`.
- [ ] El Context correspondiente está listado en `behat.php`.
- [ ] El Context extiende `BaseContext` y no tiene lógica de bootstrap propia.
- [ ] Los steps acceden al Handler del Use Case, no a Eloquent ni Facades.
- [ ] El estado entre steps se guarda en propiedades del Context.
- [ ] El scenario pasa con `php artisan behat --suite=<Modulo>`.
