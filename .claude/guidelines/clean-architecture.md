# clean-architecture.md
# Convenciones de Clean Architecture — Hub Digital

> **Propósito de este archivo:** reglas no negociables que Claude Code debe respetar
> en TODO momento al crear o modificar código de Hub Digital. No es un tutorial.
> Para ejemplos completos, plantillas y explicaciones, activa el skill
> `laravel-clean-architecture` y sus referencias en `references/`.

---

## 1. Los tres módulos y sus bounded contexts

Hub Digital usa **nwidart/laravel-modules**. Cada módulo es un bounded context
independiente con sus propias capas internas.

| Módulo (directorio) | Bounded Context | Namespace raíz |
|---------------------|-----------------|----------------|
| `Modules/GestionPrestamosRecepciones/` | Gestión de préstamos y recepciones de especímenes | `Modules\GestionPrestamosRecepciones` |
| `Modules/InventarioGestionColeccion/` | Inventario, ubicaciones y trazabilidad de la colección | `Modules\InventarioGestionColeccion` |
| `Modules/CatalogoPublico/` | Catálogo público con chatbot de IA | `Modules\CatalogoPublico` |

**Nunca:**
- ❌ Un módulo accede al `Domain/` de otro módulo directamente.
- ❌ Se crea código de negocio fuera de `Modules/` (salvo `Shared/`).
- ❌ Se añade un cuarto módulo sin definir primero su bounded context.

---

## 2. Estructura interna de cada módulo

Cada módulo replica esta estructura. Claude no puede inventar carpetas nuevas
fuera de este esquema sin justificación explícita del equipo.

```
Modules/<Modulo>/
├── src/
│   ├── Domain/
│   │   ├── Entities/
│   │   ├── ValueObjects/
│   │   ├── Services/          # Domain Services (sin estado, sin framework)
│   │   ├── Events/            # Domain Events
│   │   ├── Repositories/      # SOLO interfaces — nunca implementaciones
│   │   └── Exceptions/
│   │
│   ├── Application/
│   │   ├── UseCases/
│   │   │   └── <NombreUseCase>/
│   │   │       ├── <NombreUseCase>Handler.php
│   │   │       ├── <NombreUseCase>Input.php
│   │   │       └── <NombreUseCase>Output.php
│   │   └── Ports/             # Interfaces para servicios externos
│   │
│   └── Infrastructure/
│       ├── Persistence/
│       │   └── Eloquent/
│       │       ├── Models/    # Eloquent models — SOLO aquí
│       │       └── Repositories/
│       ├── Gateways/
│       └── Notifications/
│
├── Http/
│   ├── Controllers/           # Livewire components y API controllers
│   ├── Requests/              # Form Requests → producen Input DTOs
│   └── Resources/             # Output de Use Cases → respuesta HTTP
│
├── Providers/
│   └── <Modulo>ServiceProvider.php   # Registra bindings puerto → adaptador
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Behat/
│
└── composer.json
```

---

## 3. Reglas por capa — qué puede y qué no puede importar cada clase

### Domain — capa interior, sin framework

| ✅ Permitido | ❌ Prohibido |
|-------------|-------------|
| PHP stdlib puro | Cualquier `Illuminate\*` |
| `DateTimeImmutable` | `Carbon` o `CarbonImmutable` |
| Otras clases del mismo `Domain/` | Eloquent models |
| Clases de `Shared/Domain/` | Facades de Laravel |
| | Clases de `Application/` o `Infrastructure/` |

**La prueba:** si eliminas Laravel del proyecto, las clases de `Domain/` deben
seguir compilando sin error.

### Application — orquestación pura

| ✅ Permitido | ❌ Prohibido |
|-------------|-------------|
| Clases de `Domain/` | Eloquent models directamente |
| Interfaces de `Ports/` | Facades (`DB::`, `Mail::`, `Cache::`) |
| Input/Output DTOs propios | Clases de `Infrastructure/` |
| Clases de `Shared/Application/` | Lógica de negocio (eso es Domain) |

Los Handlers no tienen `use Illuminate\*` salvo excepciones muy justificadas
(ej. `DB::transaction()` como adaptador de transacción — preferir un Port).

### Infrastructure — adaptadores del mundo exterior

| ✅ Permitido | ❌ Prohibido |
|-------------|-------------|
| Todo lo de `Domain/` y `Application/` | Lógica de negocio |
| `Illuminate\*`, Eloquent, Carbon | Retornar Eloquent models hacia arriba |
| Clases de `Shared/Infrastructure/` | Acceder a `Domain/` de otro módulo |

### Presentation (Http/, Console/) — entrega, no lógica

| ✅ Permitido | ❌ Prohibido |
|-------------|-------------|
| Llamar un Handler con su Input DTO | Business rules en el controlador |
| Form Request → `toInput()` | `DB::` o Eloquent en controllers |
| Resource/Livewire → Output DTO | Retornar Eloquent models como respuesta |
| | Llamar más de un Handler por acción |

---

## 4. Reglas de nomenclatura

### Use Cases

Cada Use Case vive en su propia carpeta con tres archivos:

```
UseCases/EnvioSolicitudPrestamo/
├── EnvioSolicitudPrestamoHandler.php   # orquesta
├── EnvioSolicitudPrestamoInput.php     # readonly, datos de entrada
└── EnvioSolicitudPrestamoOutput.php    # readonly, datos de salida
```

