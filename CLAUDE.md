<laravel-boost-guidelines>
=== .ai/behat-conventions rules ===

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

=== .ai/clean-architecture rules ===

# clean-architecture.md

# Clean Architecture Conventions — Hub Digital

> **Purpose of this file:** non-negotiable rules that Claude Code must follow
> at ALL times when creating or modifying Hub Digital code. This is not a tutorial.
> For full examples, templates, and explanations, activate the
> `laravel-clean-architecture` skill and its references in `references/`.

---

## 1. The three modules and their bounded contexts

Hub Digital uses **nwidart/laravel-modules**. Each module is an independent bounded context
with its own internal layers.

| Module (directory) | Bounded Context | Root namespace |
|---------------------|-----------------|----------------|
| `Modules/LoanReceptionManagement/` | Specimen loan and reception management | `Modules\LoanReceptionManagement` |
| `Modules/InventoryCollectionManagement/` | Collection inventory, locations, and traceability | `Modules\InventoryCollectionManagement` |
| `Modules/PublicCatalog/` | Public catalog with AI chatbot | `Modules\PublicCatalog` |

**Never:**
- ❌ A module accesses another module's `Domain/` directly.
- ❌ Business code is created outside `Modules/` (except `Shared/`).
- ❌ A fourth module is added without first defining its bounded context.

---

## 2. Internal structure of each module

Each module replicates this structure. Claude must not invent new folders
outside this schema without explicit team approval.

```
Modules/<Module>/
├── src/
│   ├── Domain/
│   │   ├── Entities/
│   │   ├── ValueObjects/
│   │   ├── Services/          # Domain Services (stateless, no framework)

│   │   ├── Events/            # Domain Events

│   │   ├── Repositories/      # Interfaces ONLY — never implementations

│   │   └── Exceptions/
│   │
│   ├── Application/
│   │   ├── UseCases/
│   │   │   └── <UseCaseName>/
│   │   │       ├── <UseCaseName>Handler.php
│   │   │       ├── <UseCaseName>Input.php
│   │   │       └── <UseCaseName>Output.php
│   │   └── Ports/             # Interfaces for external services

│   │
│   └── Infrastructure/
│       ├── Persistence/
│       │   └── Eloquent/
│       │       ├── Models/    # Eloquent models — ONLY here

│       │       └── Repositories/
│       ├── Gateways/
│       └── Notifications/
│
├── Http/
│   ├── Controllers/           # Livewire components and API controllers

│   ├── Requests/              # Form Requests → produce Input DTOs

│   └── Resources/             # Use Case Output → HTTP response

│
├── Providers/
│   └── <Module>ServiceProvider.php   # Registers port → adapter bindings

│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Behat/
│
└── composer.json
```

---

## 3. Layer rules — what each class can and cannot import

### Domain — innermost layer, no framework

| ✅ Allowed | ❌ Forbidden |
|-------------|-------------|
| Pure PHP stdlib | Any `Illuminate\*` |
| `DateTimeImmutable` | `Carbon` or `CarbonImmutable` |
| Other classes within the same `Domain/` | Eloquent models |
| Classes from `Shared/Domain/` | Laravel Facades |
| | Classes from `Application/` or `Infrastructure/` |

**The test:** if Laravel is removed from the project, `Domain/` classes must
still compile without error.

### Application — pure orchestration

| ✅ Allowed | ❌ Forbidden |
|-------------|-------------|
| Classes from `Domain/` | Eloquent models directly |
| Interfaces from `Ports/` | Facades (`DB::`, `Mail::`, `Cache::`) |
| Own Input/Output DTOs | Classes from `Infrastructure/` |
| Classes from `Shared/Application/` | Business logic (that belongs in Domain) |

Handlers have no `use Illuminate\*` except in well-justified cases
(e.g. `DB::transaction()` as a transaction adapter — prefer a Port).

### Infrastructure — adapters to the outside world

| ✅ Allowed | ❌ Forbidden |
|-------------|-------------|
| Everything from `Domain/` and `Application/` | Business logic |
| `Illuminate\*`, Eloquent, Carbon | Returning Eloquent models upward |
| Classes from `Shared/Infrastructure/` | Accessing `Domain/` of another module |

