# Plan de Implementación Frontend — Divulgación Pública (Admin)

**Módulo:** `CatalogoPublico`
**Área:** `/admin/divulgacion`
**Stack:** TALL (Tailwind CSS v4 · Alpine.js · Laravel 13 · Livewire 4) + Flux UI v2
**Fecha:** 2026-05-06

---

## 1. Contexto y Fuentes

| Insumo | Qué aporta |
|--------|-----------|
| Endpoints: `POST /sincronizaciones`, `PATCH /configuracion`, `GET /{occurrenceID}` | Contratos de entrada/salida; definen los handlers a invocar |
| `Divulgacion.jsx` + `Components.jsx` (referencias) | Estructura visual, flujo de pantallas, agrupación de campos |
| `.feature` de sincronización | Actores, flujo de escenarios, datos de prueba reales |
| `frontend-design.md` | Tokens de color, tipografía, componentes Flux UI autorizados |
| `layouts/admin/sidebar.blade.php` | Layout admin existente con `@stack('admin-nav-items')` |

---

## 2. Mapa de Pantallas

El prototipo de referencia identifica **cuatro superficies distintas**. Traducción al TALL Stack:

```
/admin/divulgacion                     → TablaEspecimenesDivulgados   (Livewire full-page)
/admin/divulgacion/sincronizar         → SincronizarEspecimenes       (Livewire full-page, 3 pasos)
  └─ [modal dentro de la tabla]        → ConfigurarVisibilidad         (Livewire anidado)
  └─ [drawer dentro de la tabla]       → VistaPublicaEspecimen         (Alpine.js puro)
```

---

## 3. Mapa de Componentes

### 3.1 Clases Livewire (`.php`)

```
Modules/CatalogoPublico/app/Presentation/Http/Controllers/
├── TablaEspecimenesDivulgadosController.php     ← full-page, ruta index
├── SincronizarEspecimenesController.php         ← full-page, wizard 3 pasos
└── ConfigurarVisibilidadController.php          ← componente anidado (modal)
```

### 3.2 Vistas Livewire + Blade (`.blade.php`)

```
Modules/CatalogoPublico/resources/views/
├── livewire/
│   ├── tabla-especimenes-divulgados.blade.php
│   ├── sincronizar-especimenes.blade.php
│   └── configurar-visibilidad.blade.php
└── components/
    ├── visibility-toggle.blade.php          ← botón ojo (visible/oculto)
    ├── visibility-progress.blade.php        ← barra N/15 + texto
    ├── field-group.blade.php                ← sección con título de grupo
    ├── field-row.blade.php                  ← fila campo + valor + toggle
    ├── occurrence-status-badge.blade.php    ← chip present / loaned
    └── sync-stepper.blade.php              ← indicador de paso 1→2→3
```

### 3.3 Componentes Flux UI utilizados

| Superficie | Componentes Flux |
|-----------|-----------------|
| Layout | `flux:sidebar`, `flux:sidebar.nav`, `flux:sidebar.item`, `flux:sidebar.group` |
| Tabla | `flux:table`, `flux:table.head`, `flux:table.row`, `flux:table.cell` |
| Formularios | `flux:field`, `flux:label`, `flux:input`, `flux:checkbox`, `flux:switch` |
| Acciones | `flux:button`, `flux:dropdown`, `flux:menu`, `flux:menu.item` |
| Feedback | `flux:badge`, `flux:callout` |
| Modal | `flux:modal` (para ConfigurarVisibilidad) |
| Navegación | `flux:breadcrumbs`, `flux:breadcrumbs.item` |

---

## 4. Agrupación de los 15 Campos de Visibilidad

Derivada de `Divulgacion.jsx`. Aplica en el Step 2 del wizard y en el modal de configuración.

| Grupo | Campos | ¿Sensible? |
|-------|--------|-----------|
| **Identificación** | `occurrenceID` | — |
| **Taxonomía** | `scientificName`, `family`, `genus` | — |
| **Registro** | `individualCount`, `typeStatus`, `typeNotes`, `specimenNotes`, `occurrenceStatus` | `typeNotes`, `specimenNotes` |
| **Recolección** | `samplingProtocol`, `recordedBy` | `recordedBy` |
| **Localización** | `country`, `localityName`, `decimalLatitude`, `decimalLongitude` | `localityName`, `lat`, `lng` |

Los campos marcados como **sensibles** deben mostrar un `flux:badge variant="warning"` con el texto "sensible" junto al nombre del campo como advertencia visual.

---

## 5. Pantalla 1 — Tabla de Especímenes Divulgados

**Ruta:** `GET /admin/divulgacion`
**Componente:** `TablaEspecimenesDivulgados`

### 5.1 Estructura visual (extraída del prototipo `DivulgacionList`)

