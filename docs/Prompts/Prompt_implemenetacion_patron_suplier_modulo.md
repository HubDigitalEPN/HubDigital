# Prompt: Implementación del Patrón Customer-Supplier con ACL entre Módulos

**Contexto del Proyecto:**
El proyecto Hub Digital está construido con una arquitectura monolítica modular (nwidart/laravel-modules)
donde cada módulo es un contexto delimitado (Bounded Context) con su propio Dominio, Aplicación e Infraestructura.
Cuando un módulo (Customer) necesita datos que pertenecen a otro módulo (Supplier), NO puede importar
directamente las clases del Dominio del Supplier. En su lugar, aplica el patrón Customer-Supplier con una
Anti-Corruption Layer (ACL).

El Supplier ya tiene su dominio implementado y cerrado. El Customer necesita consumir datos del Supplier
de forma desacoplada: si el Supplier cambia su schema interno, solo el adaptador ACL del Customer se ve afectado.

---

### Insumos proporcionados:

1. **[Módulo Supplier]:** (FUENTE DE DATOS). El módulo que posee los datos. Debes identificar:
   - La tabla (`schema.tabla`) donde vive el dato.
   - Los campos relevantes que el Customer necesita.
   - El nombre exacto de esas columnas en el schema del Supplier.

2. **[Handler del Customer que necesita datos del Supplier]:** (OBJETIVO). El Use Case donde se hará
   la integración. Identifica exactamente en qué punto del Handler se necesita llamar al Supplier.

3. **[Estructura del módulo Customer]:** (REGLAS ESTRICTAS). Revisa los archivos existentes en
   `Application/Ports/`, `Infrastructure/Adapters/` y el `ServiceProvider` del módulo Customer para
   saber dónde colocar cada artefacto nuevo.

---

### Tu Tarea:

Actúa como un **Arquitecto de Software DDD**. Debes generar un Plan de Implementación Detallado
para conectar el Customer al Supplier usando el patrón Customer-Supplier + ACL. **No escribas código
final todavía**, preséntame el plan con seudocódigo. NO modifiques el Dominio ni la Aplicación del
Supplier bajo ninguna circunstancia.

---

### Directrices Estrictas del Patrón:

#### 1. Aislamiento de módulos — Regla de oro

