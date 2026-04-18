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
        └── <CapabilityName>Context.php
```

**Structure rules:**

- One `.feature` = one cohesive functionality, not an entire module.
- Features are grouped in subfolders by **capability** (e.g. `ResearcherLoanRequestProcessing`), not by actor or module.
- Contexts are grouped in subfolders that **mirror** the Features folders.
- `BaseContext.php` exists in all modules and contains **only** the Laravel bootstrap. It has no steps.

**Never:**

- ❌ A single Context for the entire module (legacy `InventoryCollectionManagementContext` pattern — do not replicate).
- ❌ Features at the project root.
- ❌ A Context that mixes steps from two different capabilities.

---

## 2. Configuration — behat.php

The master configuration lives in `behat.php` at the root. Each module has its own suite.

**Rules:**

- One suite per nwidart module. Not a global suite.
- Each suite lists **all** its Contexts explicitly. Behat does not autodiscover.
- When a new Context is added, it is registered in the corresponding suite in `behat.php` before implementing any step.

```php
// Suite structure example (do not copy literally — read the current behat.php)
'LoanReceptionManagement' => [
    'paths'    => ['Modules/LoanReceptionManagement/tests/Behat/Features'],
    'contexts' => [
        BaseContext::class,
        LoanRequestSubmissionContext::class,
        RequestTrackingContext::class,
        LoanRequestResolutionContext::class,
    ],
],
```

---

## 3. Language and Gherkin keywords

- **All** `.feature` files are written in **Spanish**.
- The first line of each feature is `# language: es` (with a space after `#`).
- Use Spanish keywords: `Característica`, `Escenario`, `Esquema del escenario`, `Dado`, `Cuando`, `Entonces`, `Y`, `Pero`.
- The **business language** (domain nouns and verbs) comes from the Hub Digital glossary — see `ubiquitous-language.md`.

**Never:**

- ❌ Mix English and Spanish keywords in the same file.
- ❌ Use `Feature:` or `Scenario:` in a file with `# language: es`.

---

## 4. Writing scenarios — Gherkin rules

### 4.1 Actor in the scenario title

Each `Escenario` must name the actor performing the action:

```gherkin
Escenario: El investigador envía una solicitud de préstamo válida
Escenario: El curador aprueba una solicitud pendiente
Escenario: El sistema registra la ubicación de una caja nueva
```

Valid actors per module:

| Module | Actors |
|--------|---------|
| LoanReceptionManagement | The researcher, The curator |
| InventoryCollectionManagement | The curator, The system |
| PublicCatalog | The visitor, The researcher |

**Never** use "the user" as an actor — it is ambiguous and does not belong to the ubiquitous language.

### 4.2 Writing steps

- `Dado` → system state precondition (what exists in the DB before acting).
- `Cuando` → action performed by the actor (one single action per `Cuando`).
- `Entonces` → expected observable outcome (no conditional logic).
- Do not use `Y` or `Pero` as the first step of a block.

```gherkin
# ✅ Correct
Dado que existe una solicitud de préstamo en estado "pendiente"
Cuando el curador aprueba la solicitud
Entonces la solicitud queda en estado "aprobada"
Y el investigador recibe una notificación de aprobación

# ❌ Incorrect — action and verification mixed in Cuando
Cuando el curador aprueba la solicitud y verifica el estado
```

### 4.3 One Cuando per scenario

One `Cuando` per `Escenario`. If two actions are needed, they are two separate scenarios.

### 4.4 Data in tables, not in titles

```gherkin
# ✅ Correct
Esquema del escenario: El investigador no puede enviar solicitud con datos inválidos
  Cuando el investigador envía la solicitud con los datos:
    | campo            | valor |
    | institucion      |       |
  Entonces el sistema rechaza la solicitud con el mensaje "<mensaje>"
  Ejemplos:
    | mensaje                          |
    | La institución no puede estar vacía |

# ❌ Incorrect — data in the scenario title
Escenario: El investigador envía solicitud con institución vacía y recibe error
```

---

## 5. Contexts — responsibilities and constraints

### 5.1 BaseContext — bootstrap only

`BaseContext.php` in each module does **one thing only**: boot Laravel.

