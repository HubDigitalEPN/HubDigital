# Especificación — Gestión de imágenes por nivel taxonómico

> Feature fuente: `tests/Behat/Features/GestionContenidoTaxonomico/gestion_imagenes_nivel_taxonomico.feature`
> Módulo (Bounded Context): **CatalogoPublico**
> Ubicación funcional: **sección Divulgación del menú del curador (`/dashboard` → grupo "Divulgación")**

---

## 1. Contexto y ubicación

La gestión de imágenes es una capacidad **de administración** (la ejerce el curador), no del portal público. Por eso vive
dentro del mismo árbol de divulgación que ya existe en `CatalogoPublico`:

- **Rutas:** `Modules/CatalogoPublico/routes/web.php`, grupo `middleware(['auth','verified'])->prefix('divulgacion')->name('divulgacion.')`.
  Se añade `Route::get('/imagenes', GestionImagenesTaxonomicas::class)->name('imagenes')` junto a `index` y `sincronizar`.
- **Menú:** `resources/views/layouts/app/sidebar.blade.php`, dentro de `<flux:sidebar.group heading="Divulgación">` (líneas ~280-297),
  se agrega un `<flux:sidebar.item icon="photo" :href="route('divulgacion.imagenes')" ...>Imágenes</flux:sidebar.item>`.
- **Consumo público:** el portal (`portal-catalogo`) solo *lee* la imagen por defecto resuelta por este feature. La subida y la
  elección de la portada nunca se exponen al visitante.

> **Alcance (acotado):** las imágenes solo existen para los **dos últimos niveles** — **género → especie → registro específico
> (espécimen)**. Filo/clase/orden/familia **no admiten imágenes** en absoluto: ni se suben, ni se les calcula portada por defecto,
> ni se pintan en el portal. Esto es coherente con la simplificación previa del portal (esas tarjetas ya son simples) y elimina
> cualquier ambigüedad: la imagen se sube al espécimen y la portada por defecto solo bubbleea hasta **especie** y **género**.

---

## 2. Reglas de dominio (invariantes derivadas del feature)

| # | Regla | Escenario fuente |
|---|-------|------------------|
| R1 | Subir una imagen **exige nombre y apellido** del autor. Sin ambos, se rechaza y **la imagen no se almacena**. | "Subir…exige nombre y apellido", "Rechazar la subida si falta…" |
| R2 | Al subir, se **genera una marca de agua** con el texto `"Nombre Apellido"` sobre la imagen. | "Subir…exige nombre y apellido" |
| R3 | La imagen **registra como autor** a `"Nombre Apellido"`. | "Subir…exige nombre y apellido" |
| R4 | La **primera** imagen del subárbol de un taxón se vuelve su **imagen por defecto**, solo en **género** y **especie** (si aún no tenían defecto). | "La primera imagen subida del subárbol…" |
| R5 | Una imagen subida a un espécimen se **propaga como defecto** únicamente a su **especie** y su **género** (los niveles sin defecto previo). | "La imagen subida a un espécimen se vuelve el defecto…" |
| R6 | El curador puede **sobrescribir** la imagen por defecto **solo en género y especie**. | "El curador puede sobrescribir…" |
| R7 | El sistema **no admite imágenes por defecto en niveles superiores a género** (filo/clase/orden/familia): la operación se **rechaza** y no se crea defecto. | "El sistema no admite imágenes…" |
| R8 | La **galería pública** de un taxón (género/especie) devuelve **primero** su imagen por defecto. | varios |

**Mapeo de niveles del feature → dominio:** el feature usa `specificEpithet` como nombre de nivel con valor `"Atta cephalotes"`.
En el dominio eso es `RangoTaxonomico::Species`, con `valorTaxon = scientificName` (genus + specificEpithet). Niveles que
**admiten imagen por defecto** = `{ Genus, Species }`; filo/clase/orden/familia quedan excluidos por completo.

---

## 3. Modelo de dominio (`app/Domain/`)

> Sin Laravel ni Carbon. `DateTimeImmutable`. VOs `final readonly`, constructor privado, factory estático.