Está **PROHIBIDO** importar clases del namespace `Modules\<Supplier>\Domain\` o
`Modules\<Supplier>\Application\` desde cualquier clase del Customer.
El único punto de contacto entre módulos es la base de datos compartida (en un monolito).

#### 2. DTO del contrato (lenguaje del Customer)

Crea un DTO `readonly` en `Modules\<Customer>\Application\Ports\` con **solo los campos
que el Customer realmente necesita**. No copies el modelo completo del Supplier.
Este DTO habla el idioma del Customer, con nombres que tienen sentido para su dominio.

Documenta explícitamente cualquier renombre o transformación:
- `colector` (Supplier) → `recordedBy` (Customer)
- `disposition` (Supplier) → `typeStatus` (Customer)
- Campos sin equivalente en el Supplier → `null` con un comentario que justifique por qué

#### 3. Interfaz del Port (el contrato)

Crea la interfaz `<Supplier>Port` en `Modules\<Customer>\Application\Ports\`.
Esta interfaz declara lo que el Customer necesita. La implementación concreta no tiene
nada que ver con el Dominio o la Aplicación del Customer.

Los métodos de la interfaz devuelven el DTO del Customer, nunca modelos Eloquent ni
entidades del Supplier.

#### 4. Adaptador ACL (toda la traducción aquí)

Crea la clase `<Supplier>Adapter` en `Modules\<Customer>\Infrastructure\Adapters\`.
Esta clase implementa la interfaz del Port y es la ÚNICA clase que conoce el schema
del Supplier.

Reglas del adaptador:
- Toda la lógica de traducción vive en un método privado `traducir()`.
- Usar un modelo Eloquent read-only local que apunte a la tabla del Supplier
  (`protected $table = '<supplier_schema>.<tabla>'`). Este modelo reside en
  `Infrastructure/Adapters/` — **nunca en `Infrastructure/Persistence/Eloquent/Models/`**,
  que es para modelos del propio Customer. Es un detalle de implementación interno del adaptador.
- Para traversar jerarquías (ej. taxonomía padre-hijo), usar una CTE recursiva en SQL nativo.
- Si un campo del Supplier no tiene equivalente en el Customer, documentar el motivo con
  un comentario inline.

#### 5. Actualizar el Handler del Customer

Inyectar el Port (la interfaz, no el adaptador) en el constructor del Handler.
Llamar al Port ANTES de cualquier operación de persistencia del Customer.
Si el Port devuelve null (el dato no existe en el Supplier), decidir si es un error
de negocio o si el proceso continúa sin ese dato.

Toda la lógica de persistencia del Customer debe seguir dentro de `executeTransactional`.

#### 6. Binding en el ServiceProvider

En el `$bindings` del ServiceProvider del módulo Customer, agregar:
```
<Supplier>Port::class => <Supplier>Adapter::class,
```
Nunca registrar el binding en `AppServiceProvider` ni en `bootstrap/providers.php`.

#### 7. Tests — separación de responsabilidades

**Behat (comportamiento del Use Case):**
Crear `Fake<Supplier>Port` en `Modules\<Customer>\tests\Support\` con un método `agregar()`
para poblar el fake desde el paso `Dado` del Context.
En `@BeforeScenario` del Context, registrar el fake ANTES de resolver el Handler:
```php
$this->fakeProveedor = new Fake<Supplier>Port();
self::$app->instance(<Supplier>Port::class, $this->fakeProveedor);
$this->handler = $this->make(<Handler>::class);
```
El paso `Dado` popula el fake con los datos del Supplier. El Behat testea el comportamiento
del Use Case, no la traducción ACL.

**Pest Integration (traducción ACL):**
Crear `Modules\<Customer>\tests\Integration\<Supplier>AdapterTest.php`.
Insertar datos directamente en la tabla del Supplier con `DB::table(...)` y verificar que
el adaptador los traduce correctamente al DTO del Customer.
Cubrir: traducción correcta de campos, traversal de jerarquías, valores null por defecto,
registros inexistentes.

**Behat — satisfacer FK del Customer hacia el Supplier:**
Si la entidad del Customer persiste un `especimen_id` (UUID) como FK hacia la tabla del Supplier,
el paso `Dado` que siembra la entidad del Customer debe insertar primero en las tablas del Supplier
que esa FK referencia (`DB::table('supplier_schema.tabla')->insert(...)`). Es la única excepción
pragmática donde el Context toca la DB del Supplier directamente — está justificada porque es
un monolito y la FK debe estar satisfecha para que la migración no falle. El Behat sigue sin
importar clases del dominio del Supplier.

---

### Entregable esperado:

Presenta un **Plan de Diseño Estructurado** que incluya:

1. **Campos del contrato:** Lista exacta de campos que el Customer necesita del Supplier,
   con la columna de origen y el nombre en el DTO. Documenta las transformaciones
   (renombres, defaults, nulls justificados).

2. **Archivos a crear:**
   - `Application/Ports/<NombreDTO>.php` — campos y tipos del DTO
   - `Application/Ports/<Supplier>Port.php` — firma de los métodos
   - `Infrastructure/Adapters/<Supplier>ReadModel.php` — tabla apuntada
   - `Infrastructure/Adapters/<Supplier>Adapter.php` — seudocódigo del método `traducir()`
   - `tests/Support/Fake<Supplier>Port.php` — método `agregar()` y las implementaciones de la interfaz

3. **Archivos a modificar:**
   - Handler del Customer: dónde se inyecta el Port y en qué paso del `handle()` se llama
   - ServiceProvider: el binding exacto a agregar

4. **Seudocódigo del método `traducir()`:** con cada campo del DTO mapeado a su columna
   del Supplier. Incluir comentarios ACL explícitos para renombres no obvios.

5. **Tests de integración:** al menos 4 casos a cubrir por el Pest Integration test del adaptador
   (campo base, jerarquía, null por defecto, registro inexistente).

---

### Errores comunes a evitar (aprendidos en implementaciones reales)

#### Anti-patrón: tabla espejo en el Customer

**No crees una tabla en el Customer que copie los datos del Supplier** (ej. `divulgacion.especimenes`
que replica `taxonomia.especimenes`). Este patrón parece seguro pero introduce:

- **Dependencia circular en la UI**: antes del primer sync la tabla copia está vacía, por lo que
  cualquier pantalla que la consulte no muestra nada. La UI queda inutilizable hasta que se ejecuta
  el caso de uso de sincronización, que a su vez necesita la UI para ejecutarse.
- **Datos desactualizados**: la copia siempre estará un paso detrás del Supplier.
- **Complejidad innecesaria**: requiere un proceso de sync, manejo de conflictos y limpieza.

**La alternativa correcta**: el Adaptador ACL lee directamente de la tabla del Supplier (acceso
legítimo en un monolito). El Customer solo persiste lo que le pertenece: su propia configuración
o su propia agregación de datos.

#### FK al PK estable del Supplier, nunca a la clave natural

Cuando el Customer persiste una referencia al Supplier, usa el **PK UUID** del agregado del
Supplier como FK — nunca la clave natural (ej. `occurrence_id`, `código`, `email`).

Las claves naturales pueden ser:
- **Nulas** al momento de la inserción (se asignan después)
- **Mutables** (pueden cambiar por negocio)
- **No únicas** en ciertos estados del ciclo de vida

El UUID del PK es inmutable desde la creación y siempre existe.

Consecuencia: cualquier query del Customer que necesite filtrar por clave natural debe hacer
un **JOIN con la tabla del Supplier** para resolverla. Esto es correcto — el adaptador y la
infraestructura del Customer ya conocen esa tabla.

```php
// ✅ Correcto — JOIN para resolver la clave natural
EspecimenDivulgableModel::query()
    ->join('taxonomia.especimenes', 'taxonomia.especimenes.id', '=', 'divulgacion.especimenes_divulgables.especimen_id')
    ->where('taxonomia.especimenes.occurrence_id', $occurrenceId)
    ->first();