- El nombre del Use Case usa **sustantivo + verbo de dominio** en español:
  `EnvioSolicitudPrestamo`, `ResolucionSolicitudPrestamo`, `RegistroUbicacionCaja`.
- `Handler` es siempre el sufijo de la clase que orquesta. No `Service`, no `Action`,
  no `Manager`.

### Repositorios

- Interfaz en `Domain/Repositories/`: `SolicitudPrestamoRepository` (sin prefijo `I`).
- Implementación en `Infrastructure/Persistence/Eloquent/Repositories/`:
  `EloquentSolicitudPrestamoRepository`.
- **Una interfaz por agregado raíz.** No `SolicitudPrestamoItemRepository`.

### Eloquent Models

- Nombre: `<Entidad>EloquentModel` — nunca igual que la entidad de dominio.
- Ejemplo: entidad `SolicitudPrestamo` → model `SolicitudPrestamoEloquentModel`.
- Viven **únicamente** en `Infrastructure/Persistence/Eloquent/Models/`.

### Value Objects

- Clase `final readonly`, constructor privado, named static factory.
- Nombre descriptivo del concepto, no del tipo: `CodigoEspecimen`, `InstitucionOrigen`,
  no `StringWrapper` ni `EspecimenString`.

### Ports

- Sufijo `Port` para interfaces de servicios externos: `NotificacionPort`,
  `AlmacenamientoArchivoPort`.
- Sin prefijo `I`. Sin sufijo `Interface`.

---

## 5. Eventos de dominio — patrón obligatorio

Los eventos **se registran** dentro de la entidad. Los **despacha** el Handler
después de guardar. Nunca al revés.

```
Entidad::metodo()          → $this->eventos[] = new EventoDominio(...)
Handler::handle()          → $repo->guardar($entidad)
                           → foreach ($entidad->pullEvents() as $e) { $publisher->publish($e); }
```

**Nunca** llamar `event()`, `Event::dispatch()` o cualquier Facade dentro de
una entidad o Value Object de dominio.

---

## 6. ServiceProvider por módulo

Cada módulo tiene exactamente **un** ServiceProvider que registra todos sus
bindings puerto → adaptador.

```
Modules/<Modulo>/Providers/<Modulo>ServiceProvider.php
```

- Registrado en `bootstrap/providers.php` (Laravel 13).
- Toda nueva interfaz de `Ports/` o `Repositories/` debe tener su binding aquí
  antes de que el Handler pueda resolverse del contenedor.
- No usar `app()->bind()` en ningún otro lugar del módulo.

---

## 7. Livewire y Flux UI — capa Presentation

Los componentes Livewire son **Presentation**, no Application.

- Un componente Livewire inyecta el Handler en su constructor o método.
- La lógica de negocio vive en el Handler, no en el componente.
- El componente produce un Input DTO y consume un Output DTO.
- Los componentes viven en `Http/Controllers/` o en `resources/views/livewire/`
  del módulo, según la convención de nwidart activa en el proyecto.

```php
// ✅ Correcto
final class EnviarSolicitudForm extends Component
{
    public function enviar(EnvioSolicitudPrestamoHandler $handler): void
    {
        $output = $handler->handle(new EnvioSolicitudPrestamoInput(...));
        // actualizar estado del componente con $output
    }
}

// ❌ Incorrecto — lógica de negocio en Livewire
final class EnviarSolicitudForm extends Component
{
    public function enviar(): void
    {
        if (SolicitudPrestamo::where('investigador_id', ...)->count() >= 3) { ... }
        SolicitudPrestamo::create([...]);
    }
}
```

---

## 8. CatalogoPublico — reglas adicionales para el AI SDK

El módulo `CatalogoPublico` es el único que usa el Laravel AI SDK para el chatbot.

- La integración con el AI SDK vive **únicamente** en `Infrastructure/` de ese módulo.
- El chatbot se expone como un componente Livewire que consume el stream vía
  server-sent events.
- El AI SDK no tiene presencia en `GestionPrestamosRecepciones` ni en
  `InventarioGestionColeccion`.
- Si el chatbot necesita consultar datos de la colección, lo hace a través de
  un Port definido en `CatalogoPublico/Application/Ports/`, implementado por
  un adaptador que llama al Repository de `InventarioGestionColeccion` — nunca
  accediendo directamente a su `Domain/`.

---

## 9. Checklist antes de marcar una clase como lista

- [ ] Las clases de `Domain/` no tienen ningún `use Illuminate\*`
- [ ] Las clases de `Domain/` no usan `Carbon` — solo `DateTimeImmutable`
- [ ] Los Eloquent Models viven en `Infrastructure/Persistence/Eloquent/Models/`
- [ ] La interfaz del Repository está en `Domain/Repositories/`, la implementación en `Infrastructure/`
- [ ] El Handler retorna un Output DTO, no un Eloquent Model ni una entidad de dominio
- [ ] El nuevo Port tiene su binding en el ServiceProvider del módulo
- [ ] Los eventos de dominio se registran en la entidad y se despachan en el Handler
- [ ] El controlador o componente Livewire tiene ≤ 10 líneas por acción
- [ ] Ningún módulo importa clases del `Domain/` de otro módulo
- [ ] El Use Case tiene los tres archivos: Handler, Input, Output
