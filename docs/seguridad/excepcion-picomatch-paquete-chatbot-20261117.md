# Excepción para un único paquete del chatbot — 17 de noviembre de 2026

El usuario autorizó expresamente publicar el cambio en origin/main y aceptar
GHSA-fc5q-39xc-vjpf (CVE-2026-112366) únicamente para este paquete OCI.
La dependencia picomatch sigue vulnerable; esta aceptación no constituye un parche.

La dependencia procede de herramientas de compilación Vite/Tailwind. El generador
excluye node_modules y comprueba su ausencia; OCI recibe los builds ya compilados.
La revisión delimita el empaquetado local, sin afirmar una inspección de las VM.

Se conserva el resultado íntegro de las cuatro auditorías npm. Únicamente se acepta
el aviso indicado y sus dependencias transitivas conocidas, con los mismos locks.
Cualquier otro aviso, error de red, informe incompleto, cambio de fuentes/locks o
inclusión de node_modules mantiene el bloqueo. No se bajan versiones ni se usan forks.

La aceptación se aplica mediante un adaptador local de un solo uso durante esta
ejecución. El generador rastreado y sus auditorías estrictas permanecen intactos;
la excepción no se instala en las VM ni se habilita para paquetes futuros.
Un informe junto al paquete identifica el commit, la huella del archivo OCI y los
cuatro resultados originales. Los controles funcionales aprobados se conservan.

Locks autorizados (SHA-256):

- Raíz: 610a96b8e6964ba10d4937b26c436e373d61335105cca278f3917e05b6b57be0
- CatalogoPublico: 7aa70c8b9a6a31add4d97d010a6b905c751b1a6be09752af68489631e2aeb4ce
- GestionPrestamosRecepciones: ead9cf870c8d03a154a8470c7fba3361d806d24ea1600ecd6638e25864a4d8f2
- InventarioGestionColeccion: 203dbdbfc1764b9a084fe17ce8f76f2196211e66a094a3d62f58121608446e34

Aviso oficial: https://github.com/advisories/GHSA-fc5q-39xc-vjpf