### Presentation (Http/, Console/) — delivery, not logic

| ✅ Allowed | ❌ Forbidden |
|-------------|-------------|
| Call a Handler with its Input DTO | Business rules in the controller |
| Form Request → `toInput()` | `DB::` or Eloquent in controllers |
| Resource/Livewire → Output DTO | Returning Eloquent models as responses |
| | Calling more than one Handler per action |

---

## 4. Naming rules

### Use Cases

Each Use Case lives in its own folder with three files:

```
UseCases/LoanRequestSubmission/
├── LoanRequestSubmissionHandler.php   # orchestrates

├── LoanRequestSubmissionInput.php     # readonly, input data

└── LoanRequestSubmissionOutput.php    # readonly, output data

```

- The Use Case name uses a **domain verb + noun** in English:
  `LoanRequestSubmission`, `LoanRequestResolution`, `BoxLocationRegistration`.
- `Handler` is always the suffix of the orchestrating class. Not `Service`, not `Action`,
  not `Manager`.

### Repositories

- Interface in `Domain/Repositories/`: `LoanRequestRepository` (no `I` prefix).
- Implementation in `Infrastructure/Persistence/Eloquent/Repositories/`:
  `EloquentLoanRequestRepository`.
- **One interface per aggregate root.** Not `LoanRequestItemRepository`.

### Eloquent Models

- Name: `<Entity>EloquentModel` — never the same as the domain entity.
- Example: entity `LoanRequest` → model `LoanRequestEloquentModel`.
- Live **only** in `Infrastructure/Persistence/Eloquent/Models/`.

### Value Objects

- `final readonly` class, private constructor, named static factory.
- Descriptive name of the concept, not the type: `SpecimenCode`, `OriginInstitution`,
  not `StringWrapper` or `SpecimenString`.

### Ports

- `Port` suffix for external service interfaces: `NotificationPort`,
  `FileStoragePort`.
- No `I` prefix. No `Interface` suffix.

---

## 5. Domain events — mandatory pattern

Events are **registered** inside the entity. The Handler **dispatches** them
after saving. Never the other way around.

```
Entity::method()           → $this->events[] = new DomainEvent(...)
Handler::handle()          → $repo->save($entity)
                           → foreach ($entity->pullEvents() as $e) { $publisher->publish($e); }
```

**Never** call `event()`, `Event::dispatch()`, or any Facade inside
a domain entity or Value Object.

---

## 6. ServiceProvider per module

Each module has exactly **one** ServiceProvider that registers all its
port → adapter bindings.

```
Modules/<Module>/Providers/<Module>ServiceProvider.php
```

- Registered in `bootstrap/providers.php` (Laravel 13).
- Every new `Ports/` or `Repositories/` interface must have its binding here
  before the Handler can be resolved from the container.
- Do not use `app()->bind()` anywhere else in the module.

---

## 7. Livewire and Flux UI — Presentation layer

Livewire components are **Presentation**, not Application.

- A Livewire component injects the Handler in its constructor or method.
- Business logic lives in the Handler, not in the component.
- The component produces an Input DTO and consumes an Output DTO.
- Components live in `Http/Controllers/` or in `resources/views/livewire/`
  of the module, following the active nwidart convention in the project.

```php
// ✅ Correct
final class SubmitLoanRequestForm extends Component
{
    public function submit(LoanRequestSubmissionHandler $handler): void
    {
        $output = $handler->handle(new LoanRequestSubmissionInput(...));
        // update component state with $output
    }
}

// ❌ Incorrect — business logic in Livewire
final class SubmitLoanRequestForm extends Component
{
    public function submit(): void
    {
        if (LoanRequest::where('researcher_id', ...)->count() >= 3) { ... }
        LoanRequest::create([...]);
    }
}
```

---

## 8. PublicCatalog — additional rules for the AI SDK

The `PublicCatalog` module is the only one that uses the Laravel AI SDK for the chatbot.

- The AI SDK integration lives **only** in `Infrastructure/` of that module.
- The chatbot is exposed as a Livewire component that consumes the stream via
  server-sent events.
