#!/usr/bin/env bash
set -euo pipefail

# Comprueba el candidato y el entorno antes de exponerlo o reiniciar cualquier
# escritor de base de datos. No inicia worker, timer ni scheduler.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta preflight-candidate con sudo.' >&2
    exit 64
fi

app_dir="${1:-}"
env_file=/etc/hubdigital/hubdigital.env
[[ -n "${app_dir}" && -f "${app_dir}/artisan" && -r "${env_file}" ]] || {
    echo 'Uso: sudo preflight-candidate.sh /srv/hubdigital/releases/<id>' >&2
    exit 64
}
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
require_exact() { [[ "$(read_env "$1")" == "$2" ]] || { echo "$1 debe ser $2." >&2; exit 65; }; }
artisan() {
    systemd-run --quiet --wait --collect --pipe \
        --property=User=www-data --property=Group=www-data \
        --property=EnvironmentFile="${env_file}" --working-directory="${app_dir}" \
        /usr/bin/php8.4 artisan "$@"
}

require_exact DEPOSIT_STORAGE_DRIVER r2
require_exact DEPOSIT_STORAGE_REQUIRE_REMOTE true
require_exact DEPOSIT_STORAGE_VERIFY_AFTER_WRITE true
require_exact HUBDIGITAL_VALIDATION_MODE true
[[ "$(read_env DB_QUEUE_RETRY_AFTER)" -gt 300 ]] || { echo 'retry_after debe superar 300 s.' >&2; exit 65; }
require_exact MAIL_MAILER smtp
validation_queue="$(read_env HUBDIGITAL_VALIDATION_QUEUE)"
[[ "${validation_queue}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$ && "${validation_queue}" != default ]] || {
    echo 'HUBDIGITAL_VALIDATION_QUEUE debe ser una cola dedicada valida distinta de default.' >&2
    exit 65
}
[[ -z "$(swapon --noheadings --show)" ]] || { echo 'Hay swap activo; no se acepta como alivio de memoria.' >&2; exit 65; }
systemctl is-active --quiet postgresql@16-main
systemctl is-active --quiet hubdigital-worker.service && { echo 'Worker activo durante preflight de candidato.' >&2; exit 65; }
systemctl is-active --quiet hubdigital-schedule.service && { echo 'Scheduler activo durante preflight de candidato.' >&2; exit 65; }
systemctl is-active --quiet hubdigital-schedule.timer && { echo 'Timer activo durante preflight de candidato.' >&2; exit 65; }
systemctl is-active --quiet cloudflared-hubdigital.service && { echo 'Tunnel activo durante preflight de candidato.' >&2; exit 65; }

php_modules="$(/usr/bin/php8.4 -m)"
for binary in java; do
    command -v "${binary}" >/dev/null 2>&1 || {
        echo "Falta ${binary} para inspeccionar PDFs y firmas. Instale el runtime de documentos antes de continuar." >&2
        exit 65
    }
done
for extension in curl gd intl mbstring openssl pdo_pgsql xml zip bcmath; do
    grep -Fxq "${extension}" <<<"${php_modules}" || { echo "Falta la extension PHP ${extension}." >&2; exit 65; }
done
pg_isready --host=127.0.0.1 --port=5432 --username="$(read_env DB_USERNAME)" --dbname="$(read_env DB_DATABASE)"
PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/bin/composer \
    bash "${app_dir}/deploy/oracle/scripts/verify-platform.sh" "${app_dir}"
artisan migrate:status --no-interaction
artisan depositos:verificar-almacenamiento --exigir-r2
bash "${app_dir}/deploy/oracle/scripts/verify-temporaries.sh"

if find "${app_dir}/storage/app" -type f -print -quit | grep -q .; then
    echo 'Se detectaron documentos persistentes bajo storage/app.' >&2
    exit 1
fi
echo 'Candidato validado: PostgreSQL, plataforma, R2, correo configurado, temporales y sin swap.'
