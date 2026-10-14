#!/usr/bin/env bash
set -euo pipefail

# Inspecciona sin ejecutar cola ni scheduler. Se usa tras restaurar y otra vez
# justo antes de activar una release; conserva los trabajos existentes.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la inspeccion con sudo.' >&2
    exit 64
fi

app_dir="${1:-}"
env_file=/etc/hubdigital/hubdigital.env
[[ -n "${app_dir}" && -f "${app_dir}/artisan" && -r "${env_file}" ]] || {
    echo 'Uso: sudo inspect-restored-state.sh /srv/hubdigital/releases/<id>' >&2
    exit 64
}
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
db_name="$(read_env DB_DATABASE)"
db_user="$(read_env DB_USERNAME)"
db_password="$(read_env DB_PASSWORD)"
[[ "${db_name}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,44}$ && "${db_user}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ && -n "${db_password}" ]] || {
    echo 'Configuracion PostgreSQL invalida.' >&2
    exit 65
}
validation_queue="$(read_env HUBDIGITAL_VALIDATION_QUEUE)"
[[ "${validation_queue}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$ && "${validation_queue}" != default ]] || {
    echo 'HUBDIGITAL_VALIDATION_QUEUE debe ser una cola dedicada valida distinta de default.' >&2
    exit 65
}

pgpass="$(mktemp /run/hubdigital/pgpass.inspect.XXXXXX)"
chmod 0600 "${pgpass}"
trap 'rm -f -- "${pgpass}"' EXIT
escaped_password="$(printf '%s' "${db_password}" | sed -e 's/\\/\\\\/g' -e 's/:/\\:/g')"
printf '127.0.0.1:5432:%s:%s:%s\n' "${db_name}" "${db_user}" "${escaped_password}" > "${pgpass}"
export PGPASSFILE="${pgpass}"

for unit in hubdigital-worker.service hubdigital-r2-signatures.service hubdigital-schedule.service hubdigital-schedule.timer cloudflared-hubdigital.service; do
    systemctl is-active --quiet "${unit}" && {
        echo "El servicio debe permanecer detenido durante esta inspeccion: ${unit}" >&2
        exit 65
    }
done

counter_query="SELECT 'pending_jobs=' || count(*) FROM jobs
UNION ALL SELECT 'pending_jobs_validation_queue=' || count(*) FROM jobs WHERE queue = :'validation_queue'
UNION ALL SELECT 'failed_jobs=' || count(*) FROM failed_jobs
UNION ALL SELECT 'job_batches=' || count(*) FROM job_batches;"
pending_classes_query="SELECT 'pending_job|queue=' || left(queue, 80)
    || '|class=' || left(coalesce(payload::jsonb ->> 'displayName', 'no_identificada'), 160)
    || '|count=' || count(*)
FROM jobs
GROUP BY queue, coalesce(payload::jsonb ->> 'displayName', 'no_identificada')
ORDER BY 1;"
failed_classes_query="SELECT 'failed_job|class=' || left(coalesce(payload::jsonb ->> 'displayName', 'no_identificada'), 160)
    || '|count=' || count(*)
FROM failed_jobs
GROUP BY coalesce(payload::jsonb ->> 'displayName', 'no_identificada')
ORDER BY 1;"
echo 'colas_restauradas:'
printf '%s\n' "${counter_query}" | psql --no-psqlrc --set=ON_ERROR_STOP=1 --tuples-only --no-align --quiet --host=127.0.0.1 --username="${db_user}" --dbname="${db_name}" \
    --set="validation_queue=${validation_queue}"
echo 'trabajos_pendientes_por_cola_y_clase:'
printf '%s\n' "${pending_classes_query}" | psql --no-psqlrc --set=ON_ERROR_STOP=1 --tuples-only --no-align --quiet --host=127.0.0.1 --username="${db_user}" --dbname="${db_name}"
echo 'trabajos_fallidos_por_clase:'
printf '%s\n' "${failed_classes_query}" | psql --no-psqlrc --set=ON_ERROR_STOP=1 --tuples-only --no-align --quiet --host=127.0.0.1 --username="${db_user}" --dbname="${db_name}"
echo 'tareas_programadas:'
systemd-run --quiet --wait --collect --pipe \
    --property=User=www-data --property=Group=www-data \
    --property=EnvironmentFile="${env_file}" --working-directory="${app_dir}" \
    /usr/bin/php8.4 artisan schedule:list --no-interaction