- The AI SDK has no presence in `LoanReceptionManagement` or in
  `InventoryCollectionManagement`.
- If the chatbot needs to query collection data, it does so through
  a Port defined in `PublicCatalog/Application/Ports/`, implemented by
  an adapter that calls the `InventoryCollectionManagement` Repository — never
  accessing its `Domain/` directly.

---

## 9. Checklist before marking a class as ready

- [ ] Classes in `Domain/` have no `use Illuminate\*`
- [ ] Classes in `Domain/` do not use `Carbon` — only `DateTimeImmutable`
- [ ] Eloquent Models live in `Infrastructure/Persistence/Eloquent/Models/`
- [ ] The Repository interface is in `Domain/Repositories/`, the implementation in `Infrastructure/`
- [ ] The Handler returns an Output DTO, not an Eloquent Model or domain entity
- [ ] The new Port has its binding in the module's ServiceProvider
- [ ] Domain events are registered in the entity and dispatched in the Handler
- [ ] The controller or Livewire component has ≤ 10 lines per action
- [ ] No module imports classes from another module's `Domain/`
- [ ] The Use Case has all three files: Handler, Input, Output

=== .ai/frontend-design rules ===

# frontend-design.md

# Frontend Design Conventions — Hub Digital

> **Purpose of this file:** non-negotiable rules Claude Code must follow at ALL times
> when creating or modifying any view, Blade component, Livewire component, or layout.
> This is not a tutorial. For component usage examples and patterns, activate the
> `fluxui-development`, `livewire-development`, or `tailwindcss-development` skills.

---

## 1. Color System

Hub Digital's palette is derived from EPN's institutional identity (Blue/Red), adapted to reduce visual fatigue in daily-use software. All tokens are defined in `resources/css/app.css` under `@theme`.

### 1.1 Primary Colors

| Token | Hex | Usage |
|-------|-----|-------|
| `--color-blue-navy` | `#1B365D` | Navigation bars, main headings |
| `--color-bio-green` | `#2E7D32` | Primary action buttons: Save, Confirm, Scan |
| `--color-science-blue` | `#1976D2` | Links, active states, selections |

### 1.2 Semantic Colors (System Feedback — Critical for IoT)

| Token | Hex | Usage |
|-------|-----|-------|
| `--color-success` | `#4CAF50` | "Specimen registered", "Sensor Connected", "Request logged" |
| `--color-warning` | `#FF9800` | "Loan about to expire" |
| `--color-error` | `#D32F2F` | "Box in wrong location", "Connection error" |
| `--color-info` | `#0288D1` | Contextual help |

### 1.3 Neutral Colors

| Token | Hex | Usage |
|-------|-----|-------|
| `--color-bg-main` | `#F5F7FA` | Page background (avoids pure-white glare) |
| `--color-surface` | `#FFFFFF` | Cards, panels |
| `--color-text-primary` | `#212121` | Main readable text |
| `--color-text-secondary` | `#757575` | Labels, metadata |
| `--color-border` | `#E0E0E0` | Dividers, input borders |

### 1.4 Rules

**Never:**

- ❌ Use raw hex values directly in templates (`class="bg-[#1B365D]"`, `style="color: #2E7D32"`)
- ❌ Use a semantic color for a purpose other than its defined meaning (e.g., `--color-error` for decorative red)
- ❌ Use `bg-white` for page backgrounds — use `bg-bg-main` to avoid pure-white glare

**Always:**

- ✅ Add new color tokens to `resources/css/app.css` under `@theme` before using them
- ✅ Use the `dark:` variant for every color class if the surrounding component supports dark mode

---

## 2. Typography

All sizes use **rem units**. The scale follows a "Scientific Journal" aesthetic — Roboto Slab for academic headings, Inter for all interactive UI elements.

### 2.1 Type Scale

