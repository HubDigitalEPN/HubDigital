# module-structure.md
# Estructura de Módulos — Hub Digital

> **Propósito:** reglas no negociables sobre cómo se organiza cada módulo nwidart
> en Hub Digital. Claude Code debe respetar esta estructura al crear archivos nuevos.
> No inventar carpetas fuera de este esquema.

---

## 1. Raíz PSR-4 y namespace

El `composer.json` de cada módulo mapea `app/` como raíz del namespace:

```json
"autoload": {
    "psr-4": {
        "Modules\\<Modulo>\\": "app/"
    }
}
```

**Consecuencia directa:** toda clase del módulo vive dentro de `app/`.
`Domain/`, `Application/` e `Infrastructure/` son subcarpetas de `app/`, no carpetas
hermanas al mismo nivel.

| Namespace | Ruta física |
|-----------|-------------|
| `Modules\GestionPrestamosRecepciones\Domain\...` | `Modules/GestionPrestamosRecepciones/app/Domain/...` |
| `Modules\InventarioGestionColeccion\Application\...` | `Modules/InventarioGestionColeccion/app/Application/...` |
| `Modules\CatalogoPublico\Infrastructure\...` | `Modules/CatalogoPublico/app/Infrastructure/...` |
| `Modules\<Modulo>\Tests\...` | `Modules/<Modulo>/tests/...` |

---

## 2. Estructura completa de un módulo

```
Modules/<Modulo>/
├── app/                                      # Raíz PSR-4 → Modules\<Modulo>\
│   ├── Domain/
│   │   ├── Entities/
│   │   ├── ValueObjects/
│   │   ├── Services/
│   │   ├── Events/
│   │   ├── Repositories/                     # Solo interfaces
│   │   └── Exceptions/
│   │
│   ├── Application/
│   │   ├── UseCases/
│   │   │   └── <NombreUseCase>/
│   │   │       ├── <NombreUseCase>Handler.php
│   │   │       ├── <NombreUseCase>Input.php
│   │   │       └── <NombreUseCase>Output.php
│   │   └── Ports/
│   │
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   │   └── Eloquent/
│   │   │       ├── Models/
│   │   │       └── Repositories/
│   │   ├── Providers/
│   │   │   ├── <Modulo>ServiceProvider.php   # Provider principal
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
│   ├── migrations/                           # Migraciones del módulo
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

## 3. ServiceProvider — el único lugar de bindings

El ServiceProvider principal de cada módulo es
`app/Infrastructure/Providers/<Modulo>ServiceProvider.php`.

Es el **único lugar** donde se registran los bindings puerto → adaptador.

### Qué debe sobrescribir `register()`

```php
<?php
namespace Modules\GestionPrestamosRecepciones\Infrastructure\Providers;

use Nwidart\Modules\Traits\PathNamespace;
use Nwidart\Modules\Laravel\ModuleServiceProvider;

// Importar interfaces (Domain/Application) e implementaciones (Infrastructure)
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Repositories\EloquentSolicitudPrestamoRepository;
use Modules\GestionPrestamosRecepciones\Application\Ports\NotificacionPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\MailNotificacionAdapter;

class GestionPrestamosRecepcionesServiceProvider extends ModuleServiceProvider
{
    use PathNamespace;

    protected string $name = 'GestionPrestamosRecepciones';
    protected string $nameLower = 'gestionprestamosrecepciones';
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    // Bindings declarados como array (sin lógica) → preferir sobre register()
    public array $bindings = [
        SolicitudPrestamoRepository::class => EloquentSolicitudPrestamoRepository::class,
        NotificacionPort::class            => MailNotificacionAdapter::class,
    ];

    public function register(): void
    {
        parent::register();
        // Solo aquí si el binding necesita lógica (ej. pasar config al constructor)
    }

