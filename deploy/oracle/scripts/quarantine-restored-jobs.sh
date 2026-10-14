#!/usr/bin/env bash
set -euo pipefail

# Mueve trabajos restaurados a una cola reversible antes de la validación. No
# toca payloads ni borra filas: conserva la cola original en una tabla de
# control limitada a IDs, clase y marcas de tiempo.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la cuarentena de trabajos con sudo.' >&2
    exit 64
fi

release_id="${1:-}"
[[ "${release_id}" =~ ^[0-9a-f]{16}$ ]] || {
    echo 'Uso: sudo quarantine-restored-jobs.sh <id-de-16-hex>' >&2
    exit 64
}

release_dir="/srv/hubdigital/releases/${release_id}"
env_file=/etc/hubdigital/hubdigital.env
[[ -f "${release_dir}/artisan" && -r "${env_file}" ]] || { echo 'Falta release o entorno protegido.' >&2; exit 66; }
"${release_dir}/deploy/oracle/scripts/verify-release-state.sh" "${release_id}" validado_en_mantenimiento
for unit in hubdigital-worker.service hubdigital-r2-signatures.service hubdigital-schedule.service hubdigital-schedule.timer cloudflared-hubdigital.service; do
    systemctl is-active --quiet "${unit}" && { echo "Debe detenerse antes de cuarentena: ${unit}" >&2; exit 65; }
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

pgpass="$(mktemp /run/hubdigital/pgpass.quarantine.XXXXXX)"
chmod 0600 "${pgpass}"
trap 'rm -f -- "${pgpass}"' EXIT
escaped_password="$(printf '%s' "${db_password}" | sed -e 's/\\/\\\\/g' -e 's/:/\\:/g')"
printf '127.0.0.1:5432:%s:%s:%s\n' "${db_name}" "${db_user}" "${escaped_password}" > "${pgpass}"
export PGPASSFILE="${pgpass}"

sql="BEGIN;
CREATE SCHEMA IF NOT EXISTS hubdigital_control;
CREATE TABLE IF NOT EXISTS hubdigital_control.quarantined_jobs (
    release_id char(16) NOT NULL,
    job_id bigint NOT NULL,
    original_queue varchar(255) NOT NULL,
    quarantine_queue varchar(255) NOT NULL,
    job_class varchar(255) NOT NULL,
    quarantined_at timestamptz NOT NULL DEFAULT now(),
    restored_at timestamptz NULL,
    PRIMARY KEY (release_id, job_id)
);
LOCK TABLE jobs IN SHARE ROW EXCLUSIVE MODE;
WITH candidates AS (
    SELECT id, queue, left(coalesce(payload::jsonb ->> 'displayName', 'no_identificada'), 255) AS job_class
    FROM jobs
    WHERE queue NOT LIKE 'quarantine.%'
), captured AS (
    INSERT INTO hubdigital_control.quarantined_jobs (release_id, job_id, original_queue, quarantine_queue, job_class)
    SELECT :'release_id', id, queue, :'quarantine_queue', job_class FROM candidates
    ON CONFLICT (release_id, job_id) DO NOTHING
    RETURNING job_id
), moved AS (
    UPDATE jobs SET queue = :'quarantine_queue' WHERE id IN (SELECT job_id FROM captured)
    RETURNING id
)
SELECT 'jobs_en_cuarentena=' || count(*) FROM moved;
COMMIT;"
printf '%s\n' "${sql}" | psql --no-psqlrc --set=ON_ERROR_STOP=1 --tuples-only --no-align --quiet --host=127.0.0.1 --username="${db_user}" --dbname="${db_name}" \
    --set="release_id=${release_id}" --set="quarantine_queue=${quarantine_queue}"
echo "Cuarentena reversible preparada en ${quarantine_queue}; no se eliminaron trabajos."