| Level | Size | Weight | Font | Color |
|-------|------|--------|------|-------|
| H1 — Page title | `text-2xl` / 1.5rem | Bold | Roboto Slab | `text-text-primary` |
| H2 — Sections | `text-xl` / 1.25rem | SemiBold | Roboto Slab | `text-text-primary` |
| H3 — Card subtitles | `text-base` / 1rem | Medium | Inter | `text-text-primary` |
| Body — Standard text | `text-sm` / 0.875rem | Regular | Inter | `text-text-primary` |
| Caption — Labels/Metadata | `text-xs` / 0.75rem | Regular | Inter | `text-text-secondary` |

### 2.2 Rules

**Never:**

- ❌ Use px units for font sizes — always use rem-based Tailwind classes (`text-sm`, `text-xl`, etc.)
- ❌ Use Roboto Slab for anything other than H1, H2, or scientific species names
- ❌ Use Inter for page-level or section-level headings

**Always:**

- ✅ Scientific species names use Roboto Slab in **italic** (`font-serif italic`)
- ✅ Numbers in data tables use Inter — it has excellent numeric rendering at small sizes

---

## 3. Iconography & Visual Elements

### 3.1 Icons

- **Source:** Heroicons (included via Flux UI) — use `<flux:icon name="..." />` exclusively
- **Variant:** **Outline only** — stroke weight 1.5–2px. Clean and technical.
- If an icon is not available in Heroicons, import from Lucide via `php artisan flux:icon <name>`

**Never:**

- ❌ Use filled/solid icon variants — they conflict with the outline visual language
- ❌ Invent or guess icon names — look them up at heroicons.com before use

### 3.2 Borders

- **Border radius:** `rounded-lg` (8px) on cards, inputs, buttons, modals
- Avoid fully square corners (`rounded-none`) or pill shapes (`rounded-full`) on non-circular elements

### 3.3 Elevation (Shadows)

- Elevation is used only to lift cards off the background
- Use `shadow-sm` → `box-shadow: 0 2px 4px rgba(0,0,0,0.1)`
- Never apply shadows to inline elements, text, or navigation items

---

## 4. Component Architecture

Follow this decision tree **in order** before writing any template code:

```
1. Check resources/views/components/ for an existing reusable Blade component
        ↓ not found
2. Check Flux UI free edition components:
   avatar, badge, brand, breadcrumbs, button, callout, checkbox, dropdown,
   field, heading, icon, input, modal, navbar, otp-input, profile, radio,
   select, separator, skeleton, switch, text, textarea, tooltip
        ↓ not found
3. Create a new Blade component (@props + $attributes->merge())
        ↓ requires server-side reactivity
4. Create a Livewire component (call a Use Case Handler — see clean-architecture.md)
```

### 4.1 Naming & Location

**Default rule:** every view and component belongs inside its module. Only elements used across two or more modules (e.g. the navbar, app shell layouts, global error pages) belong in the root `resources/views/`.

| Type | Naming | Location |
|------|--------|----------|
| **Shared** Blade component (cross-module) | `kebab-case.blade.php` | `resources/views/components/` |
| **Shared** page layout (cross-module) | `kebab-case.blade.php` | `resources/views/layouts/` |
| Module page / Livewire template | `kebab-case.blade.php` | `Modules/<Module>/resources/views/` |
| Module Blade component | `kebab-case.blade.php` | `Modules/<Module>/resources/views/components/` |
| Livewire class | `PascalCase.php` | `Modules/<Module>/app/Presentation/Http/Controllers/` |

**Never:**

- ❌ Place a module-specific view or component under the root `resources/views/` — it must live in `Modules/<Module>/resources/views/`
- ❌ Move a component to root `resources/views/components/` just because it is reused within the same module; it only moves there when a second, different module needs it

### 4.2 Blade Component Rules

```blade
{{-- ✅ Correct — @props declared, attributes forwarded --}}
@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg bg-surface shadow-sm p-4']) }}>
    <h3 class="text-base font-medium text-text-primary">{{ $title }}</h3>
    @if($description)
        <p class="text-xs text-text-secondary">{{ $description }}</p>
    @endif
</div>

{{-- ❌ Incorrect — no @props, hardcoded styles, no attribute forwarding --}}
<div style="background: #fff; border-radius: 8px; padding: 16px;">
    <h3>{{ $title }}</h3>
</div>
```

