#!/usr/bin/env bash
set -euo pipefail

# Revierte una cuarentena antes de que exista un worker activo. Conserva la
# auditoría y sólo devuelve trabajos que aún pertenecen a esa cuarentena.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la restauracion de trabajos con sudo.' >&2
    exit 64
fi

release_id="${1:-}"
[[ "${release_id}" =~ ^[0-9a-f]{16}$ ]] || {
    echo 'Uso: sudo restore-quarantined-jobs.sh <id-de-16-hex>' >&2
    exit 64
}

release_dir="/srv/hubdigital/releases/${release_id}"
env_file=/etc/hubdigital/hubdigital.env
[[ -f "${release_dir}/artisan" && -r "${env_file}" ]] || { echo 'Falta release o entorno protegido.' >&2; exit 66; }
"${release_dir}/deploy/oracle/scripts/verify-release-state.sh" "${release_id}" validado_en_mantenimiento
for unit in hubdigital-worker.service hubdigital-r2-signatures.service hubdigital-schedule.service hubdigital-schedule.timer cloudflared-hubdigital.service; do
    systemctl is-active --quiet "${unit}" && { echo "Debe detenerse antes de restaurar cola: ${unit}" >&2; exit 65; }
done

read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
db_name="$(read_env DB_DATABASE)"
db_user="$(read_env DB_USERNAME)"
db_password="$(read_env DB_PASSWORD)"
[[ "${db_name}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,44}$ && "${db_user}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ && -n "${db_password}" ]] || {
    echo 'Configuracion PostgreSQL invalida.' >&2
    exit 65
}
quarantine_queue="quarantine.${release_id}"

pgpass="$(mktemp /run/hubdigital/pgpass.restore-queue.XXXXXX)"
chmod 0600 "${pgpass}"
trap 'rm -f -- "${pgpass}"' EXIT
escaped_password="$(printf '%s' "${db_password}" | sed -e 's/\\/\\\\/g' -e 's/:/\\:/g')"
printf '127.0.0.1:5432:%s:%s:%s\n' "${db_name}" "${db_user}" "${escaped_password}" > "${pgpass}"
export PGPASSFILE="${pgpass}"

sql="BEGIN;
LOCK TABLE jobs IN SHARE ROW EXCLUSIVE MODE;
WITH restored AS (
    UPDATE jobs AS job
    SET queue = control.original_queue
    FROM hubdigital_control.quarantined_jobs AS control
    WHERE control.release_id = :'release_id'
      AND control.restored_at IS NULL
      AND control.quarantine_queue = :'quarantine_queue'
      AND job.id = control.job_id
      AND job.queue = control.quarantine_queue
    RETURNING job.id
), marked AS (
    UPDATE hubdigital_control.quarantined_jobs
    SET restored_at = now()
    WHERE release_id = :'release_id'
      AND job_id IN (SELECT id FROM restored)
    RETURNING job_id
)
SELECT 'jobs_restaurados=' || count(*) FROM marked;
COMMIT;"
printf '%s\n' "${sql}" | psql --no-psqlrc --set=ON_ERROR_STOP=1 --tuples-only --no-align --quiet --host=127.0.0.1 --username="${db_user}" --dbname="${db_name}" \
    --set="release_id=${release_id}" --set="quarantine_queue=${quarantine_queue}"
echo 'La cuarentena se revirtio sin borrar registros de auditoria.'
