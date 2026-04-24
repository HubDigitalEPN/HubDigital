# behat-conventions.md
# Behat Conventions — Hub Digital

> **Purpose of this file:** non-negotiable rules that Claude Code must follow
> at ALL times when creating, modifying, or running Behat tests. This is not a tutorial;
> it is a list of constraints. For full implementation examples, use the
> `behat` skill.

---

## 1. File structure — where each thing goes

The structure is per nwidart module. There is no global `features/` directory at the root.

```
Modules/<Module>/tests/Behat/
├── Features/
│   └── <CapabilityName>/          # Folder per module capability
│       └── <scenario_name>.feature
└── Contexts/
    ├── BaseContext.php               # Laravel bootstrap — ONLY that
    └── <CapabilityName>/
        └── <FeatureName>Context.php  # One Context per feature file
```

**Structure rules:**

- One `.feature` = one cohesive functionality, not an entire module.
- Features are grouped in subfolders by **capability** (e.g. `TramitacionSolicitudesInvestigador`).
- Contexts are grouped in subfolders that **mirror** the Features folders.
- **One Context per feature file** — never one Context for the whole capability.
- `BaseContext.php` exists in all modules and contains **only** the Laravel bootstrap. It has no steps.

**Never:**

- ❌ A single Context for the entire module or capability.
- ❌ Features at the project root.
- ❌ A Context that mixes steps from two different feature files.

---

## 2. Configuration — behat.php

The master configuration lives in `behat.php` at the root. Each module has its own suite.

**Rules:**

- One suite per nwidart module. Not a global suite.
- Each suite lists **all** its Contexts explicitly. Behat does not autodiscover.
- When a new Context is added, it is registered in the corresponding suite in `behat.php`
  **before implementing any step**.

```php
// Suite structure example (do not copy literally — read the current behat.php)
'LoanReceptionManagement' => [
    'paths'    => ['Modules/LoanReceptionManagement/tests/Behat/Features'],
    'contexts' => [
        BaseContext::class,
        EnvioSolicitudPrestamoContext::class,
        SeguimientoSolicitudesContext::class,
        ResolucionSolicitudesPrestamoContext::class,
    ],
],
```

---

## 3. Language and Gherkin keywords

- **All** `.feature` files are written in **Spanish**.
- The first line of each feature is `# language: es` (with a space after `#`).
- Use Spanish keywords: `Característica`, `Escenario`, `Esquema del escenario`,
  `Dado`, `Cuando`, `Entonces`, `Y`, `Pero`.
- The **business language** (domain nouns and verbs) comes from the Hub Digital
  glossary — see `ubiquitous-language.md`.

**Never:**

- ❌ Mix English and Spanish keywords in the same file.
- ❌ Use `Feature:` or `Scenario:` in a file with `# language: es`.

---

## 4. Writing scenarios — Gherkin rules

### 4.1 Actor in the scenario title

Each `Escenario` must name the actor performing the action:

```gherkin
Escenario: Guardar una solicitud como borrador
Escenario: El curador aprueba una solicitud pendiente
```

Valid actors per module:

| Module                    | Actors                        |
|---------------------------|-------------------------------|
| LoanReceptionManagement   | el investigador, el curador   |
| InventoryCollectionManagement | el curador, el sistema    |
| PublicCatalog             | el visitante, el investigador |

**Never** use "el usuario" — it is ambiguous and does not belong to the ubiquitous language.

### 4.2 Writing steps

- `Dado` → system state precondition (what exists before the action).
- `Cuando` → single action performed by the actor.
- `Entonces` → expected observable outcome. No conditional logic.
- Do not use `Y` or `Pero` as the first step of a block.

```gherkin
# ✅ Correct
Dado que existe una solicitud en estado borrador
Cuando el investigador envía la solicitud
Entonces la solicitud queda en estado enviada

# ❌ Incorrect — two actions in one Cuando
Cuando el investigador envía la solicitud y verifica el estado
```

### 4.3 One Cuando per scenario

One `Cuando` per `Escenario`. If two actions are needed, use `Dado` for the first
or split into two separate scenarios.

### 4.4 Data in tables, not in titles

```gherkin
# ✅ Correct
Esquema del escenario: No permitir enviar una solicitud con información incompleta
    Dado que existe una solicitud en estado <estado_previo> con información incompleta
    Cuando el investigador envía la solicitud
    Entonces la solicitud permanece en estado <estado_previo>

    Ejemplos:
        | estado_previo |
        | borrador      |
        | observada     |

# ❌ Incorrect — data embedded in the title
Escenario: El investigador envía solicitud en borrador con institución vacía y recibe error
```

---

## 5. What Behat covers — and what does not

Behat in Hub Digital tests **observable behavior through Use Case Handlers**
(Application layer). Because the Context calls the Handler with the real Laravel
container and a real test database, Behat exercises Domain and Infrastructure
**as an integrated whole** — not in isolation.