**Rules:**

- ✅ Every new Blade component declares `@props([])` at the top
- ✅ Always use `{{ $attributes->merge(['class' => '...']) }}` to forward extra attributes to the root element
- ✅ Use `wire:navigate` on all internal `<a>` links (SPA navigation)

**Never:**

- ❌ Use `style=""` inline attributes — all styling through Tailwind utility classes
- ❌ Duplicate a Flux UI component when one already exists for the use case

---

## 5. Usability Heuristics (Critical)

These three rules from the Design Manual apply across **all modules** without exception.

### 5.1 Visibility of System Status

Always show whether the IoT system is online or offline with a visual indicator in the page header.

```blade
{{-- ✅ Correct --}}
<span class="inline-flex items-center gap-1.5 text-xs">
    <span class="size-2 rounded-full bg-success"></span>
    Online
</span>
```

**Never** hide system connectivity state from the user.

### 5.2 Error Prevention

In taxonomy forms, use **database-backed autocomplete** for scientific names to prevent typographical errors.

```blade
{{-- ✅ Correct --}}
<flux:field>
    <flux:label>Scientific Name</flux:label>
    <flux:input wire:model.live="scientificName" list="taxa-list" />
    <datalist id="taxa-list">
        @foreach($taxa as $taxon)
            <option value="{{ $taxon->name }}">
        @endforeach
    </datalist>
    <flux:error name="scientificName" />
</flux:field>
```

**Never** use a plain free-text input for scientific names without validation against the database.

### 5.3 Recognition Over Recall

When referencing a domain entity (box, cabinet, specimen, request), always display its **human-readable name** alongside or instead of its ID.

```blade
{{-- ✅ Correct --}}
Moving <strong>Box A1</strong> to <strong>Cabinet 2 — Row 3</strong>

{{-- ❌ Incorrect --}}
Moving item #142 to location #87
```

This applies to: loan requests, specimen movements, box transfers, and any entity reference in status messages or confirmations.

---

## 6. Responsive Design & Accessibility

Hub Digital is used in laboratories on tablets via QR code scanning. Design for this context first.

### 6.1 Mobile-First Breakpoints

- Always start with the mobile layout; add `md:` and `lg:` variants to expand for larger screens
- Minimum touch target size: **44×44px** for any button, link, or interactive element used with a barcode/QR scanner
- Use `gap-*` utilities for spacing between siblings, not margins

### 6.2 Contrast & Readability

- High contrast is required — lab lighting is variable
- **Never** use `text-text-secondary` (`#757575`) on colored backgrounds — it will fail WCAG AA
- Use `text-text-primary` (`#212121`) for any text that must be readable under harsh lighting

### 6.3 Dark Mode

- If surrounding pages support `dark:` variants, new components must include `dark:` variants too
- Check existing sibling components before assuming dark mode is not in use

---

## 7. Checklist Before Marking a Component as Done

- [ ] No hardcoded hex colors — only Tailwind utility classes referencing `@theme` tokens
- [ ] No `style=""` inline attributes anywhere
- [ ] Typography: H1/H2 use Roboto Slab; H3/Body/Caption use Inter
- [ ] Font sizes use rem-based Tailwind classes (`text-sm`, `text-xl`), never `text-[14px]`
- [ ] Icons are Heroicons **outline** variant only via `<flux:icon>`
- [ ] Cards and inputs use `rounded-lg` (8px border-radius)
- [ ] Elevation: `shadow-sm` only on cards — not on inline elements
- [ ] Checked `Modules/<Module>/resources/views/components/`, then `resources/views/components/`, then Flux UI before creating a new component
- [ ] Module-specific views/components are inside `Modules/<Module>/resources/views/` — not under root `resources/views/`
- [ ] A component was only promoted to root `resources/views/components/` because a second, different module needs it
- [ ] New Blade components declare `@props([])` at the top
- [ ] `$attributes->merge()` used on the root element of new Blade components
- [ ] `wire:navigate` on all internal `<a>` links
- [ ] Layout is mobile-first with responsive breakpoints
- [ ] Touch targets are ≥ 44×44px
- [ ] IoT system status is visible in header (if page includes IoT data)
- [ ] Entity names shown alongside codes/IDs — not raw IDs alone

