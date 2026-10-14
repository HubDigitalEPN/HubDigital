#!/usr/bin/env bash
set -euo pipefail

# Restaura en una base candidata y sólo intercambia nombres después de validar
# pg_restore. Nunca borra ni sobrescribe automáticamente la base previa.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la restauración con sudo.' >&2
    exit 64
fi

dump_file="${1:-}"
manifest_file="${2:-}"
[[ -s "${dump_file}" && -s "${manifest_file}" ]] || {
    echo 'Uso: sudo restore-postgres.sh /ruta/postgresql.dump /ruta/manifiesto-coordinado.json' >&2
    exit 64
}
command -v jq >/dev/null || { echo 'Falta jq para validar el manifiesto JSON.' >&2; exit 66; }
env_file=/etc/hubdigital/hubdigital.env
[[ -r "${env_file}" ]] || { echo 'Falta el archivo de entorno protegido.' >&2; exit 66; }
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
db_name="$(read_env DB_DATABASE)"
db_user="$(read_env DB_USERNAME)"
db_password="$(read_env DB_PASSWORD)"
[[ "${db_name}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,44}$ && "${db_user}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ && -n "${db_password}" ]] || {
    echo 'Configuración PostgreSQL incompleta o inválida.' >&2
    exit 65
}
[[ "${db_password}" != *$'\n'* && "${db_password}" != *$'\r'* ]] || { echo 'DB_PASSWORD no puede contener saltos de línea.' >&2; exit 65; }

dump_name="$(basename "${dump_file}")"
jq -e --arg dump_name "${dump_name}" '
    .version_formato == 1
    and .estado == "COMPLETO"
    and (.id | type == "string" and test("^[A-Za-z0-9._-]+$"))
    and (.creado_en | type == "string" and length > 0)
    and (.origen | type == "object")
    and (.origen.entorno | type == "string" and length > 0)
    and (.origen.instancia | type == "string" and length > 0)
    and (.origen.base_datos | type == "string" and length > 0)
    and (.origen.revision | type == "string" and length > 0)
    and (.origen.capturado_en | type == "string" and length > 0)
    and (.postgresql.archivo == $dump_name)
    and (.postgresql.sha256 | type == "string" and test("^[0-9a-fA-F]{64}$"))
    and (.documentos.archivo | type == "string" and length > 0)
    and (.documentos.sha256 | type == "string" and test("^[0-9a-fA-F]{64}$"))
    and (.documentos.prefijo | type == "string" and length > 0)
    and (.colas.pendientes | type == "number" and . >= 0)
    and (.colas.fallidos | type == "number" and . >= 0)
' "${manifest_file}" >/dev/null || {
    echo 'El manifiesto no tiene estructura, procedencia o estado de corte válidos.' >&2
    exit 65
}
expected_sha="$(jq -r '.postgresql.sha256' "${manifest_file}")"
actual_sha="$(sha256sum "${dump_file}" | awk '{print $1}')"
[[ "${expected_sha}" == "${actual_sha}" ]] || { echo 'El SHA-256 del dump no coincide con el manifiesto.' >&2; exit 65; }
pg_restore --list "${dump_file}" >/dev/null

pgpass="$(mktemp /run/hubdigital/pgpass.restore.XXXXXX)"
chmod 0600 "${pgpass}"
trap 'rm -f -- "${pgpass}"' EXIT
escaped_password="$(printf '%s' "${db_password}" | sed -e 's/\\/\\\\/g' -e 's/:/\\:/g')"
printf '127.0.0.1:5432:*:%s:%s\n' "${db_user}" "${escaped_password}" > "${pgpass}"
export PGPASSFILE="${pgpass}"

