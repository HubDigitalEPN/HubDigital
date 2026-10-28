# Validación del agente y generación del paquete OCI

Desde el 28 de octubre de 2026 se conserva toda la cobertura y se distribuye
su ejecución en dos momentos. El reparto se mide por familias de control:
8 de 20 corresponden al agente (40 %) y 12 al paquete (60 %). No significa
retirar casos ni garantiza ese mismo porcentaje de minutos. El contrato es
`scripts/deployment/plan-validacion.json`.

## Obligación del agente en cada cambio

Al terminar todos los ajustes de una solicitud, ejecutar en localhost:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/deployment/validar-cambios-agente.ps1
```

Las directrices del proyecto autorizan este bloque local y sus reintentos sin
una petición adicional. Prepara dependencias y artefactos, ejecuta sus suites
completas y genera evidencia solo tras aprobar todo. No requiere token y termina
antes de registrar commits, publicar o crear paquetes. Si se trabaja sin agente, el usuario puede
ejecutar esta misma entrada antes del paquete.

| Agente: 40 % | Paquete: 60 % |
| --- | --- |
| Instalación PHP desde el lock | Diff y conflictos |
| Compilación Maven y copia del JAR | JSON modificados |
| npm ci en raíz y módulos | Contrato composer.json/lock |
| Builds Vite en raíz y módulos | Auditoría PHP actualizada |
| Preparación del runtime ETE | Contratos Python ETE |
| Todos los contratos JavaScript Node | Autoprueba criptográfica Java |
| Arranque de Laravel | Auditoría npm de los cuatro proyectos |
| Pest/PostgreSQL, PDF local y navegador Neon/Livewire | Bash y asistentes DEV/PROD generados |
| | Sintaxis PHP modificados |
| | Clases de migración |
| | Gherkin completo `@listo --strict` |
| | Runtime requerido, evidencia, identidad, publicación y archivos OCI |

Se conservan casos, aserciones y exigencias. PostgreSQL sigue usando bases y
almacenamiento aislados. La medición de firma permanece en localhost con límite
de tres segundos. La revisión visual complementaria usa Opera Neon QA al 75 %.

## Evidencia y cambios posteriores

`.local/validation/evidencia-agente.json` acredita las ocho familias e identifica
el repositorio, HEAD, todas las fuentes rastreadas y nuevas no ignoradas, todos
los archivos de vendor, el JAR y los cuatro directorios de builds.

El paquete rechaza evidencia inexistente, incompleta, malformada, de otro
repositorio/HEAD, de más de 24 horas o con fuentes o runtime diferentes. Comprueba
otra vez la evidencia al terminar su 60 %, antes de publicar. Un nuevo intento
del agente retira la evidencia anterior antes de empezar: un fallo no deja
acreditada una ejecución anterior. No se admite editar el recibo.

Las entradas se identifican antes de las suites y se comparan al terminar.
Un estado pendiente no acredita aprobación ni puede ser consumido por el paquete.

Cualquier ajuste posterior exige repetir el bloque completo del agente. No se
ejecutan pruebas sueltas ni se marcan manualmente como aprobadas.

## Uso del paquete

La orden de publicación sigue siendo la misma y exige autorización expresa:

```powershell
crear-paquete-oci -DescripcionCambio "descripcion del cambio"
```

Para ejecutar el 60 % pendiente sin publicar ni empaquetar:

```powershell
crear-paquete-oci -SoloValidacionLocal -DescripcionCambio "revision local"
```

Ambas exigen primero la evidencia vigente del agente. No repiten instalaciones,
compilaciones, Node ni la integración PostgreSQL/PDF/navegador acreditados. Las
auditorías se consultan al empaquetar para no usar datos antiguos de seguridad.
Gherkin sigue bloqueando publicación ante fallos o pasos pendientes/sin definir.

## Archivos OCI y tiempos

Se conserva `artifacts/oci/<descripcion>-<fecha>` y los mismos archivos: bundle
`.tar.gz`, `.sha256`, kit de subida Cloud Shell, asistentes DEV/PROD e instrucciones.
El entorno sigue dentro del kit; la evidencia local no se incorpora al paquete.
Continúan los manifiestos, la identidad del commit publicado en `origin/main`,
el vendor de producción aislado y la construcción nativa Linux en las VM.
Crear un paquete no autoriza desplegarlo.

Cada comando muestra su duración. El progreso distingue la copia temporal sin
compresión del bundle comprimido. El traslado evita repetir trabajo costoso al
empaquetar; las operaciones de archivos siguen siendo necesarias.

Esta política sustituye las referencias históricas a ejecutar todas las suites
exclusivamente dentro de `crear-paquete-oci`.