// ❌ Incorrecto — filtra por columna que ya no existe en la tabla del Customer
EspecimenDivulgableModel::where('occurrence_id', $occurrenceId)->first();
```

Cuando adoptes esta decisión, **audita todas las queries del módulo** — incluidos controllers
Livewire, computed properties y métodos de búsqueda en lote — que filtraban por la clave
natural que eliminaste.

#### La lógica de filtrado de visibilidad pertenece al Handler, no a la entidad

Si el Customer tiene una entidad con flags de visibilidad (ej. `EspecimenDivulgable`) y necesita
filtrar los campos de un DTO del Supplier según esos flags, esa lógica va en un **método privado
del Handler**, no en la entidad del dominio.

```php
// ✅ Correcto — el Handler filtra usando los flags de la entidad y el DTO del Supplier
private function filtrarPorVisibilidad(EspecimenDivulgable $divulgable, DatosEspecimenProveedor $datos): array
{
    $result = [];
    if ($divulgable->occurrenceIDVisible()) { $result['occurrenceID'] = $datos->occurrenceId; }
    // ...
    return $result;
}

// ❌ Incorrecto — la entidad del dominio del Customer conoce el DTO del Port
public function obtenerDatosVisibles(DatosEspecimenProveedor $datos): array { ... }
```

La entidad solo expone sus flags (`occurrenceIDVisible(): bool`). El Handler une la entidad
con el DTO del Port para producir el resultado filtrado.

---

**¿Entendido? Analiza los insumos adjuntos y genera el plan de integración Customer-Supplier.**
