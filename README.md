# HubDigital

El trabajo actual esta en [main](https://github.com/HubDigitalEPN/HubDigital/tree/main). El historial y las ramas anteriores se conservan.

La aplicacion web usa Laravel, PHP y Blade. La firma y verificacion criptografica de PDF se realizan exclusivamente en Java 17, con PDFBox y Bouncy Castle. El codigo Java esta en [tools/pdf-signature](tools/pdf-signature/src/main/java/org/hubdigital/signatures); el paquete compila y transporta el JAR. Que PHP sea el lenguaje mayoritario del repositorio no significa que las firmas se calculen en PHP.

Cada cambio se desarrolla en una rama nueva desde main. El usuario ejecuta `crear-paquete-oci` para validar PHP/PostgreSQL, compilar Java y Vite, publicar el cambio en main sin push forzado y generar el paquete del mismo commit. Un fallo impide publicar el cambio. Consulta [AGENTS.md](AGENTS.md).

El archivo indicado al final del comando se sube a OCI. `SOURCE-METADATA.json` registra el commit de main, la huella del JAR y la del manifiesto Vite; la preparacion Linux verifica esos datos. Una nueva publicacion en Git no actualiza por si sola la VM: OCI usa el commit del paquete que se haya desplegado.

La migracion de localidades crea el catalogo provincial y conserva los datos historicos. El despliegue aplica las migraciones antes de activar la release. La provincia la selecciona el usuario; del PDF se descubre el tipo de documento, y cada archivo tiene su comprobacion independiente.

El seguimiento de cajas mediante lectores RFID y Arduino se ha retirado. Se conservan las tablas históricas y el catálogo científico; la retirada no requiere borrar datos. Las pruebas del sistema se mantienen en Git y se excluyen del paquete de producción.
