# HANDOFF — Reubicación digital guiada + Trazabilidad de movimientos

> **Para el agente que toma este trabajo.** Esta tarea es grande: dos features Behat
> implementadas capa por capa, respetando Clean Architecture. Es demasiada para una sola
> sesión, por eso se reparte. **Trabaja UNA capa a la vez**, déjala verde/compilando,
> commitea, y **actualiza este archivo** (marca la casilla + nota) antes de terminar tu turno.

## Protocolo de trabajo (LÉEME PRIMERO)

1. Lee la sección **Estado actual** y toma la **primera casilla `[ ]` pendiente** del checklist.
2. Lee los archivos relevantes de esa capa (usa el **Mapa de APIs** de abajo para no re-explorar).
3. Implementa SOLO esa capa. No adelantes otras capas.
4. Verifica: `php -l` en archivos tocados; si es capa Behat, corre Behat in-memory (ver Comandos).
5. **Commit por capa, sin coautor** (ver Convenciones). Rama actual:
   `feature/iot/reubicacion-guiada-y-trazabilidad` (NO `develop`).
6. **Actualiza este HANDOFF**: marca `[x]`, añade una nota de 1 línea con lo hecho / hallazgos /
   sorpresas para el siguiente agente. Si descubriste una firma de API nueva, agrégala al Mapa.
7. Termina tu turno. El siguiente agente sigue con la próxima casilla.

## Contexto

Dos features en `Modules/InventarioGestionColeccion/tests/Behat/Features/TrazabilidadOperativaMovimientosCirculacion/`:
- `reubicacion_digital_guiada.feature` — reubicar especímenes a otro unit tray y unit trays a
  otra caja, con orden taxonómico (alerta suave), vía escaneo QR (especímenes/unit trays) y NFC
  (cajas). Incluye visitante habilitado / no habilitado para reubicar.
- `trazabilidad_movimientos.feature` — pantalla del curador con el historial cronológico de
  movimientos de un espécimen, incluyendo los movimientos de los contenedores (unit tray, caja)
  que lo albergan, con responsable.

Modelo real del dominio: **especimen → unitTray → caja → ranura → gabinete**. (El context Behat
viejo `ReubicacionDigitalGuiadaContext.php` referencia un diseño ABANDONADO — `EspecimenEnCaja`,
`TrasladoEspecimen`, `IniciarReubicacionEspecimen`, `ConsultarHistorialCustodiaEspecimen` — que NO
existe. Hay que **reescribirlo** desde cero.)

Convención de carpetas: usar subfolder `SeguimientoFisico/` en CADA capa
(Domain/SeguimientoFisico, Application/SeguimientoFisico, Infrastructure/SeguimientoFisico,
Presentation/.../SeguimientoFisico).