```
[Page Header]
  H1: "Tabla de divulgación"        (font-serif, text-2xl)
  Subtítulo: "N especímenes..."     (text-text-secondary, text-sm)
  Acciones: [Abrir portal público] [Sincronizar nuevos →]

[Toolbar]
  flux:input (búsqueda live)   |   Filtro familia   |   Filtro visibilidad

[flux:table]
  Columnas: occurrenceID | scientificName | family | localityName | Campos visibles | Acciones
  Fila:
    - occurrenceID en Inter Medium 500
    - scientificName en Roboto Slab italic (font-serif italic)
    - localityName: valor o "oculto" en text-text-secondary italic si no es visible
    - Barra de progreso: <x-visibility-progress :visible="N" :total="15" />
    - Acciones: [Vista pública] [Configurar]
```

### 5.2 Propiedades públicas Livewire

```php
public string $buscar = '';
public string $filtroFamilia = '';
public string $filtroVisibilidad = '';   // 'todos' | 'completo' | 'parcial'

// Estado del modal de configuración
public bool $modalConfigAbierto = false;
public ?string $occurrenceIDActivo = null;

// Estado del drawer de vista pública (Alpine, no Livewire)
// Se maneja con x-data en la vista
```

### 5.3 Métodos Livewire

```php
// Computed property — lee desde EspecimenDivulgableRepositoryInterface (NO handler)
public function getEspecimenes(): LengthAwarePaginator { ... }

public function abrirConfiguracion(string $occurrenceID): void
public function cerrarConfiguracion(): void
```

### 5.4 Navegación en sidebar

Vía `@push('admin-nav-items')` en la vista:

```blade
@push('admin-nav-items')
<flux:sidebar.group heading="Divulgación">
    <flux:sidebar.item icon="table-cells"
        :href="route('admin.divulgacion.index')"
        :current="request()->routeIs('admin.divulgacion.*')"
        wire:navigate>
        Tabla divulgada
    </flux:sidebar.item>
    <flux:sidebar.item icon="arrow-up-tray"
        :href="route('admin.divulgacion.sincronizar')"
        wire:navigate>
        Sincronizar
    </flux:sidebar.item>
</flux:sidebar.group>
@endpush
```

---

## 6. Pantalla 2 — Wizard de Sincronización (3 pasos)

**Ruta:** `GET /admin/divulgacion/sincronizar`
**Componente:** `SincronizarEspecimenes`
**Handler:** `SincronizarEspecimenesHandler`

### 6.1 Paso 1 — Selección de especímenes

```
[Sync Stepper]  ●─────○─────○
               Selección  Config.  Confirmación

[Card toolbar]
  Contador: "N de M especímenes seleccionados"
  Botón: "Datos divulgables · N/15 ▾" → flux:dropdown con checkboxes por grupo

[flux:table con checkboxes]
  Columnas: ☐ | occurrenceID | scientificName | family | localityName | typeStatus | occurrenceStatus
  Fila seleccionada: bg-science-blue/5 (Tailwind: bg-blue-50 o token equivalente)

[Footer fijo]
  Info: icono info + "Por defecto todos los campos están habilitados"
  Botón: "Sincronizar N especímenes →"
```

**Propiedades:**

```php
public int $paso = 1;

// Paso 1
public array $especimenesDisponibles = [];   // cargados en mount() desde EspecimenRepositoryInterface
public array $seleccionados = [];            // occurrenceIDs seleccionados
public array $configuracionGlobal = [];      // flags aplicados globalmente en paso 1

// Paso 2
public string $especimenActivoId = '';
public array $configuracionPorEspecimen = []; // ['EPN-001' => ['occurrenceIDVisible' => true, ...]]

// Paso 3
public array $occurrenceIDsActualizados = [];
```

**Métodos:**

```php
public function mount(): void          // carga $especimenesDisponibles
public function toggleSeleccion(string $id): void
public function seleccionarTodos(bool $valor): void
public function toggleCampoGlobal(string $campo): void
public function aplicarConfigGlobalATodos(): void
public function avanzarPaso(): void    // valida antes de pasar al siguiente
public function retrocederPaso(): void
public function copiarConfigDeActivo(): void   // "Aplicar a todos" del paso 2
public function sincronizar(): void    // invoca SincronizarEspecimenesHandler
```

### 6.2 Paso 2 — Configuración de visibilidad por espécimen