```php
// What BaseContext CAN have:
// - @BeforeSuite with app bootstrap
// - @BeforeScenario with DB migration/cleanup
// - Shared properties: $app, $kernel

// What BaseContext MUST NEVER have:
// - @Given / @When / @Then methods
// - Business logic
// - Calls to Use Cases or repositories
```

### 5.2 Capability-specific Contexts — one capability, one responsibility

Each capability Context:

- Extends `BaseContext`.
- Contains **only** the steps for its capability.
- Accesses the application layer **by injecting the Use Case Handler**, never calling Facades or Eloquent directly.

```php
// ✅ Correct — the Context talks to the Application layer
private LoanRequestSubmissionHandler $handler;

public function __construct()
{
    parent::__construct();
    $this->handler = $this->app->make(LoanRequestSubmissionHandler::class);
}

/** @When the researcher submits the request with: */
public function theResearcherSubmitsTheRequest(TableNode $table): void
{
    $data = $table->getRowsHash();
    $this->lastResponse = ($this->handler)(new LoanRequestSubmissionInput(
        institution: $data['institution'] ?? '',
        // ...
    ));
}

// ❌ Incorrect — direct Eloquent access from the Context
public function theResearcherSubmitsTheRequest(): void
{
    LoanRequest::create([...]); // Never
}

// ❌ Incorrect — Facade from the Context
public function theResearcherSubmitsTheRequest(): void
{
    DB::table('requests')->insert([...]); // Never
}
```

### 5.3 Shared state between steps

State between `Given`, `When`, and `Then` within a scenario is stored in **Context properties** (`$this->lastResponse`, `$this->capturedException`, etc.). Never in static variables or the Laravel container.

```php
private ?LoanRequestSubmissionOutput $lastResponse = null;
private ?\Throwable $capturedException = null;
```

---

## 6. Database cleanup between scenarios

Each scenario starts from a clean DB. Cleanup runs in `BaseContext` with `@BeforeScenario`:

```php
/** @BeforeScenario */
public function cleanDatabase(): void
{
    Artisan::call('migrate:fresh');
}
```

**Rules:**

- `migrate:fresh` in `@BeforeScenario`, not in `@BeforeSuite`.
- Do not manually truncate tables — it lets pending migrations go undetected.
- If the scenario needs base data (catalogs, roles), seed them in the scenario's own `Given`, not in a global suite seeder.

---

## 7. File and class naming

| Artifact | Convention | Example |
|-----------|-----------|---------|
| Feature file | `snake_case.feature` | `loan_request_submission.feature` |
| Capability folder (Features) | `PascalCase` | `ResearcherLoanRequestProcessing/` |
| Capability folder (Contexts) | `PascalCase` (mirrors Features) | `ResearcherLoanRequestProcessing/` |
| Context class | `<Capability>Context.php` | `LoanRequestSubmissionContext.php` |
| Context namespace | `Modules\<Module>\Tests\Behat\Contexts\<Capability>` | `Modules\LoanReceptionManagement\Tests\Behat\Contexts\ResearcherLoanRequestProcessing` |

---

## 8. What Behat does NOT do in this project

Behat covers **Application layer behavior** (Use Cases). It is not the place for:

- ❌ Unit tests for entities or Value Objects → that is Pest/Unit.
- ❌ Repository or infrastructure tests → that is Pest/Integration.
- ❌ UI or HTTP tests → there are no controllers in scenarios; the Context calls handlers directly.
- ❌ Tests for the PublicCatalog chatbot → that AI SDK integration is covered by dedicated Pest tests.

---

## 9. Checklist before marking a scenario as implemented

- [ ] The `.feature` file has `# language: es` on the first line.
- [ ] The scenario actor exists in the valid actors table (section 4.1).
- [ ] There is exactly one `When` per `Scenario`.
- [ ] The corresponding Context is listed in `behat.php`.
- [ ] The Context extends `BaseContext` and has no bootstrap logic of its own.
- [ ] Steps access the Use Case Handler, not Eloquent or Facades.
- [ ] State between steps is stored in Context properties.
- [ ] The scenario passes with `php artisan behat --suite=<Module>`.