user_tables="$(psql -v ON_ERROR_STOP=1 --host=127.0.0.1 --username="${db_user}" --dbname="${db_name}" -Atqc "SELECT count(*) FROM pg_tables WHERE schemaname NOT IN ('pg_catalog','information_schema')")"
[[ "${user_tables}" == 0 ]] || {
    echo 'El destino contiene tablas de usuario; se niega a sobrescribirlo.' >&2
    exit 73
}
connections="$(runuser -u postgres -- psql -d postgres -Atqc "SELECT count(*) FROM pg_stat_activity WHERE datname = '${db_name}'")"
[[ "${connections}" == 0 ]] || { echo 'Hay conexiones activas a la base destino; no se inicia restauración.' >&2; exit 73; }

stamp="$(date --utc +%Y%m%d%H%M%S)"
candidate="${db_name}_r_${stamp}"
previous_empty="${db_name}_pre_${stamp}"
runuser -u postgres -- psql -d postgres -v ON_ERROR_STOP=1 --set=candidate="${candidate}" --set=db_user="${db_user}" <<'SQL'
SELECT format('CREATE DATABASE %I OWNER %I', :'candidate', :'db_user')\gexec
SQL

if ! pg_restore --host=127.0.0.1 --username="${db_user}" --dbname="${candidate}" \
    --no-owner --no-privileges --exit-on-error --verbose "${dump_file}"; then
    echo "La restauración falló en ${candidate}; ${db_name} permanece intacta para investigación." >&2
    exit 1
fi
restored_tables="$(psql -v ON_ERROR_STOP=1 --host=127.0.0.1 --username="${db_user}" --dbname="${candidate}" -Atqc "SELECT count(*) FROM pg_tables WHERE schemaname NOT IN ('pg_catalog','information_schema')")"
[[ "${restored_tables}" -gt 0 ]] || {
    echo "La base candidata ${candidate} no contiene tablas de usuario; no se activa." >&2
    exit 1
}

# Ningún worker, scheduler, FPM, nginx o Tunnel puede tocar la base durante el
# cambio de nombres. Las bases quedan preservadas si el intercambio falla.
for unit in cloudflared-hubdigital.service hubdigital-worker.service hubdigital-r2-signatures.service hubdigital-schedule.timer hubdigital-schedule.service nginx.service php8.4-fpm.service; do
    if systemctl cat "${unit}" >/dev/null 2>&1; then
        systemctl stop "${unit}"
    else
        echo "Unidad no instalada (no se intenta detener): ${unit}" >&2
    fi
done
connections="$(runuser -u postgres -- psql -d postgres -Atqc "SELECT count(*) FROM pg_stat_activity WHERE datname = '${db_name}'")"
[[ "${connections}" == 0 ]] || { echo 'Persisten conexiones a destino; no se intercambian bases.' >&2; exit 73; }
runuser -u postgres -- psql -d postgres -v ON_ERROR_STOP=1 --set=target="${db_name}" --set=previous="${previous_empty}" --set=candidate="${candidate}" <<'SQL'
SELECT format('ALTER DATABASE %I RENAME TO %I', :'target', :'previous')\gexec
SELECT format('ALTER DATABASE %I RENAME TO %I', :'candidate', :'target')\gexec
SQL

install -d -m 0700 /var/lib/hubdigital/migration
jq -cn --arg restored_at "$(date --utc +%FT%TZ)" --arg sha "${actual_sha}" \
    --arg source "$(jq -c '.origen' "${manifest_file}")" --arg previous_empty "${previous_empty}" \
    --argjson table_count "${restored_tables}" \
    '{restaurado_en:$restored_at, postgresql_sha256:$sha, origen:($source|fromjson), tablas_usuario:$table_count, base_vacia_previa:$previous_empty}' \
    > "/var/lib/hubdigital/migration/restore-${actual_sha:0:16}.json"
chmod 0600 "/var/lib/hubdigital/migration/restore-${actual_sha:0:16}.json"
echo 'Restauración PostgreSQL validada y activada. Servicios permanecen detenidos hasta validar release, R2 y preflight.'