### 3.1 Value Objects (`Domain/ValueObjects/`)
- **`ImagenTaxonomicaId`** — UUID (espejo de `EspecimenDivulgableId`).
- **`AutorImagen`** — `nombre`, `apellido`; factory `crear(string $nombre, string $apellido)` lanza
  `AutorImagenInvalidoException` si cualquiera está vacío (trim). Expone `nombreCompleto(): string` → `"{$nombre} {$apellido}"`.
- **`ArchivoImagen`** — `nombreOriginal`, `rutaAlmacenada`, `disco`. Identidad lógica del binario ya persistido (lo físico vive en infra).
- **`RangoTaxonomico`** — *ya existe*; se reutiliza. Añadir helper `admiteImagen(): bool` → `in_array($this, [Genus, Species])`
  (única lista blanca de niveles con portada; los demás se rechazan).

### 3.2 Entidades (`Domain/Entities/`)
- **`ImagenTaxonomica`** (aggregate root)
  - Estado: `id`, `occurrenceID` (espécimen), `archivo: ArchivoImagen`, `autor: AutorImagen`, `marcaAguaAplicada: bool`, `subidaEn: DateTimeImmutable`.
  - Factory `subir(id, occurrenceID, archivo, autor)` → valida R1 (autor vía VO), marca `marcaAguaAplicada` cuando infra confirme R2,
    emite evento `ImagenSubida`.
- **`ImagenPorDefectoDeTaxon`** (registro de portada por nodo)
  - Estado: `nivel: RangoTaxonomico`, `valorTaxon: string`, `imagenId: ImagenTaxonomicaId`.
  - Solo se construye para niveles que `admiteImagen()` (Genus/Species); cualquier intento en otro nivel lanza `NivelNoSobrescribibleException`.
  - `asignarSiVacio(...)` (R4/R5: solo si no había defecto) y `sobrescribir(...)` (R6: reemplaza el defecto existente).

### 3.3 Servicios de dominio (`Domain/Services/`)
- **`PropagadorImagenPorDefecto`** — puro, sin estado. Entrada: `JerarquiaTaxonomica` del espécimen + defaults actuales + nueva `ImagenTaxonomicaId`.
  Salida: lista de `ImagenPorDefectoDeTaxon` a asignar, considerando **solo especie y género** (los niveles que `admiteImagen()`) y únicamente los que **no** tenían defecto. Implementa R4 + R5.

### 3.4 Eventos (`Domain/Events/`)
- `ImagenSubida`, `ImagenPorDefectoAsignada`, `ImagenPorDefectoSobrescrita`.

### 3.5 Excepciones (`Domain/Exceptions/`)
- `AutorImagenInvalidoException`, `NivelNoSobrescribibleException`, `ImagenNoEncontradaException`.

### 3.6 Repositorios (interfaces, `Domain/Repositories/`)
- **`ImagenTaxonomicaRepositoryInterface`**: `nextIdentity()`, `guardar(ImagenTaxonomica)`, `buscarPorId(ImagenTaxonomicaId)`,
  `listarPorSubarbol(RangoTaxonomico $nivel, string $valorTaxon): list<ImagenTaxonomica>`.
- **`ImagenPorDefectoRepositoryInterface`**: `obtener(RangoTaxonomico, string $valor): ?ImagenPorDefectoDeTaxon`,
  `guardar(ImagenPorDefectoDeTaxon)`, `mapaDeDefectosDeLinea(JerarquiaTaxonomica): array`.

---

## 4. Aplicación (`app/Application/`)

### 4.1 Casos de uso (`UseCases/<Nombre>/` con `Handler.php`, `Input.php`, `Output.php`)
- **`SubirImagenEspecimen`** — orquesta R1-R5:
  1. `AutorImagen::crear()` (R1). 2. `AlmacenamientoImagenesPort::guardar()` (binario→ruta). 3. `GeneradorMarcaAguaPort::aplicar(binario, autor->nombreCompleto())` (R2).
  4. `ImagenTaxonomica::subir()` + `guardar()` (R3). 5. `ProveedorJerarquiaDeEspecimenPort::obtener(occurrenceID)` → `PropagadorImagenPorDefecto` → persistir defaults (R4/R5). 6. publicar eventos. Todo dentro de `TransactionManagerPort`.
  - `Input`: `occurrenceID`, `nombreArchivo`, `contenidoBinario` (o ruta temporal), `nombreAutor`, `apellidoAutor`.
  - `Output`: `imagenId`, `autor`, `nivelesActualizados[]`.
