#!/usr/bin/env bash
set -euo pipefail

# Sincroniza las unidades de worker, scheduler y el renderizador ETE. No
# reinicia servicios, no toca PostgreSQL ni modifica la configuración de base.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la actualizacion de unidades con sudo.' >&2
    exit 64
fi

app_dir="${1:-}"
[[ -n "${app_dir}" && -f "${app_dir}/artisan" ]] || {
    echo 'Uso: sudo update-service-units.sh /srv/hubdigital/releases/<id>' >&2
    exit 64
}
for unit in hubdigital-worker.service hubdigital-r2-signatures.service hubdigital-schedule.service hubdigital-schedule.timer hubdigital-ete.service hubdigital-signature.service; do
    [[ -f "${app_dir}/deploy/oracle/systemd/${unit}" ]] || { echo "Falta unidad ${unit}." >&2; exit 66; }
    install -m 0644 "${app_dir}/deploy/oracle/systemd/${unit}" "/etc/systemd/system/${unit}"
done
systemctl daemon-reload
systemctl disable hubdigital-schedule.timer >/dev/null 2>&1 || true
echo 'Unidades worker/scheduler/ETE/firma actualizadas; scheduler queda deshabilitado.'