=== .ai/module-structure rules ===

# module-structure.md

# Module Structure — Hub Digital

> **Purpose:** non-negotiable rules about how each nwidart module is organized
> in Hub Digital. Claude Code must follow this structure when creating new files.
> Do not invent folders outside this schema.

---

## 1. PSR-4 root and namespace

Each module's `composer.json` maps `app/` as the namespace root:

```json
"autoload": {
    "psr-4": {
        "Modules\<Module>\": "app/"
    }
}
```

**Direct consequence:** every class in the module lives inside `app/`.
`Domain/`, `Application/`, and `Infrastructure/` are subdirectories of `app/`, not
sibling folders at the same level.

| Namespace | Physical path |
|-----------|-------------|
| `Modules\LoanReceptionManagement\Domain\...` | `Modules/LoanReceptionManagement/app/Domain/...` |
| `Modules\InventoryCollectionManagement\Application\...` | `Modules/InventoryCollectionManagement/app/Application/...` |
| `Modules\PublicCatalog\Infrastructure\...` | `Modules/PublicCatalog/app/Infrastructure/...` |
| `Modules\<Module>\Tests\...` | `Modules/<Module>/tests/...` |

---

## 2. Full module structure

```
Modules/<Module>/
├── app/                                      # PSR-4 root → Modules\<Module>\

│   ├── Domain/
│   │   ├── Entities/
│   │   ├── ValueObjects/
│   │   ├── Services/
│   │   ├── Events/
│   │   ├── Repositories/                     # Interfaces only

│   │   └── Exceptions/
│   │
│   ├── Application/
│   │   ├── UseCases/
│   │   │   └── <UseCaseName>/
│   │   │       ├── <UseCaseName>Handler.php
│   │   │       ├── <UseCaseName>Input.php
│   │   │       └── <UseCaseName>Output.php
│   │   └── Ports/
│   │
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   │   └── Eloquent/
│   │   │       ├── Models/
│   │   │       └── Repositories/
│   │   ├── Providers/
│   │   │   ├── <Module>ServiceProvider.php   # Main provider

│   │   │   ├── EventServiceProvider.php
│   │   │   └── RouteServiceProvider.php
│   │   ├── Gateways/
│   │   └── Notifications/
│   │
│   └── Presentation/
│       └── Http/
│           ├── Controllers/
│           ├── Requests/
│           └── Resources/
│
├── database/
│   ├── migrations/                           # Module migrations

│   ├── factories/
│   └── seeders/
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Behat/
│       ├── Features/
│       └── Contexts/
│
├── composer.json
└── module.json
```

---

## 3. ServiceProvider — the only place for bindings

The main ServiceProvider for each module is
`app/Infrastructure/Providers/<Module>ServiceProvider.php`.

It is the **only place** where port → adapter bindings are registered.

### What `register()` must override

```php
<?php
namespace Modules\LoanReceptionManagement\Infrastructure\Providers;

use Nwidart\Modules\Traits\PathNamespace;
use Nwidart\Modules\Laravel\ModuleServiceProvider;

// Import interfaces (Domain/Application) and implementations (Infrastructure)
use Modules\LoanReceptionManagement\Domain\Repositories\LoanRequestRepository;
use Modules\LoanReceptionManagement\Infrastructure\Persistence\Eloquent\Repositories\EloquentLoanRequestRepository;
use Modules\LoanReceptionManagement\Application\Ports\NotificationPort;
use Modules\LoanReceptionManagement\Infrastructure\Notifications\MailNotificationAdapter;

class LoanReceptionManagementServiceProvider extends ModuleServiceProvider
{
    use PathNamespace;

    protected string $name = 'LoanReceptionManagement';
    protected string $nameLower = 'loanreceptionmanagement';
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    // Bindings declared as array (no logic) → prefer over register()
    public array $bindings = [
        LoanRequestRepository::class => EloquentLoanRequestRepository::class,
        NotificationPort::class      => MailNotificationAdapter::class,
    ];

    public function register(): void
    {
        parent::register();
        // Only here if the binding needs logic (e.g. passing config to the constructor)
    }

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
```