- **`SeleccionarImagenPorDefecto`** — R6/R7: valida `nivel->esSobrescribible()`; si no, lanza y no persiste. `Input`: `nivel`, `valorTaxon`, `imagenId`. `Output`: `defectoActual`.
- **`ConsultarGaleriaTaxon`** — R8: devuelve imágenes del subárbol con la **portada primero**. `Input`: `nivel`, `valorTaxon`. `Output`: `imagenes[]` (primera = defecto).

### 4.2 Ports (`Application/Ports/`)
- **`AlmacenamientoImagenesPort`** — `guardar(string $binario, string $nombre): ArchivoImagen`, `eliminar(ArchivoImagen)`, `url(ArchivoImagen): string`.
- **`GeneradorMarcaAguaPort`** — `aplicar(string $binario, string $texto): string` (binario con marca).
- **`ProveedorJerarquiaDeEspecimenPort`** — `obtener(string $occurrenceID): JerarquiaTaxonomica` (reutiliza el read model / proveedor del árbol ya existente).

---

## 5. Infraestructura (`app/Infrastructure/`)

### 5.1 Migraciones (`database/migrations/`, esquema `divulgacion`)
- `..._create_imagenes_taxonomicas_table`: `id uuid pk`, `occurrence_id`, `nombre_original`, `ruta`, `disco`,
  `autor_nombre`, `autor_apellido`, `autor_nombre_completo`, `marca_agua_aplicada bool`, `timestamps`.
  (FK lógica a `divulgacion.especimenes.occurrence_id`; seguir el patrón idempotente `if (Schema::hasTable(...)) return;`).
- `..._create_imagenes_por_defecto_table`: `id uuid pk`, `nivel`, `valor_taxon`, `imagen_id uuid`, `unique(nivel, valor_taxon)`.

### 5.2 Persistencia
- `Models/ImagenTaxonomicaEloquentModel`, `Models/ImagenPorDefectoEloquentModel`.
- `Repositories/EloquentImagenTaxonomicaRepository`, `Repositories/EloquentImagenPorDefectoRepository` (mapean Eloquent ↔ entidad).

### 5.3 Adapters (`Infrastructure/Adapters/`)
- **`StorageImagenesAdapter`** implementa `AlmacenamientoImagenesPort` sobre `Storage::disk('public')` (carpeta `divulgacion/imagenes`).
- **`MarcaAguaAdapter`** implementa `GeneradorMarcaAguaPort`. **Requiere dependencia nueva** → propuesta: `intervention/image` v3 (usa `gd`/`imagick` ya disponibles). Aislada tras el Port, así el dominio/los tests no la conocen.
- **`JerarquiaDeEspecimenAdapter`** implementa `ProveedorJerarquiaDeEspecimenPort` reusando `TaxonomiaEspecimenReadModel` / `EloquentProveedorEspecimenesParaArbol`.

### 5.4 Bindings
En `CatalogoPublicoServiceProvider::$bindings`:
```php
ImagenTaxonomicaRepositoryInterface::class => EloquentImagenTaxonomicaRepository::class,
ImagenPorDefectoRepositoryInterface::class => EloquentImagenPorDefectoRepository::class,
AlmacenamientoImagenesPort::class          => StorageImagenesAdapter::class,
GeneradorMarcaAguaPort::class              => MarcaAguaAdapter::class,
ProveedorJerarquiaDeEspecimenPort::class   => JerarquiaDeEspecimenAdapter::class,
```

---

## 6. Presentación (`app/Presentation/` + vistas)
- **Controlador Livewire** `Presentation/Http/Controllers/GestionImagenesTaxonomicas` (capa de presentación: inyecta Handlers,
  arma Input DTO, renderiza desde Output DTO, ≤10 líneas por acción).