Decisiones del usuario ya tomadas:
- Escáner QR por cámara → **librería JS `html5-qrcode`** (cubre iOS + Android).
- Generación de QR de unit tray → **`endroid/qr-code` server-side** (imprimible, "siempre el mismo
  QR" = codifica el `UnitTrayId`).
- Búsqueda por cámara del visitante → **stub mínimo provisional**.
- Commits **sin coautoría** (nunca trailer Co-Authored-By ni mención de Claude).

## Estado actual

**Hecho y commiteado:**
- ✅ **F1 Dominio** (commit `feat(iot): dominio de reubicación...`): `ActorRol::Visitante`;
  `Visitante::puedeReubicar()` + `definirReubicacion(bool)` (campo `puedeReubicar`, default false en
  `crear`, nuevo param en `reconstituir`); `UnitTray::moverACaja(CajaId)` (cajaId ahora mutable, ya
  no readonly); `ReubicacionNoAutorizadaException`; `EventoCicloIotRepository::buscarPorAgregado(string $tipoAgregado, string $agregadoId): array`.
- ✅ **F1 Aplicación** (commit `feat(iot): casos de uso de reubicación...`):
  `UseCases/ReubicarEspecimenes/{Handler,Input,Output}` y `UseCases/ReubicarUnitTray/{Handler,Input,Output}`.
  - `ReubicarEspecimenesHandler`: autoriza actor (rechaza visitante sin `puedeReubicar`), advierte
    taxonómicamente (devuelve `requiereConfirmacion=true` sin persistir si `confirmar=false`),
    delega la asignación en `ActualizarEspecimenesUnitTrayHandler`, registra un `EventoCicloIot`
    (`tipoAgregado='especimen'`, `tipoEvento='especimen_reubicado'`) por espécimen.
  - `ReubicarUnitTrayHandler`: autoriza, `moverACaja`, registra `EventoCicloIot`
    (`tipoAgregado='unit_tray'`, `tipoEvento='unit_tray_reubicado'`).

**Cambio en working tree SIN commitear (inclúyelo en la capa F1 Behat):**
- `tests/Behat/Infrastructure/InMemory/InMemoryEventoCicloIotRepository.php` — ya se le agregó
  `buscarPorAgregado()` (filtra por tipoAgregado+agregadoId, ordena por `ocurridoEn` asc).

**⚠️ Rotura conocida hasta que se haga F1 Infra:** `Visitante::reconstituir()` cambió de firma
(ahora exige `bool $puedeReubicar` antes de `$registradoEn`). El llamador
`Infrastructure/.../Eloquent/Repositories/EloquentVisitanteRepository.php` **quedará roto** hasta
que la capa Infra lo actualice. Behat usa repos InMemory, así que NO se ve afectado.

## Checklist (una casilla = una capa = un commit)

- [x] **F1 Infra** (commit `feat(iot): infraestructura de reubicación y trazabilidad`) — listo.
  `buscarPorAgregado` agregado (orden `ocurrido_en` asc). Visitante: nueva migración
  `2026_06_29_000001_add_puede_reubicar_to_visitantes_table.php` + cast `boolean` + fillable + mapeo
  en `EloquentVisitanteRepository` (guardar + `reconstituir`). `endroid/qr-code ^6.1` instalado.
  **OJO: la tabla real es `taxonomia.visitantes`, NO `iot.visitantes` como decía este HANDOFF.**
  Bindings sin cambios (los 3 handlers auto-resuelven). `php -l` ok, `php artisan about` arranca,
  pint pass.
- [x] **F2 Aplicación** (commit `feat(iot): caso de uso de consulta de trazabilidad`) — listo.
  `ConsultarTrazabilidadEspecimen/{Handler,Input,Output}` + `MovimientoTrazabilidadOutput` (item
  DTO `{tipo, origen, destino, ocurridoEn, responsable}`). El handler reúne
  `buscarPorAgregado('especimen',$id)` + unit tray contenedor (`unitTrayDeEspecimen`) + caja de ese
  tray (`unitTrayRepo->buscarPorId(...)->cajaId()`), fusiona, `usort` por `ocurridoEn()` asc.
  **`responsable = actorRol()->valor()`** a secas: `ActorRol::Sistema->valor()` ya es `'sistema'`,
  así que un evento sin actor humano queda `responsable='sistema'` solo. `origen`/`destino` se
  extraen genéricamente del primer key `origen_*`/`destino_*` de `datos()` (sirve para unit_tray,
  caja, ranura sin acoplar a sus nombres). `php -l` ok, pint pass.
- [x] **F1 Behat** (commit `test(iot): behat de reubicación digital guiada`) — listo. Creados
  `FakeClasificacionTaxonomicaAdapter` (método `registrar(taxonId, ClasificacionTaxonomica)`) e
  `InMemoryVisitanteRepository`; `FakeContextoEjecucionAdapter` ahora tiene `setActor(ActorRol, ?string)`
  (default Sistema/null intacto). Reescrito `ReubicacionDigitalGuiadaContext` contra el modelo real
  (patrón `self::$app->instance(...)`+`make`, como `MapaUbicacionGuiaEspecimenesContext`) y registrado
  en `behat.php`. **OJO 1:** la feature mezcla dos redacciones del mismo Given — `Dado que existe un
  espécimen...` (S1/S4) y `Y existe un espécimen...` (S5/S6); Behat NO las unifica, hay dos atributos
  `#[Given]` sobre el mismo método. **OJO 2:** correr Behat con `--no-snippets`; si hay un paso
  undefined, Behat abre un prompt interactivo de snippets que parece un cuelgue (bloquea esperando
  stdin). Gate completo `--tags=@listo --strict` in-memory: 36 escenarios / 135 pasos verdes. Pint pass.
- [x] **F2 Behat** (commit `test(iot): "behat de trazabilidad de movimientos"`) — listo. Creado
  `TrazabilidadMovimientosContext` y registrado en `behat.php`. Siembra eventos directos vía
  `eventoRepo->guardar(EventoCicloIot::registrar(...))` (no corre los handlers de reubicación). El de
  "caja sin actor humano" usa `ActorRol::Sistema`/`actorId=null`. **OJO 1:** la feature venía con la
  cola truncada (un fragmento suelto "los movimientos del unit tray y de la caja..."); se reescribió
  limpia con los **4 escenarios** (2 outlines + 2 simples) y se reordenó scenario 1 a
  `Dado que existe un espécimen <condicion>` con `condicion ∈ {con movimientos previos, sin
  movimientos}` para que las expansiones casen con `#[Given]` literales (los placeholders turnip no
  matchean multi-palabra sin comillas). **OJO 2:** ids de espécimen son strings opacos
  (`especimen-N`), no entidades reales — el handler de consulta no carga `Especimen`. Gate
  `--tags=@listo --strict` in-memory: 43 escenarios / 160 pasos verdes. Pint pass.
- [x] **F1 UI** (commit `feat(iot): "UI de reubicación guiada"`) — listo. En `AsignacionUnitTrayIndex`
  + `unit-trays/index.blade.php`: por fila de tray se añadió **QR** (`mostrarQrTray` → endroid Builder
  v6 `new Builder(data:UnitTrayId,size:320,margin:16)->build()->getDataUri()`, modal imprimible con
  Imprimir/Descargar), **Mover de caja** (`abrirReubicarTray` → modal con NFC `NDEFReader` que llama
  `reubicarTrayPorRfid`→`buscarPorCodigoRfid`, + select manual de caja `reubicarTrayACaja`), **Eliminar**
  (`EliminarUnitTrayHandler` + `wire:confirm`). Botón global **Reubicar especímenes** abre modal con
  escáner **html5-qrcode** (CDN 2.3.8, Alpine `reubicacionScanner` con toggle de modo espécimen/destino,
  guarda anti-ráfaga de 2s); cada escaneo de espécimen se resuelve vía `ListarEspecimenesAsignablesHandler`
  (busqueda=código, **el QR del espécimen codifica su `codigoCatalogo`**, match exacto), popup de
  confirmación, acumula, destino por select o escaneo, llama `ReubicarEspecimenesHandler` y maneja
  `requiereConfirmacion` (callout "Reubicar de todos modos"=confirmar:true). **Toggle `puede_reubicar`**:
  nuevo caso de uso **`DefinirReubicacionVisitante/{Handler,Input,Output}`** (Application — era necesario,
  no existía); `VisitanteResumen` y `ListarVisitantesHandler` ahora exponen `puedeReubicar`;
  `VisitanteAccesoPanel::alternarReubicacion()` + `flux:switch` en `visitantes.blade.php` (desktop+móvil).
  **OJO 1:** `endroid/qr-code ^6.1` estaba en composer.lock pero NO instalado físicamente en `vendor/`;
  hubo que correr `composer install` (PowerShell; en Git Bash el prompt no devolvía salida). **OJO 2:**
  los flujos de cámara/NFC no tienen test automatizado — quedan para validación manual en Herd (paso de
  cierre). `php artisan view:cache` compila OK, gate `@listo --strict` in-memory sigue 43/160 verde, pint pass.
- [x] **F2 UI** (commit `feat(iot): UI de trazabilidad de movimientos`) — listo. Livewire
  `TrazabilidadMovimientosIndex` + vista `admin/trazabilidad/index.blade.php` (buscador acotado vía
  `ListarEspecimenesAsignablesHandler` → selección → `ConsultarTrazabilidadEspecimenHandler` →
  timeline vertical `<ol>` con tipo/origen→destino/fecha/responsable). Ruta
  `inventario.trazabilidad` (`/inventario/trazabilidad`, middleware `role:curador`) + item de
  sidebar (icono `arrows-right-left`). **OJO 1:** `seleccionarEspecimen` recibe SOLO el `id` y
  re-deriva el label desde `$candidatos` — pasar `@js(...)` dentro del `wire:click` (atributo de
  comillas dobles) rompía el HTML. **OJO 2:** el `tipo` del movimiento es el `tipoEvento` crudo;
  se mapea con `ETIQUETA_TIPO` para los conocidos (`especimen_reubicado`, `unit_tray_reubicado`,
  `caja_ingresada/extraida`) y se humaniza el resto. `php -l` ok, `view:cache` compila, gate
  `@listo --strict` in-memory sigue 43/160 verde, pint pass.
- [x] **Stub visitante + cierre** (commit `feat(iot): stub de búsqueda por cámara del visitante`) —
  listo. En `MapaInteractivo` (modo visitante): nuevo método `buscarPorCamara(string $texto)` +
  propiedad `objetivoCamara` (`// ponytail: stub provisional`, reutiliza el QR como puente, no es
  visión real). En `seguimiento-fisico/mapa/interactivo.blade.php`: botón **Buscar por cámara**
  (solo `modo === 'visitante'`) que abre modal Alpine `visitanteCamaraScanner` con el mismo
  html5-qrcode (CDN 2.3.8) de F1; al decodificar vuelca el texto a `busquedaEspecimen`, lista los
  candidatos y **resalta el objetivo** (ring science-blue en el candidato cuyo `codigoCatalogo`/
  `taxonNombre` casa con lo escaneado). `php -l` ok, `view:cache` compila, gate `@listo --strict`
  in-memory sigue 43/160 verde, pint pass. **Pendiente manual:** validar cámara en Herd (móvil real).

## Convenciones (OBLIGATORIAS)

- **NUNCA** ejecutar `migrate:fresh`, `db:fresh` ni nada que reinicie la BD real (Supabase). La
  data es vital.
- **Behat in-memory**: el `.env` apunta al pooler de Supabase; hay que forzar sqlite o se cuelga.
- **Commits sin coautor**: nada de `Co-Authored-By` ni mención de Claude. PowerShell rompe el
  quoting de `git commit -m`; usar `git commit -F -` con heredoc desde la **Bash tool** (Git Bash).
- **Pint** antes de cada commit: `php vendor/bin/pint --dirty --format agent`.
- **UI en sentence case** (no Title Case), tokens de color (no hex), iconos `<flux:icon … />`
  outline, `wire:navigate` en links internos. Ver `CLAUDE.md` y `claude.local.md`.
- **php**: invocar como `php vendor/bin/<tool>` (el shebang `/usr/bin/env php` no resuelve en Git
  Bash, pero `php` directo sí está en PATH).
- **RTK**: prefijar comandos con `rtk` (p. ej. `rtk git add`, `rtk git commit`).

## Comandos

```bash
# Lint
php -l <archivo.php>
php vendor/bin/pint --dirty --format agent

# Behat in-memory (NUNCA contra Postgres). Desde Bash tool:
DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/behat \
  --profile=default --suite=InventarioGestionColeccion --tags=@pendiente   # mientras se desarrolla
DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/behat \
  --profile=default --suite=InventarioGestionColeccion --tags=@listo --strict   # gate final

# Una feature concreta:
DB_CONNECTION=sqlite DB_DATABASE=:memory: php vendor/bin/behat --profile=default \
  --suite=InventarioGestionColeccion Modules/InventarioGestionColeccion/tests/Behat/Features/TrazabilidadOperativaMovimientosCirculacion/reubicacion_digital_guiada.feature
```
> Si en PowerShell, usar `$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; php vendor/bin/behat ...`.

## Mapa de APIs (verificado en código — evita re-explorar)

**Dominio / VOs**
- `EventoCicloIot::registrar(string $tipoAgregado, string $agregadoId, string $tipoEvento, int $versionEvento, array $datos, ?string $actorId, ActorRol $actorRol, \DateTimeImmutable $ocurridoEn): self`
  — getters: `tipoAgregado() agregadoId() tipoEvento() datos() actorId() actorRol() ocurridoEn()`.
- `ActorRol` (enum string): `Sistema='sistema'`, `Esp32='esp32'`, `Curador='curador'`, `Visitante='visitante'`; `->valor()`, `->equals()`.
- `Visitante::crear(VisitanteId,$nombre,?$contacto,\DateTimeImmutable)`; `reconstituir(VisitanteId,$nombre,?$contacto,int $versionAcceso,bool $puedeReubicar,\DateTimeImmutable)`; `definirReubicacion(bool)`; `puedeReubicar():bool`.
- `UnitTray::crear(UnitTrayId,$cajaId,int $numero,?ClasificacionTaxonomica)`; `reconstituir(...)`; `moverACaja(CajaId)`; `id() cajaId() numero() clasificacionDominante() actualizarClasificacion() limpiarClasificacion()`.
- `Caja::crear(id:,codigo:,clasificacionTaxonomica?:,esEspecial?:)`; `esEspecial():bool`; `ingresarEnRanura(RanuraId)`; `pullEvents()`; `id()`.
- `ClasificacionTaxonomica::desde(orden?:,suborden?:,superfamilia?:,familia?:,subfamilia?:,genero?:,especie?:)`; `estaVacia():bool`.
- `Especimen::crear(id:,codigoCatalogo:,taxonId:,localidad:,fechaColecta:,colector:)`; `taxonId():string`; `id()`.
- `Taxon::crear(id:,nombreCientifico:,rango:)`.
- VOs `::desde(string)`: `VisitanteId CajaId UnitTrayId EspecimenId CodigoCaja CodigoGabinete`.

**Repositorios (interfaces Domain)**
- `UnitTrayEspecimenRepository`: `sincronizar(UnitTrayId,$especimenIds[])` (reubica desde el tray
  previo — constraint único por espécimen), `especimenIdsPorUnitTray(UnitTrayId): string[]`,
  `unitTrayDeEspecimen(string): ?UnitTrayId`, `unitTraysDeEspecimenes(string[]): array<string,string>`,
  `eliminarPorUnitTray(UnitTrayId)`.
- `CajaRepository`: `nextIdentity guardar buscarPorId buscarPorCodigo buscarPorCodigoRfid buscarTodas eliminar`.
- `UnitTrayRepository`: `nextIdentity guardar buscarPorId buscarPorCaja`; InMemory además `siguienteNumero(CajaId)`.
- `EspecimenRepositoryInterface`: `buscarPorId(EspecimenId):?Especimen`, `buscarParaAsignacion(?string $busqueda,int $limite,array $incluirSiempre=[]): array`.
- `VisitanteRepositoryInterface`: `nextIdentity guardar buscarPorId(VisitanteId) buscarTodos`.
- `EventoCicloIotRepository`: `guardar`, `buscarUltimoPorAgregadoYTipo($agregadoId,$tipoEvento)`, `buscarPorAgregado($tipoAgregado,$agregadoId): array` (nuevo).

**Aplicación**
- `Ports\ContextoEjecucionPort`: `actorRol(): ActorRol`, `actorId(): ?string`.
- `Ports\ClasificacionTaxonomicaPort`: `resolverParaTaxon(string $taxonId): ?ClasificacionTaxonomica`.
- `Ports\TransactionManagerPort`: `executeTransactional(callable): mixed`.
- `Ports\EventPublisherPort`: `publish(object)`.
- `Support\PropagaClasificacionTaxonomica` (trait): `private resolverClasificacionAgregadaPorEspecimenes(string[] $ids, EspecimenRepositoryInterface, ClasificacionTaxonomicaPort): ?ClasificacionTaxonomica`; `private detectarEspecimenesFueraDeLugar(string[] $ids, ?ClasificacionTaxonomica $dominante, EspecimenRepositoryInterface, ClasificacionTaxonomicaPort): string[]` (devuelve códigos de catálogo).
- `ActualizarEspecimenesUnitTrayHandler::handle(Input(string $unitTrayId, string[] $especimenIds)): Output` — Output expone `especimenesFueraDeLugar`, `tieneClasificacion`, etc. (sincroniza + reclasifica + propaga a caja, todo transaccional). REUTILIZADO por `ReubicarEspecimenesHandler`.

**Behat (patrón verificado)**
- `BaseContext` (abstract): `#[BeforeSuite] bootstrapLaravel()` arranca Laravel una vez en
  `self::$app`; `protected make(string $abstract)` resuelve del container. Bind con
  `self::$app->instance(Interface::class, $impl)` en el constructor del Context, LUEGO `$this->make(Handler::class)`.
- Estado por escenario en propiedades privadas: `$ultimaRespuesta`, `$excepcionCapturada`,
  ids sembrados. `Cuando` ejecuta el handler en try/catch.
- Atributos de paso: `use Behat\Step\{Given,When,Then};` con `#[Given('...')]` etc.
- Repos InMemory existentes en `tests/Behat/Infrastructure/InMemory/`: Caja, Especimen,
  EventoCicloIot, UnitTray, UnitTrayEspecimen, Taxon, Gabinete, Ranura, Ubicacion, Alerta,
  Notificacion, Horario, etc. **Falta crear: InMemoryVisitanteRepository.**
- Fakes en `tests/Behat/Infrastructure/Fakes/`: ContextoEjecucion, EventPublisher, Horario,
  GeneradorActaPdf, PassThroughTransactionManager. **Falta crear: FakeClasificacionTaxonomicaAdapter.**
- **Receta de siembra** (de `MapaUbicacionGuiaEspecimenesContext`): crear Taxon → Especimen con
  `taxonId` → Caja → UnitTray (`unitTrayRepo->siguienteNumero(cajaId)`) →
  `asignacionRepo->sincronizar(unitTray->id(), [especimenIds])`. Para que el espécimen tenga
  clasificación vía el port, registrar en el `FakeClasificacionTaxonomicaAdapter`:
  `taxonId => ClasificacionTaxonomica::desde(...)`.
- `behat.php` (raíz): suite `InventarioGestionColeccion` → `withPaths(...Features)` →
  `withContexts([... lista de FQCN ...])`. Agregar el FQCN del nuevo Context a esa lista.

## UI — archivos existentes a tocar/reutilizar
- `Presentation/Http/Controllers/SeguimientoFisico/Admin/AsignacionUnitTrayIndex.php` + `resources/views/admin/unit-trays/index.blade.php` (crea/selecciona/asigna unit trays; patrón inyectar Handler→Input DTO→render Output ≤10 líneas; `traducirErrorParaUsuario`).
- `Presentation/.../Admin/CajaIndex.php` + `resources/views/admin/cajas/index.blade.php` — **bloque Alpine `NDEFReader`** (Web NFC) reutilizable para escanear caja destino; resolver por `buscarPorCodigoRfid`.
- `Presentation/.../Admin/VisitanteAccesoPanel.php` + `resources/views/admin/visitantes.blade.php` (QR de acceso de visitante; aquí va el toggle `puede_reubicar`).
- `EliminarUnitTrayHandler` (ya existe en Application; sin UI todavía).
- Componente `resources/views/components/seguimiento-fisico/campo-movil.blade.php` (par etiqueta/valor móvil).
- `routes/web.php` del módulo (rutas Livewire bajo `/admin/inventario/...`, middleware `role:curador`).

## Gotchas de entorno
- **Compresión de lecturas (headroom proxy):** las salidas de `Read` a veces vuelven solo como
  números de línea con `[N items compressed... hash=XXXX]`. Workarounds: leer en chunks chicos
  (≤~30 líneas suele venir limpio), o `mcp__headroom__headroom_retrieve` con el hash **de inmediato**
  (los hashes EXPIRAN en ~1–2 min). No te frustres; es del entorno, no del archivo.
- El plan original completo está en `C:\Users\acarl\.claude\plans\hagamos-un-plan-para-starry-walrus.md`.
