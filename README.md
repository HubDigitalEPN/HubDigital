# HubDigital

**Plataforma del Laboratorio de Invertebrados de la Escuela Politécnica Nacional.**

HubDigital conecta la consulta pública de la colección biológica con el trabajo
de curaduría y la gestión de depósitos y préstamos del Museo de Historia Natural
Gustavo Orcés V. Reúne información taxonómica, fotografías, distribución geográfica
y documentación de los materiales que custodia el laboratorio.

[Portal público](https://labinvepn.org) · [Entorno de desarrollo](https://dev.labinvepn.org)

## Funcionalidades

| Área | Funcionalidades |
| --- | --- |
| Colección pública | Consulta de especímenes y taxones, fotografías, mapas y filtros de la colección divulgada. |
| Exploración y análisis | Exploradores taxonómicos, visualización 3D, gráficos e indicadores de diversidad. |
| Curaduría | Administración de colecciones, registros taxonómicos, localidades, imágenes, importaciones y publicación de información. |
| Depósitos | Solicitudes de ingreso, revisión documental y de matrices, recepción de muestras y seguimiento del trámite. |
| Préstamos | Solicitudes, actas, seguimiento, prórrogas y devolución de materiales. |
| Administración | Usuarios, perfiles, roles, catálogos y configuración del sistema. |

El portal divulga la información autorizada por curaduría. Las cuentas, los
expedientes y las operaciones internas utilizan controles de acceso por rol.

## Tecnología y organización

La aplicación utiliza **Laravel 13, Livewire 4, Blade y Tailwind CSS**, con
**PostgreSQL** como base de datos. Vite compila los recursos del frontend.
El motor documental Java utiliza PDFBox y Bouncy Castle para la firma y
verificación criptográfica de PDF. Python y ETE complementan la visualización
taxonómica. Leaflet, Three.js y las librerías de gráficos sustentan las vistas
interactivas del catálogo.

| Ruta | Contenido |
| --- | --- |
| `app/` | Autenticación, administración y servicios comunes de la aplicación. |
| `Modules/CatalogoPublico/` | Catálogo y consulta pública de la colección. |
| `Modules/InventarioGestionColeccion/` | Inventario, taxonomía y herramientas de curaduría. |
| `Modules/GestionPrestamosRecepciones/` | Préstamos, depósitos y recepción de materiales. |
| `resources/` | Vistas, recursos del frontend, motor Python y recursos documentales. |
| `tools/pdf-signature/` | Código fuente y compilación del motor criptográfico Java. |
| `tests/` | Pruebas PHP, JavaScript, Python y recorridos de navegador. |
| `scripts/` | Herramientas de desarrollo y validación manual. |
| `deploy/oracle/` | Preparación, instalación y activación de releases en OCI. |

## Entorno de desarrollo

El entorno local requiere:

- PHP **8.4.1 o superior**, compatible con `composer.lock`, y extensiones
  PostgreSQL, cURL, GD, Intl, Mbstring, OpenSSL, XML, ZIP y BCMath.
- Composer 2 y PostgreSQL 16.
- Node.js según [`.nvmrc`](.nvmrc) y npm.
- JDK 17 o superior y Maven para compilar el motor PDF.
- Python y las dependencias de [ETE](resources/python/portal_ete/requirements.txt).
- Git, PowerShell y `tar` para el flujo de validación y empaquetado de Windows.
- Opera Neon con un perfil dedicado de QA para las pruebas de navegador.

La configuración parte de [`.env.example`](.env.example). Deben configurarse la
base de datos, `APP_KEY`, la URL del entorno y los servicios que se vayan a usar:
Turnstile, correo, almacenamiento R2 y motores documentales. Las credenciales,
claves privadas y certificados se suministran fuera del repositorio.

En un equipo Windows con las dependencias, el esquema de base de datos y el
entorno ETE ya preparados, el lanzador local inicia PHP, Vite y ETE:

```powershell
.\scripts\development\localhost.ps1 -Accion iniciar
```

La aplicación local se sirve en `http://localhost:8000`. Para consultar su estado,
utiliza `.\scripts\development\localhost.ps1 -Accion estado`; para detenerla,
`.\scripts\development\localhost.ps1 -Accion detener`. La base puede indicarse
mediante `-BaseDatos` y `-UsuarioBD`; su contraseña se proporciona con
`DB_PASSWORD` o con el secreto local protegido que utiliza el lanzador. El motor
de firmas dispone de su propio [lanzador local](scripts/development/start-signature-worker.ps1).

También se incluyen [Docker Compose](docker-compose.yml), el [Dockerfile](Dockerfile)
y una configuración de [Dev Container](.devcontainer/devcontainer.json). Revisa la
configuración de cada entorno antes de utilizarla con bases o servicios reales.

## Pruebas y publicación: flujo manual

**Este repositorio no utiliza GitHub Actions.** Las validaciones, la publicación
y el despliegue se inician manualmente; un push o un pull request no lanza suites
ni instala una versión en los servidores.

Las pruebas automáticas se conservan en el [reparto agente/paquete](docs/validacion-agente-y-paquete.md).
El agente ejecuta al finalizar cada cambio 8 de 20 familias (40 %): dependencias,
compilaciones, Node y la integración completa Pest/PostgreSQL/PDF/navegador.
`crear-paquete-oci` comprueba su evidencia vigente y ejecuta las otras 12 (60 %),
incluidas auditorías, Python, Java criptográfico, Gherkin e integridad OCI.
Se requieren los insumos locales autorizados, incluidos `.local/secrets`.

Para validar exclusivamente en localhost, desde la raíz del repositorio:

```powershell
.\scripts\deployment\validar-cambios-agente.ps1
.\scripts\deployment\crear-paquete-oci-core.ps1 -SoloValidacionLocal -DescripcionCambio "Validación local"
```

El primer paso corresponde al agente y no necesita autorización adicional.
El segundo verifica su evidencia y ejecuta el 60 % restante; lo inicia el usuario
por defecto. No publican ni empaquetan y no requieren token GitHub.

Cada cambio se desarrolla en una rama nueva basada en `main`. Para validar,
publicar en el repositorio oficial y generar el paquete OCI:

```powershell
.\scripts\deployment\crear-paquete-oci-core.ps1 -DescripcionCambio "Descripción del cambio"
```

El comando solicita de forma oculta una credencial con acceso al repositorio
oficial. Solo después de superar todas las validaciones registra el cambio,
actualiza `origin/main` sin reescribir el historial y genera el paquete desde ese
mismo commit. Behat ejecuta el perfil `default`, la etiqueta `@listo` y el modo
`--strict`; cualquier fallo bloquea la publicación.

Consulta el [acuerdo de trabajo](AGENTS.md) y la
[distribución de cobertura](docs/pruebas-cobertura.md) para el procedimiento completo.

## Despliegue manual en OCI

Los paquetes se generan en `artifacts/oci/`. Cada ejecución entrega el archivo
fuente, sus huellas, un kit de transferencia para Cloud Shell y las instrucciones
correspondientes. `SOURCE-METADATA.json` identifica el commit de `main` y
`SOURCE-MANIFEST.sha256` permite verificar la integridad de sus fuentes.

La instalación en DEV o producción es un paso manual posterior. Los
[scripts de despliegue](deploy/oracle/scripts) preparan la release, comprueban su
identidad y requisitos y aplican las migraciones antes de activarla. La
configuración y los secretos de cada VM se administran fuera del código fuente.

## Documentación

- [Cobertura de pruebas y responsabilidades de cada suite](docs/pruebas-cobertura.md).
- [Explorador taxonómico del portal](docs/portal-explorador-taxonomico.md).
- [Dendrograma y navegación taxonómica](docs/dendrograma-taxonomico-portal.md).
- [Colección principal y curaduría](docs/coleccion-principal-curacion-2026-09-29.md).
