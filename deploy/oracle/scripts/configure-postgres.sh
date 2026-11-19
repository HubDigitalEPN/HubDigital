#!/usr/bin/env bash
set -euo pipefail

# Crea el rol y la base de aplicacion sin imprimir contrasenas ni cambiar el
# listener local. Requiere /etc/hubdigital/hubdigital.env con valores reales.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta este script con sudo.' >&2
    exit 64
fi

env_file=/etc/hubdigital/hubdigital.env
[[ -r "${env_file}" ]] || { echo 'Falta el archivo de entorno protegido.' >&2; exit 66; }

read_env() {
    local key="$1"
    local value
    value="$(sed -n "s/^${key}=//p" "${env_file}" | tail -n 1)"
    [[ -n "${value}" ]] || { echo "Falta ${key} en ${env_file}." >&2; exit 65; }
    printf '%s' "${value}"
}

db_name="$(read_env DB_DATABASE)"
db_user="$(read_env DB_USERNAME)"
db_password="$(read_env DB_PASSWORD)"
[[ "${db_name}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,44}$ ]] || { echo 'DB_DATABASE invalida.' >&2; exit 65; }
[[ "${db_user}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ ]] || { echo 'DB_USERNAME invalido.' >&2; exit 65; }
[[ "${db_password}" != *$'\n'* && "${db_password}" != *$'\r'* ]] || { echo 'DB_PASSWORD no puede contener saltos de línea.' >&2; exit 65; }
db_password_sql="${db_password//\'/\'\'}"

# La reconstruccion de DESA instala su perfil antes del primer reinicio y de
# las comprobaciones SQL. PROD conserva el perfil general de 96 MiB.
shared_buffers_expected=96MB
app_url="$(read_env APP_URL)"
if [[ "${app_url%/}" == 'https://dev.labinvepn.org' ]]; then
    script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
    python3 "${script_dir}/configure-dev-memory.py" --prepare
    shared_buffers_expected=48MB
fi

systemctl enable postgresql@16-main.service
# El paquete puede haber arrancado antes de copiar 90-hubdigital.conf: el
# reinicio controlado garantiza que los valores se leen antes de crear datos.
systemctl restart postgresql@16-main.service
systemctl is-active --quiet postgresql@16-main.service
runuser -u postgres -- psql -v ON_ERROR_STOP=1 --set=db_name="${db_name}" --set=db_user="${db_user}" <<SQL
SELECT format('CREATE ROLE %I LOGIN', :'db_user')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'db_user')\gexec
ALTER ROLE :"db_user" PASSWORD '${db_password_sql}';
SELECT format('CREATE DATABASE %I OWNER %I', :'db_name', :'db_user')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'db_name')\gexec
SQL
runuser -u postgres -- psql -v ON_ERROR_STOP=1 --dbname="${db_name}" --set=db_user="${db_user}" <<'SQL'
CREATE EXTENSION IF NOT EXISTS pg_trgm;
SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', current_database())\gexec
SELECT format('GRANT CONNECT, TEMPORARY ON DATABASE %I TO %I', current_database(), :'db_user')\gexec
SQL

for expected in "listen_addresses=127.0.0.1" "max_connections=20" "shared_buffers=${shared_buffers_expected}" "work_mem=2MB"; do
    parameter="${expected%%=*}"
    value="${expected#*=}"
    actual="$(runuser -u postgres -- psql -Atqc "SHOW ${parameter}")"
    [[ "${actual}" == "${value}" ]] || { echo "PostgreSQL no aplicó ${parameter}=${value} (actual: ${actual})." >&2; exit 1; }
done

echo 'PostgreSQL 16 configurado en loopback. No se modificaron reglas de entrada ni se expuso 5432.'
