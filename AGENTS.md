# Acuerdo de validacion de HubDigital

- El usuario ejecuta `crear-paquete-oci`. Ese comando realiza las pruebas, compilaciones y validaciones integrales del proyecto. No lo ejecutes salvo que el usuario te lo pida expresamente.
- Despues de cada cambio de codigo, no ejecutes por iniciativa propia pruebas PHP/Pest/PostgreSQL, pruebas Java/Maven, compilaciones Vite/npm ni otras comprobaciones automatizadas ya incluidas en `crear-paquete-oci`, aunque se cierre VS Code o se retome el trabajo en otra sesion. No repitas esas pruebas antes ni despues del paquete.
- Revisa el cambio de forma estatica y comunica que la validacion automatizada queda a cargo de `crear-paquete-oci`. Si el usuario pide una comprobacion concreta o el paquete informa un fallo, investiga solo ese caso de forma puntual, sin repetir la suite integral.