**Rules:**

- `$bindings` for simple interface → concrete class bindings.
- `register()` only if the binding needs to pass parameters (e.g. `$app->when(...)->needs(...)->give(...)`).
- `boot()` always calls `parent::boot()` and loads migrations with `loadMigrationsFrom`.
- **Never** register bindings from this module in `AppServiceProvider` or in `bootstrap/providers.php`.

---

## 4. Migrations — where they go and how they are loaded

Domain migrations go **inside the module**, not in the root `database/migrations/`.

| Migration type | Location |
|----------------|-----------|
| Module domain tables | `Modules/<Module>/database/migrations/` |
| Framework tables (users, cache, jobs) | `database/migrations/` root |

### How they are loaded

In the main ServiceProvider's `boot()`:

```php
public function boot(): void
{
    parent::boot();
    $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
}
```

`module_path()` is the nwidart helper that resolves the absolute path to the module.
Do not use `__DIR__` or relative paths.

---

## 5. Auto-discovery — module.json

Each module has a `module.json` that declares its main ServiceProvider.
nwidart reads it automatically to load the module without manual registration
in `bootstrap/providers.php`.

```json
{
    "name": "LoanReceptionManagement",
    "alias": "loanreceptionmanagement",
    "providers": [
        "Modules\LoanReceptionManagement\Infrastructure\Providers\LoanReceptionManagementServiceProvider"
    ],
    "files": []
}
```

**Rules:**

- The provider declared in `module.json` is **only** the main ServiceProvider.
- `EventServiceProvider` and `RouteServiceProvider` are loaded from the `$providers`
  array of the main ServiceProvider, not from `module.json`.
- Do not add providers to `bootstrap/providers.php` — nwidart's auto-discovery
  already loads them via `LaravelModulesServiceProvider`.

---

## 6. Routes — convention per module

Web and API routes for each module live in `routes/web.php` and `routes/api.php`
of the module. The `RouteServiceProvider` already loads them with the correct middlewares.

**Route naming convention:**

```php
// web.php — Livewire routes
Route::middleware(['auth', 'verified'])
    ->prefix('loans')
    ->name('loans.')
    ->group(function () {
        // module routes
    });

// api.php — API routes (if applicable)
Route::middleware(['auth:sanctum'])
    ->prefix('loans')
    ->name('api.loans.')
    ->group(function () {
        // API endpoints
    });
```

**Never** define a module's routes in the project root `routes/web.php` or `routes/api.php`.

---

## 7. Tests — namespace and location

```json
"autoload-dev": {
    "psr-4": {
        "Modules\<Module>\Tests\": "tests/"
    }
}
```

| Type | Path | Namespace |
|------|------|-----------|
| Unit | `tests/Unit/` | `Modules\<Module>\Tests\Unit\` |
| Integration | `tests/Integration/` | `Modules\<Module>\Tests\Integration\` |
| Behat Contexts | `tests/Behat/Contexts/` | `Modules\<Module>\Tests\Behat\Contexts\` |
| Behat Features | `tests/Behat/Features/` | — (`.feature` files, no PHP namespace) |

---

## 8. Checklist when creating a new file in a module

- [ ] The physical path is `Modules/<Module>/app/<Layer>/...`
- [ ] The namespace declares `Modules\<Module>\<Layer>\...`
- [ ] If it is a Repository or Port interface → it is in `Domain/` or `Application/`
- [ ] If it is an implementation (Eloquent, Mail, Gateway) → it is in `Infrastructure/`
- [ ] If it is a new binding → it is declared in `$bindings` of the ServiceProvider
- [ ] If it is a migration → it is in `Modules/<Module>/database/migrations/`
- [ ] If it is a route → it is in `Modules/<Module>/routes/web.php` or `api.php`
- [ ] No class in the module imports from `Modules\<OtherModule>\Domain\`

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/ai (AI) - v0
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
<!-- Local-only include: see CLAUDE.local.md (not tracked) -->
See local notes: @claude.local.md
