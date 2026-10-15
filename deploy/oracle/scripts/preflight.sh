#!/usr/bin/env bash
set -euo pipefail

# Validacion de arranque sin crear usuarios, seeders, correos ni documentos.
# La unica escritura externa es el objeto aleatorio y autocontenido que el
# comando oficial de R2 elimina en su misma ejecucion.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta preflight con sudo.' >&2
    exit 64
fi

env_file=/etc/hubdigital/hubdigital.env
app_dir=/srv/hubdigital/current
source "$(dirname -- "${BASH_SOURCE[0]}")/deployment-host.sh"
[[ -r "${env_file}" && -f "${app_dir}/artisan" ]] || { echo 'Falta entorno o release activa.' >&2; exit 66; }
artisan() {
    systemd-run --quiet --wait --collect --pipe \
        --property=User=www-data --property=Group=www-data \
        --property=EnvironmentFile="${env_file}" --working-directory="${app_dir}" \
        /usr/bin/php8.4 artisan "$@"
}
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }

[[ "$(read_env DEPOSIT_STORAGE_DRIVER)" == r2 ]] || { echo 'R2 no esta seleccionado.' >&2; exit 65; }
[[ "$(read_env DEPOSIT_STORAGE_REQUIRE_REMOTE)" == true ]] || { echo 'R2 remoto no es obligatorio.' >&2; exit 65; }
[[ "$(read_env DEPOSIT_STORAGE_VERIFY_AFTER_WRITE)" == true ]] || { echo 'La escritura R2 no se verifica.' >&2; exit 65; }
[[ "$(read_env DB_QUEUE_RETRY_AFTER)" -gt 300 ]] || { echo 'retry_after debe superar el timeout del worker (300 s).' >&2; exit 65; }
validation_queue="$(read_env HUBDIGITAL_VALIDATION_QUEUE)"
[[ "${validation_queue}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$ && "${validation_queue}" != default ]] || {
    echo 'HUBDIGITAL_VALIDATION_QUEUE debe ser una cola dedicada valida distinta de default.' >&2
    exit 65
}

systemctl is-active --quiet postgresql@16-main php8.4-fpm nginx hubdigital-worker
if [[ "$(read_env DEPOSIT_SIGNATURE_ASYNC)" == true ]]; then
    systemctl is-active --quiet hubdigital-r2-signatures.service
fi
if [[ "$(read_env HUBDIGITAL_SCHEDULER_REVIEWED)" == true ]]; then
    systemctl is-active --quiet hubdigital-schedule.timer
else
    ! systemctl is-active --quiet hubdigital-schedule.service
    ! systemctl is-active --quiet hubdigital-schedule.timer
fi
systemctl is-enabled --quiet hubdigital-temp-cleanup.timer
php_modules="$(/usr/bin/php8.4 -m)"
for extension in curl gd intl mbstring openssl pdo_pgsql xml zip bcmath; do
    grep -Fxq "${extension}" <<<"${php_modules}" || { echo "Falta la extensión PHP ${extension}." >&2; exit 65; }
done
pg_isready --host=127.0.0.1 --port=5432 --username="$(read_env DB_USERNAME)" --dbname="$(read_env DB_DATABASE)"
[[ -s /etc/letsencrypt/live/${deployment_hostname}/fullchain.pem && -s /etc/letsencrypt/live/${deployment_hostname}/privkey.pem ]] || {
    echo 'Falta el certificado HTTPS del origen.' >&2
    exit 65
}
curl --fail --silent --show-error --max-time 15 --resolve "${deployment_hostname}:443:127.0.0.1" "https://${deployment_hostname}/depositos" >/dev/null
artisan migrate:status --no-interaction
artisan depositos:verificar-almacenamiento --exigir-r2
PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/bin/composer \
    bash "${app_dir}/deploy/oracle/scripts/verify-platform.sh" "${app_dir}"
bash "${app_dir}/deploy/oracle/scripts/verify-temporaries.sh"

# Nada bajo storage/app puede convertirse en un segundo repositorio documental.
if find "${app_dir}/storage/app" -type f -print -quit | grep -q .; then
    echo 'Se detectaron archivos persistentes bajo storage/app; investigar antes de publicar.' >&2
    exit 1
fi
echo 'Preflight correcto: PostgreSQL local, R2 estricto y HTTPS directo comprobados.'