### Coverage map

| Tool              | What it covers                                              | Isolation level        |
|-------------------|-------------------------------------------------------------|------------------------|
| **Behat**         | Use Case behavior (Application) exercising Domain + Infra   | Integrated (real DB)   |
| **Pest / Unit**   | Domain rules in isolation: entities, value objects, domain services | Pure unit (no DB) |
| **Pest / Integration** | Infrastructure adapters in isolation: Eloquent repositories, external adapters | One adapter + DB |

### The golden rule

> If you deleted the HTTP layer from the project, all Behat tests must still pass.
> The Context calls the Handler. The Handler does not know Behat exists.

### What Behat does NOT do

- ❌ Replace unit tests for entities or value objects → use **Pest/Unit**.
- ❌ Replace integration tests for Eloquent repositories in isolation → use **Pest/Integration**.
- ❌ Test HTTP controllers, FormRequests, or API responses → use **Pest/Feature**.
- ❌ Test UI or browser interactions → there are no HTTP calls in Contexts.
- ❌ Test the PublicCatalog chatbot AI integration → dedicated Pest tests.

---

## 6. BDD development flow — per feature

This is the mandatory order of work when implementing a feature.
**Do not skip steps or reorder them.**

```
Per feature (e.g. Cap1 F1 — Envío de solicitud de préstamo):

  Step 1 — Pest / Unit
            Write unit tests for the domain rules the feature implies.
            Entities, value objects, invariants — no Handler, no DB.
            Example: SolicitudPrestamo starts in borrador state.
                     SolicitudPrestamo with duration > 12 months without
                     justification throws a domain exception.

  Step 2 — Behat scaffold
            Run: php artisan behat:scaffold <Module> <Capability> <feature_file>
            The command parses the .feature and generates the Context with
            PendingException stubs. Register the Context in behat.php.
            Run with --dry-run to confirm all steps are detected.
            At this point Behat is RED — no Handler exists yet.

  Step 3 — Generate the minimum to turn Behat green
            In this exact order (each piece unlocks the next):
              1. Domain entity / value objects  (if not done in Step 1)
              2. Domain repository interface    (Domain/Repositories/)
              3. Handler + Input + Output       (Application/UseCases/<Feature>/)
              4. Migration                      (database/migrations/)
              5. Eloquent repository            (Infrastructure/Persistence/Eloquent/)
              6. Binding in ServiceProvider     ($bindings array)
            The Handler may contain minimal logic — just enough to satisfy
            the scenarios. Do not implement full business rules yet.

  Step 4 — Implement the Context steps
            Given  → seed state using the domain Repository or a setup Handler.
                     Never use Eloquent directly.
            When   → call the Handler, wrap in try/catch, store in $this->lastResponse
                     or $this->capturedException.
            Then   → PHPUnit assertions on $this->lastResponse or $this->capturedException.

  Step 5 — Behat green ✅
            All scenarios of the feature pass.

  Step 6 — Complete the Handler logic
            Implement full business rules, domain events, invariant enforcement.
            Behat must remain green after this step.

  Step 7 — Pest / Integration
            Test the Eloquent repository in isolation against a real DB.
            Cover: save, find, nextIdentity, filtering.

  Step 8 — UI / Frontend
            Only once Steps 1–7 are green and stable.
```

---

## 7. Contexts — responsibilities and constraints

### 7.1 BaseContext — bootstrap only

`BaseContext.php` does **one thing only**: boot Laravel and reset the DB.

```php
// What BaseContext CAN have:
// - @BeforeSuite  with app bootstrap (once per suite)
// - @BeforeScenario with migrate:fresh (once per scenario)
// - protected make() helper
// - Shared properties: $app

// What BaseContext MUST NEVER have:
// - @Given / @When / @Then step methods
// - Business logic of any kind
// - Direct calls to Use Cases or repositories
```

### 7.2 Feature Contexts — one feature, one responsibility

Each feature Context:

- Extends `BaseContext` of the same module.
- Contains **only** the steps for its feature file.
- Resolves Use Case Handlers via `$this->make(Handler::class)` in the constructor.
- **Never** calls Eloquent models or Laravel Facades directly.

```php
// ✅ Correct — Context talks to the Application layer via Handler
private RegistrarSolicitudHandler $registrarHandler;
private EnviarSolicitudHandler    $enviarHandler;

public function __construct()
{
    $this->registrarHandler = $this->make(RegistrarSolicitudHandler::class);
    $this->enviarHandler    = $this->make(EnviarSolicitudHandler::class);
}

/** @When el investigador registra la solicitud */
public function elInvestigadorRegistraLaSolicitud(): void
{
    try {
        $this->ultimaRespuesta = ($this->registrarHandler)(
            new RegistrarSolicitudInput(/* ... */)
        );
    } catch (\Throwable $e) {
        $this->excepcionCapturada = $e;
    }
}

// ❌ Incorrect — Eloquent in the Context
public function elInvestigadorRegistraLaSolicitud(): void
{
    SolicitudPrestamoModel::create([...]); // Never
}

// ❌ Incorrect — Facade in the Context
public function elInvestigadorRegistraLaSolicitud(): void
{
    DB::table('solicitudes_prestamo')->insert([...]); // Never
}
```

