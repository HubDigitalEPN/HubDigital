# DESA en la VM de 1 GiB

La VM `labinvepn-dev-vnic` utiliza un perfil operativo propio, instalado con:

```sh
sudo python3 deploy/oracle/scripts/configure-dev-memory.py --apply
```

El instalador exige root, el dominio exacto de DESA, su hostname y una capacidad
de aproximadamente 1 GiB. Rechaza PROD antes de escribir. Conserva un respaldo
de las configuraciones sustituidas en `/var/backups/hubdigital/dev-memory-*`.
Valida las configuraciones antes de detener consumidores y restaura las
anteriores si falla la aplicación. No cambia el commit ni los archivos de la release.

Los dos consumidores conservan sus conexiones, colas y trabajos. Se elimina
OPcache solamente de PHP CLI: cada proceso mantenía una caché independiente.
PHP-FPM conserva su OPcache y atiende una solicitud a la vez, con procesos bajo
demanda. Las solicitudes adicionales esperan. PostgreSQL conserva consultas,
tablas y conexiones; reduce su caché de 96 a 48 MiB y limita a 16 MiB la memoria
de mantenimiento y autovacuum. No se reducen límites de documentos, cargas ni
la memoria Java. Firma, ETE y transporte R2 permanecen disponibles.

Las unidades de modelo local y renovación de IA no arrancan en este hostname.
El chatbot de DESA permanece deshabilitado. PROD conserva su configuración completa.

Los archivos `zz-hubdigital-dev-memory.*`, el override PostgreSQL `99-*` y los
drop-ins de systemd sobreviven a reinicios y despliegues ordinarios. En una VM
reconstruida, `configure-postgres.sh` identifica el dominio DESA e invoca
automáticamente `configure-dev-memory.py --prepare` antes de reiniciar y
comprobar PostgreSQL. Este modo exige las mismas guardas de identidad y capacidad,
valida FPM y PostgreSQL y prepara sus archivos sin necesitar una release ni
consumidores activos. Comprueba 48 MiB en DESA y conserva 96 MiB en PROD.
Repetir la configuración inicial conserva el perfil; no requiere retirar overrides
ni reaplicarlos manualmente. El modo `--apply` sigue disponible para una VM activa.

La reducción de concurrencia es intencional en DESA; no elimina funciones. No
garantiza cualquier carga simultánea dentro de 1 GiB. La aceptación debe medir
MemAvailable, eventos OOM, colas, arranque, acceso web y salud del motor de firma;
una lectura en reposo no acredita estabilidad bajo toda carga. No habilitar swap
ni retirar los espacios temporales privados para ahorrar RAM.
