# ChatBot RAG — Plan de implementación

Feature: `Modules/CatalogoPublico/tests/Behat/Features/ChatBot/consulta_en_lenguaje_natural.feature`
Rama: `feature/divulgacion/implementacion-RAG`

## Decisiones arquitectónicas cerradas

1. **Estrategia RAG**: híbrida sin vectorización.
   - Groq no ofrece embeddings; los datos son tabulares y estructurados.
   - Prompt 1 (clasificador): guardrail + extracción de entidades en JSON.
   - Prompt 2 (generador): recibe contexto tabular ya filtrado.
   - `pgvector` queda como puerta futura si aparecen notas de campo largas.

2. **Mensaje guardrail**: constante PHP `ChatBotMensajes::FUERA_DE_DOMINIO`.
   - Fuente única de verdad; determinismo en tests.

3. **Testing Behat**: fakes registrados con `app()->instance()` en `@BeforeScenario`.
   - Cero llamadas a Groq en CI.
   - El fake del generador **captura** el `ContextoLLM` para asertar sobre él.

4. **Módulo autorizado**: `CatalogoPublico` es el único con `laravel/ai` (CLAUDE.md §1).

## Contratos (Application)

### Ports
- `ClasificadorIntencionPort::clasificar(string $pregunta): IntencionConsulta`
- `GeneradorRespuestaChatBotPort::generar(ContextoLLM $contexto): string`

### VOs
- `IntencionConsulta { dentroDeDominio: bool, entidades: EntidadesExtraidas }`
- `EntidadesExtraidas { genero?, especie?, occurrenceID?, localidad?, country? }`
- `ContextoLLM { pregunta: string, especimenes: list<DatosEspecimenParaContexto> }`
- `DatosEspecimenParaContexto` (DTO tabular ya filtrado por visibilidad)

### Servicio de dominio
- `RecuperadorContextoEspecimenes` (puro, sin Laravel/Carbon)
  - Traduce `EntidadesExtraidas` → llamadas a repos/ports existentes.
  - Aplica filtro de `ConfiguracionVisibilidad` (misma lógica que `ConsultarInformacionDivulgadaHandler::filtrarPorVisibilidad`).

### UseCase
- `Application/UseCases/ConsultarChatBot/`
  - `ConsultarChatBotInput { pregunta: string }`
  - `ConsultarChatBotOutput { respuesta: string, dentroDeDominio: bool, especimenesReferenciados: list<string> }`
  - `ConsultarChatBotHandler` — orquesta clasificador → recuperador → generador.

## Contratos (Infrastructure)

- `Infrastructure/Adapters/ChatBotAgent`
  - Clon de `Modules/GestionPrestamosRecepciones/app/Infrastructure/Adapters/ExtractorDocumentoAgent.php`.
  - `#[Temperature(0.2)]` para respuestas más deterministas.
  - Implementa `Agent`, `Conversational`; usa `Promptable`.
- `Infrastructure/Adapters/GroqClasificadorIntencionAdapter` — implementa `ClasificadorIntencionPort`. JSON structured output.
- `Infrastructure/Adapters/GroqGeneradorRespuestaChatBotAdapter` — implementa `GeneradorRespuestaChatBotPort`. Contexto tabular → respuesta natural.

## Bindings

En `Modules/CatalogoPublico/app/Infrastructure/Providers/ServiceProvider.php`, array `$bindings` (NO en `AppServiceProvider`):

```php
ClasificadorIntencionPort::class => GroqClasificadorIntencionAdapter::class,
GeneradorRespuestaChatBotPort::class => GroqGeneradorRespuestaChatBotAdapter::class,
```

## Convenciones ya aplicadas al `.feature`

- Sin `@listo` hasta que los 3 escenarios pasen en verde estricto.
- Actores explícitos en títulos ("El visitante recupera…", "El visitante consulta…", "El visitante recibe rechazo…").
- Un solo `Cuando` por escenario.
- Aserciones sobre el `ContextoLLM`, NO sobre el texto libre generado por el LLM.
- Guardrail asertado contra el `ClasificadorIntencionPort`, no contra el side-effect textual.

## Checklist de tareas

### 1. Verificar entorno Groq y datos locales
- [x] `GROQ_API_KEY` disponible (confirmado por el usuario).
- [ ] Prueba de humo: `Ai::provider('groq')->prompt('ping')` desde `php artisan tinker`.
- [ ] `GROQ_MODEL` seteado (default `llama-3.3-70b-versatile` en `config/ai.php:11`).
- [ ] Existen `EspecimenDivulgable` sincronizados en la BD local para pruebas manuales.

### 2. Pest/Unit — VOs y servicio de dominio
- [ ] Test `EntidadesExtraidas` (constructor, factory, validación).
- [ ] Test `IntencionConsulta` (dentro/fuera de dominio).
- [ ] Test `RecuperadorContextoEspecimenes` con `EspecimenDivulgableRepository` y `ProveedorEspecimenesPort` en memoria.
- [ ] Verificar filtro de visibilidad: campos no visibles NO aparecen en `DatosEspecimenParaContexto`.