### 7.3 Seeding state in Given steps

`Dado` steps must create domain state using the **domain Repository interface**
or a setup Handler — never Eloquent directly. This keeps the test honest:
if the repository is broken, the Given will fail visibly.

```php
// ✅ Correct — seed via domain Repository
/** @Given que existe una solicitud en estado borrador */
public function queExisteUnaSolicitudEnEstadoBorrador(): void
{
    $repo     = $this->make(SolicitudPrestamoRepository::class);
    $solicitud = SolicitudPrestamo::registrar(
        id:            $repo->nextIdentity(),
        investigadorId: 'inv-001',
        // ... required fields
    );
    $repo->guardar($solicitud);
    $this->solicitudExistente = $solicitud;
}

// ✅ Also correct — seed via a setup Handler (e.g. RegistrarSolicitudHandler)
/** @Given que el investigador ha ingresado información en una solicitud */
public function queElInvestigadorHaIngresadoInformacion(): void
{
    $this->ultimaRespuesta = ($this->registrarHandler)(
        new RegistrarSolicitudInput(
            investigadorId:       'inv-001',
            tituloEstudio:        'Estudio de lepidópteros',
            institucionAdscripcion: 'EPN',
            lineaInvestigacion:   'Entomología',
            propositoPrestamo:    'Investigación taxonómica',
            duracionPropuestaMeses: 6,
        )
    );
}

// ❌ Incorrect — Eloquent in Given
public function queExisteUnaSolicitudEnEstadoBorrador(): void
{
    SolicitudPrestamoModel::create(['estado' => 'borrador', ...]); // Never
}
```

### 7.4 Shared state between steps

State between `Dado`, `Cuando`, and `Entonces` is stored in **instance properties**.
Never in static variables or the Laravel container.

```php
private ?SomeOutput $ultimaRespuesta      = null;
private ?\Throwable  $excepcionCapturada  = null;
private ?SolicitudPrestamo $solicitudExistente = null;
```

Behat instantiates Contexts **once per scenario**, so instance properties reset
automatically between scenarios. Static properties do not — never use them for
scenario state.

---

## 8. Database cleanup between scenarios

Each scenario starts from a clean DB. Cleanup runs in `BaseContext`:

```php
/** @BeforeScenario */
public function resetDatabase(): void
{
    static::$app->make(ConsoleKernel::class)->call('migrate:fresh');
}
```

**Rules:**

- `migrate:fresh` in `@BeforeScenario`, not in `@BeforeSuite`.
- Do not manually truncate tables — it lets pending migrations go undetected.
- If a scenario needs base catalog data (roles, taxonomic types), seed it
  in the scenario's own `Dado` step, not in a global suite seeder.

---

## 9. File and class naming

| Artifact              | Convention    | Example                                      |
|-----------------------|---------------|----------------------------------------------|
| Feature file          | `snake_case.feature` | `envio_solicitud_prestamo.feature`      |
| Capability folder (Features) | `PascalCase` | `TramitacionSolicitudesInvestigador/`  |
| Capability folder (Contexts) | `PascalCase` (mirrors Features) | `TramitacionSolicitudesInvestigador/` |
| Context class         | `<FeatureName>Context.php` | `EnvioSolicitudPrestamoContext.php`  |
| Context namespace     | `Modules\<Module>\Tests\Behat\Contexts\<Capability>` | `Modules\LoanReceptionManagement\Tests\Behat\Contexts\TramitacionSolicitudesInvestigador` |

---

## 10. Checklist before marking a scenario as implemented

- [ ] The `.feature` file has `# language: es` on the first line.
- [ ] The actor in the scenario belongs to the valid actors table (section 4.1).
- [ ] There is exactly one `Cuando` per `Escenario`.
- [ ] The Context is registered in `behat.php` before any step was written.
- [ ] The Context extends `BaseContext` of the same module.
- [ ] `Dado` steps seed state via Repository or Handler — never Eloquent or Facades.
- [ ] `Cuando` steps wrap the Handler call in try/catch.
- [ ] `Entonces` steps assert on `$this->ultimaRespuesta` or `$this->excepcionCapturada`.
- [ ] State between steps is in instance properties, not static variables.
- [ ] Pest/Unit tests for the domain rules exist before the Behat Context was written (Step 1).
- [ ] The scenario passes: `php artisan behat --suite=<Module>`.