```
[Sync Stepper]  ●─────●─────○

[Grid 2 columnas]

  [Aside — lista de especímenes seleccionados]
    Por cada espécimen:
      occurrenceID (Inter Medium)
      scientificName (font-serif italic, text-xs)
      N/15 campos     ← <x-visibility-progress />
    Item activo: border-l-2 border-science-blue bg-surface

  [Panel principal — campos del espécimen activo]
    Header:
      "Editando: EPN-0012 · Atta cephalotes"
      "N de 15 campos visibles" + ícono ojo

    Por cada grupo (Identificación, Taxonomía, etc.):
      <x-field-group title="Taxonomía">
        <x-field-row
          campo="scientificName"
          descripcion="Nombre científico"
          :valor="$especimenActivo->scientificName"
          :visible="$configuracionPorEspecimen[$especimenActivoId]['scientificNameVisible']"
          wire:click="toggleCampo('scientificName')"
        />
      </x-field-group>

[Footer]
  [← Atrás]   [Sincronizar N especímenes →]
```

### 6.3 Paso 3 — Confirmación de éxito

```
[Sync Stepper]  ●─────●─────●

[Card centrada]
  Ícono: círculo bg-success-tint + ícono cloud/check text-bio-green (size 32)
  H2: "Sincronización completada"   (font-serif)
  Texto: "Se sincronizaron N especímenes a la tabla de divulgación."
  Acciones: [Sincronizar más] [Ver tabla de divulgación →]
```

---

## 7. Modal — Configurar Visibilidad (especímenes ya sincronizados)

**Componente anidado:** `ConfigurarVisibilidad`
**Handler:** `ModificarConfiguracionDivulgacionHandler`
**Trigger:** botón "Configurar" en la tabla de `TablaEspecimenesDivulgados`

```
flux:modal (tamaño lg)
  Header: "Configurar visibilidad · EPN-0012"

  [Igual al panel principal del Paso 2, pero para un solo espécimen]
  Grupos de campos con <x-field-row> y visibility-toggle

  Footer:
    flux:badge variant="warning": "Los cambios se aplican inmediatamente al portal público"
    [Cancelar]  [Guardar configuración]
```

**Propiedades:**

```php
public string $occurrenceID = '';
public array $configuracion = [];       // 15 flags
public bool $guardado = false;          // estado de éxito inline
```

**Método:**

```php
public function guardar(): void   // invoca ModificarConfiguracionDivulgacionHandler
                                  // emite evento Livewire 'configuracionActualizada'
                                  // TablaEspecimenesDivulgados escucha y refresca
```

---

## 8. Drawer Alpine — Vista Pública

**Tecnología:** Alpine.js puro (sin viaje al servidor — datos ya en la fila de la tabla).
**Trigger:** botón "Vista pública" en cada fila.

```blade
<div x-data="{ abierto: false, especimen: null }">
    {{-- Botón trigger en la fila --}}
    <flux:button
        variant="ghost" size="sm"
        @click="abierto = true; especimen = {{ Js::from($row) }}"
    >
        Vista pública
    </flux:button>

    {{-- Scrim + Drawer --}}
    <div x-show="abierto" x-transition class="fixed inset-0 bg-black/45 z-40" @click="abierto = false"></div>
    <aside x-show="abierto" x-transition:enter="translate-x-full" class="fixed inset-y-0 right-0 w-96 bg-surface shadow-overlay z-50 overflow-y-auto">
        <div class="p-5 border-b border-border flex items-center justify-between">
            <div>
                <p class="text-xs text-text-secondary uppercase tracking-wider">Vista pública · portal de divulgación</p>
                <h2 class="text-xl font-serif italic font-semibold" x-text="especimen?.scientificName"></h2>
            </div>
            <flux:button variant="ghost" icon="x-mark" @click="abierto = false" />
        </div>
        {{-- flux:callout informativo + lista de campos visibles --}}
    </aside>
</div>
```

Este drawer usa Alpine porque los datos ya están presentes en el DOM de la tabla — no tiene sentido hacer un round-trip al servidor.

---

## 9. Componentes Blade Anónimos — Especificaciones

### `visibility-toggle.blade.php`

```
@props(['on' => true, 'sensitive' => false])
```

- Estado `on`: `flux:button` con `icon="eye"`, colores `text-bio-green`
- Estado `off`: `flux:button` con `icon="eye-slash"`, colores `text-text-secondary`
- Si `$sensitive && !$on`: agrega `flux:badge variant="warning" size="sm"` con "sensible"

### `visibility-progress.blade.php`

```
@props(['visible' => 0, 'total' => 15])
```

Barra `div` con ancho calculado `{{ ($visible / $total) * 100 }}%`, color `bg-bio-green`.
Texto caption: `{{ $visible }}/{{ $total }}` en `text-xs text-text-secondary`.

### `field-row.blade.php`

```
@props(['campo', 'descripcion', 'valor' => null, 'visible' => true, 'sensitive' => false])
```

Fila con 3 columnas:
1. Nombre del campo + descripción + badge "sensible" si aplica
2. Valor del espécimen (o "oculto del portal" en italic si `!$visible`)
3. `<x-visibility-toggle :on="$visible" :sensitive="$sensitive" />`