    public function boot(): void
    {
        parent::boot();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }
}
```

**Reglas:**

- `$bindings` para bindings simples interfaz → clase concreta.
- `register()` solo si el binding necesita pasar parámetros (ej. `$app->when(...)->needs(...)->give(...)`).
- `boot()` siempre llama `parent::boot()` y carga migraciones con `loadMigrationsFrom`.
- **Nunca** registrar bindings de este módulo en `AppServiceProvider` ni en `bootstrap/providers.php`.

---

## 4. Migraciones — dónde van y cómo se cargan

Las migraciones de dominio van **dentro del módulo**, no en `database/migrations/` raíz.

| Tipo de migración | Ubicación |
|-------------------|-----------|
| Tablas del dominio del módulo | `Modules/<Modulo>/database/migrations/` |
| Tablas de framework (users, cache, jobs) | `database/migrations/` raíz |

### Cómo se cargan

En el `boot()` del ServiceProvider principal:

```php
public function boot(): void
{
    parent::boot();
    $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
}
```

`module_path()` es el helper de nwidart que resuelve la ruta absoluta al módulo.
No usar `__DIR__` ni rutas relativas.

---

## 5. Descubrimiento automático — module.json

Cada módulo tiene un `module.json` que declara su ServiceProvider principal.
nwidart lo lee automáticamente para cargar el módulo sin registro manual
en `bootstrap/providers.php`.

```json
{
    "name": "GestionPrestamosRecepciones",
    "alias": "gestionprestamosrecepciones",
    "providers": [
        "Modules\\GestionPrestamosRecepciones\\Infrastructure\\Providers\\GestionPrestamosRecepcionesServiceProvider"
    ],
    "files": []
}
```

**Reglas:**

- El provider declarado en `module.json` es **únicamente** el ServiceProvider principal.
- `EventServiceProvider` y `RouteServiceProvider` se cargan desde el array `$providers`
  del ServiceProvider principal, no desde `module.json`.
- No agregar providers a `bootstrap/providers.php` — el auto-discovery de nwidart
  ya los carga vía `LaravelModulesServiceProvider`.

---

## 6. Rutas — convención por módulo

Las rutas web y api de cada módulo viven en `routes/web.php` y `routes/api.php`
del módulo. El `RouteServiceProvider` ya las carga con los middlewares correctos.

**Convención de nombres de ruta:**

```php
// web.php — rutas Livewire
Route::middleware(['auth', 'verified'])
    ->prefix('prestamos')
    ->name('prestamos.')
    ->group(function () {
        // rutas del módulo
    });

// api.php — rutas API (si aplica)
Route::middleware(['auth:sanctum'])
    ->prefix('prestamos')
    ->name('api.prestamos.')
    ->group(function () {
        // endpoints API del módulo
    });
```

**Nunca** definir rutas de un módulo en `routes/web.php` o `routes/api.php` raíz
del proyecto.

---

## 7. Tests — namespace y ubicación

```json
"autoload-dev": {
    "psr-4": {
        "Modules\\<Modulo>\\Tests\\": "tests/"
    }
}
```

| Tipo | Ruta | Namespace |
|------|------|-----------|
| Unit | `tests/Unit/` | `Modules\<Modulo>\Tests\Unit\` |
| Integration | `tests/Integration/` | `Modules\<Modulo>\Tests\Integration\` |
| Behat Contexts | `tests/Behat/Contexts/` | `Modules\<Modulo>\Tests\Behat\Contexts\` |
| Behat Features | `tests/Behat/Features/` | — (archivos `.feature`, sin namespace PHP) |

---

## 8. Checklist al crear un archivo nuevo en un módulo

- [ ] La ruta física es `Modules/<Modulo>/app/<Capa>/...`
- [ ] El namespace declara `Modules\<Modulo>\<Capa>\...`
- [ ] Si es una interfaz de Repository o Port → está en `Domain/` o `Application/`
- [ ] Si es una implementación (Eloquent, Mail, Gateway) → está en `Infrastructure/`
- [ ] Si es un binding nuevo → está declarado en `$bindings` del ServiceProvider
- [ ] Si es una migración → está en `Modules/<Modulo>/database/migrations/`
- [ ] Si es una ruta → está en `Modules/<Modulo>/routes/web.php` o `api.php`
- [ ] Ninguna clase del módulo importa desde `Modules\<OtroModulo>\Domain\`