- **Vista** `resources/views/livewire/gestion-imagenes-taxonomicas.blade.php`:
  - Form de subida: `<flux:input type="file">` + nombre + apellido (Flux UI, tokens de color del proyecto, áreas de toque ≥44px).
  - Selector de taxón con autocompletado sobre datos reales (`<flux:input list=...>`, heurística de prevención de errores).
  - Galería del subárbol con la portada marcada; botón "Usar como portada" **habilitado solo cuando el nivel es género/especie** (R6/R7).
  - Patrón de tabla/tarjetas responsivas obligatorio (`hidden md:block` + tarjetas `md:hidden`).
- **Ruta + ítem de sidebar** según §1.
- **Integración portal:** `portal-catalogo` reemplaza los placeholders de género/especie por la imagen devuelta por `ConsultarGaleriaTaxon`.

---

## 7. Trazabilidad escenario → artefacto

| Escenario (.feature) | Artefacto principal | Reglas |
|---|---|---|
| Subir exige nombre y apellido | `AutorImagen`, `SubirImagenEspecimenHandler`, `MarcaAguaAdapter` | R1, R2, R3 |
| Rechazar si falta nombre/apellido | `AutorImagen` + `AutorImagenInvalidoException` | R1 |
| Primera imagen del subárbol = defecto (género/especie) | `PropagadorImagenPorDefecto`, `ImagenPorDefectoDeTaxon::asignarSiVacio` | R4, R8 |
| Propaga a especie y género | `PropagadorImagenPorDefecto` (solo niveles `admiteImagen()`) | R5 |
| Sobrescribir en género/especie | `SeleccionarImagenPorDefectoHandler`, `RangoTaxonomico::admiteImagen` | R6, R8 |
| No admite imágenes sobre género (filo/clase/orden/familia) | `SeleccionarImagenPorDefectoHandler` + `NivelNoSobrescribibleException` | R7 |

---

## 8. Refinamientos sugeridos al `.feature` (conformidad CLAUDE.md)
1. Línea 1: `#language: es` → **`# language: es`** (con espacio, como exige la convención).
2. Actor: el resto del BC público usa `el visitante`/`el investigador`, pero esta feature es administrativa y usa `el curador`
   (coherente con Sincronización y Tabla divulgada). Mantener `el curador` y documentarlo como la cara *admin* de CatalogoPublico.
3. Un solo `Cuando` por escenario: se respeta; los `Y` posteriores extienden el bloque correctamente.
4. No agregar `@listo` hasta que la feature pase **100% verde** en `--tags=@listo --strict`.
5. `Dado` debe sembrar vía interfaces de repositorio/Handler (nunca Eloquent directo) — ya es el patrón del Context vecino.

---

## 9. Flujo BDD a seguir (orden de implementación)
1. **Pest/Unit** de dominio aislado: `AutorImagen` (R1), `PropagadorImagenPorDefecto` (R4/R5), `RangoTaxonomico::esSobrescribible` (R6/R7).
2. `php artisan behat:scaffold CatalogoPublico GestionContenidoTaxonomico gestion_imagenes_nivel_taxonomico`.
3. Esqueleto: Entidad → Interfaz Repo → Handler(s) → Migración → Repo Eloquent → Binding.
4. Implementar pasos del Context (Fakes en memoria para repos y ports, igual que `InMemoryEspecimenDivulgableRepository`).
5. Behat verde ✅ → completar lógica de Handlers (eventos, invariantes).
6. **Pest/Integration**: repos Eloquent + adapters (storage/marca de agua) contra DB y disco.
7. Frontend/UI: Livewire + ruta + sidebar + integración portal.

---

## 10. Decisiones (confirmadas / pendientes)
- ✅ **Alcance:** imágenes solo para género → especie → registro específico. Filo/clase/orden/familia excluidos (confirmado).
- ✅ **Librería de marca de agua:** `intervention/image` v3, aislada tras `GeneradorMarcaAguaPort` (aceptado).
- ✅ **Disco de almacenamiento:** `public` (local), carpeta `divulgacion/imagenes` (aceptado).
- ⏳ **Formato/posición de la marca de agua:** texto `"Nombre Apellido"` — falta afinar esquina, opacidad y tamaño (se puede ajustar en la fase UI/adapter sin afectar dominio).