### `occurrence-status-badge.blade.php`

```
@props(['status'])
```

- `present`: `flux:badge variant="success"` con icono `check-circle`
- `loaned`: `flux:badge variant="warning"` con icono `clock`

### `sync-stepper.blade.php`

```
@props(['paso' => 1])  // 1, 2 o 3
```

Tres círculos conectados por líneas. El paso activo usa `bg-science-blue text-white`. Los completos usan `bg-bio-green text-white` + ícono `check`. Los pendientes usan `bg-border text-text-secondary`.

---

## 10. Flujo de Estados de UI

| Estado | Mecanismo | Elemento visual |
|--------|-----------|----------------|
| **Cargando** sincronización | `wire:loading` en el botón "Sincronizar" | `flux:button` con `wire:loading.attr="disabled"` + spinner via `wire:loading` |
| **Cargando** tabla | `wire:loading.class="opacity-50"` en `flux:table` | Tabla translúcida durante `$buscar` live |
| **Éxito** sincronización | Paso 3 del wizard + `flux:callout variant="success"` | Card centrada con ícono verde |
| **Éxito** configuración guardada | `$guardado = true` en modal | `flux:callout variant="success"` inline debajo del footer del modal, auto-dismiss con Alpine |
| **Error 422** (validación) | `$errors` de Livewire | `flux:error` junto a los campos afectados |
| **Error 404** (espécimen no encontrado) | `catch (\RuntimeException)` en el método | `flux:callout variant="danger"` en el modal |
| **Error genérico** | `try/catch \Throwable` | `flux:callout variant="danger"` con mensaje genérico |

---

## 11. Integración Backend — Llamadas por Componente

| Componente | Handler invocado | Dónde |
|-----------|-----------------|-------|
| `SincronizarEspecimenes::sincronizar()` | `SincronizarEspecimenesHandler::handle()` | Método `sincronizar()`, paso 2→3 |
| `ConfigurarVisibilidad::guardar()` | `ModificarConfiguracionDivulgacionHandler::handle()` | Método `guardar()`, al confirmar el modal |
| `TablaEspecimenesDivulgados` (datos) | `EspecimenDivulgableRepositoryInterface` directamente | `mount()` + computed property (no es un Use Case de consulta masiva) |
| Vista pública drawer | Sin backend | Alpine.js con datos del DOM |

> **Nota sobre el GET `/{occurrenceID}`:** este endpoint está orientado a la API pública (portal de divulgación). En el admin, la vista previa usa los datos que ya están en memoria en la tabla, por lo que no invoca el endpoint — evita 15 round-trips al abrir el drawer.

---

## 12. Mapeo Feature → Pantallas

| Escenario `.feature` | Pantalla que lo satisface |
|---------------------|--------------------------|
| **Antecedentes** — especímenes en base interna | Los especímenes disponibles se listan en el Paso 1 del wizard (`$especimenesDisponibles`) |
| **Escenario 1** — sincronizar con todos los campos habilitados | Paso 1: seleccionar todos + no modificar configuración global → Paso 2: todos los toggles en ON → `sincronizar()` → Paso 3 |
| **Escenario 2** — sincronizar especificando campos | Paso 1: seleccionar + ajustar configuración → Paso 2: toggle individual por campo → `sincronizar()` |
| **Escenario 3** — modificar configuración de especímenes ya sincronizados | Tabla principal → botón "Configurar" → modal `ConfigurarVisibilidad` → `guardar()` → verificar en drawer "Vista pública" |

---

## 13. Rutas a Registrar

```php
// Modules/CatalogoPublico/routes/web.php
Route::middleware(['auth', 'verified'])
    ->prefix('admin/divulgacion')
    ->name('admin.divulgacion.')
    ->group(function () {
        Route::get('/', TablaEspecimenesDivulgadosController::class)
            ->name('index');
        Route::get('/sincronizar', SincronizarEspecimenesController::class)
            ->name('sincronizar');
    });
```

---

## 14. Checklist de Implementación

- [ ] Registrar rutas en `web.php` del módulo
- [ ] Crear `TablaEspecimenesDivulgadosController` + vista
- [ ] Crear `SincronizarEspecimenesController` + vista (3 pasos con `$paso`)
- [ ] Crear `ConfigurarVisibilidadController` + vista (modal anidado)
- [ ] Crear los 6 Blade components anónimos del módulo
- [ ] Añadir `@push('admin-nav-items')` con los dos ítems de navegación
- [ ] Confirmar que los tokens de color del `@theme` de `app.css` incluyen `bg-main`, `surface`, `text-primary`, `text-secondary`, `border`, `bio-green`, `science-blue`, `success`, `warning`, `error`
- [ ] Verificar que `flux:switch` o `flux:checkbox` sirve para `visibility-toggle` antes de crear un componente custom
