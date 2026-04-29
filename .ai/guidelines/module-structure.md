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
        "Modules\\<Module>\\": "app/"
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
        "Modules\\LoanReceptionManagement\\Infrastructure\\Providers\\LoanReceptionManagementServiceProvider"
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
        "Modules\\<Module>\\Tests\\": "tests/"
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