### 3. Scaffold Behat
- [ ] `php artisan behat:scaffold CatalogoPublico ChatBot consulta_en_lenguaje_natural`
- [ ] Verificar que se crea `tests/Behat/Contexts/ChatBot/ChatBotContext.php`.

### 4. Esqueleto: Ports, UseCase, VOs y constante
- [ ] `Application/Ports/ClasificadorIntencionPort.php`
- [ ] `Application/Ports/GeneradorRespuestaChatBotPort.php`
- [ ] `Application/UseCases/ConsultarChatBot/{Input,Output,Handler}.php`
- [ ] `Domain/ValueObjects/{IntencionConsulta,EntidadesExtraidas,ContextoLLM,DatosEspecimenParaContexto}.php`
- [ ] `Domain/Services/RecuperadorContextoEspecimenes.php`
- [ ] `Domain/ValueObjects/ChatBotMensajes.php` con `FUERA_DE_DOMINIO`.
- [ ] Registrar bindings en `Infrastructure/Providers/ServiceProvider.php`.

### 5. Implementar pasos del `ChatBotContext`
- [ ] `@BeforeScenario`: instanciar fakes y `app()->instance(Port::class, $fake)`.
- [ ] Fake `ClasificadorIntencionPort`: mapa pregunta→intención predefinido.
- [ ] Fake `GeneradorRespuestaChatBotPort`: captura `ContextoLLM` en `$this->ultimoContexto`.
- [ ] Step "Dado que existen los siguientes especímenes ya divulgados" — usa el `EspecimenDivulgableRepositoryInterface`, nunca Eloquent directo.
- [ ] Step "Cuando el motor de búsqueda…" — ejecuta `ConsultarChatBotHandler->handle()`.
- [ ] Steps "Entonces el contexto del LLM incluye…" — asserts sobre `$this->ultimoContexto`.
- [ ] Steps del guardrail — asserts sobre `Output.dentroDeDominio` y `Output.respuesta === ChatBotMensajes::FUERA_DE_DOMINIO`.

### 6. Behat verde
- [ ] `vendor/bin/behat --suite=catalogopublico`
- [ ] Los 3 escenarios en verde sin pending/undefined.

### 7. Completar lógica del Handler
- [ ] Manejar retrieval vacío (0 especímenes) sin romper el flujo.
- [ ] Poblar `Output.especimenesReferenciados` con los `occurrenceID` incluidos en el contexto.
- [ ] Invariantes: pregunta no vacía, intención resuelta.

### 8. Pest/Integration — retrieval contra DB real
- [ ] Test con repos Eloquent reales: `buscarPorNombreCientifico`, `buscarPorOccurrenceIDs`.
- [ ] Verificar filtro de visibilidad con datos reales.

### 9. Adapters Groq reales
- [ ] `ChatBotAgent` (clon de `ExtractorDocumentoAgent`).
- [ ] `GroqClasificadorIntencionAdapter` con JSON schema estricto.
- [ ] `GroqGeneradorRespuestaChatBotAdapter` con prompt tabular.
- [ ] Manejo de `ConnectionException` y respuestas malformadas (patrón de `GroqExtraccionDatosDocumentoAdapter`).

### 10. Livewire de presentación
- [ ] Componente Livewire del chatbot (≤10 líneas por acción).
- [ ] Vista en `Modules/CatalogoPublico/resources/views/`.
- [ ] Ruta en `Modules/CatalogoPublico/routes/web.php`.
- [ ] Cumplir Flux UI + tokens de color (CLAUDE.md §3): sin hex, iconos outline, `rounded-lg`, `p-4 sm:p-6`.

### 11. Añadir `@listo`
- [ ] Añadir tag `@listo` encima de `Característica:` en el `.feature`.
- [ ] `vendor/bin/behat --profile=default --tags=@listo --strict` en verde.
- [ ] `vendor/bin/pint --dirty --format agent` antes de commit.

## Referencias

- Patrón de agente Groq: `Modules/GestionPrestamosRecepciones/app/Infrastructure/Adapters/ExtractorDocumentoAgent.php`
- Adapter Groq completo (parsing JSON, retry, validación): `Modules/GestionPrestamosRecepciones/app/Infrastructure/Adapters/GroqExtraccionDatosDocumentoAdapter.php`
- Filtro de visibilidad reutilizable: `Modules/CatalogoPublico/app/Application/UseCases/ConsultarInformacionDivulgada/ConsultarInformacionDivulgadaHandler.php::filtrarPorVisibilidad`
- Config compartida: `config/ai.php`
- Repositorios existentes:
  - `Modules/CatalogoPublico/app/Domain/Repositories/EspecimenDivulgableRepositoryInterface.php`
  - `Modules/CatalogoPublico/app/Application/Ports/ProveedorEspecimenesPort.php`
