#!/usr/bin/env bash
set -euo pipefail

# Valida y prepara un candidato sin cambiar current. Tras una migración no hay
# reversión automática de código: el destino queda en mantenimiento para que un
# operador evalúe compatibilidad de esquema y datos.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta el despliegue con sudo.' >&2
    exit 64
fi

archive="${1:-}"
checksum_file="${2:-${archive}.sha256}"
[[ -f "${archive}" && -r "${checksum_file}" ]] || {
    echo 'Uso: sudo deploy-release.sh /ruta/release.tar.gz /ruta/release.tar.gz.sha256' >&2
    exit 64
}
expected_sha="$(awk 'NR == 1 && $1 ~ /^[0-9a-fA-F]{64}$/ {print tolower($1)}' "${checksum_file}")"
[[ -n "${expected_sha}" && "$(wc -l < "${checksum_file}")" -eq 1 ]] || {
    echo 'La suma esperada debe contener exactamente un SHA-256.' >&2
    exit 65
}
actual_sha="$(sha256sum "${archive}" | awk '{print $1}')"
[[ "${actual_sha}" == "${expected_sha}" ]] || { echo 'El artefacto no coincide con la suma entregada.' >&2; exit 65; }

env_file=/etc/hubdigital/hubdigital.env
[[ -r "${env_file}" ]] || { echo 'Falta el archivo de entorno protegido.' >&2; exit 66; }
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
require_exact() { [[ "$(read_env "$1")" == "$2" ]] || { echo "$1 debe ser $2." >&2; exit 65; }; }
require_value() { local value; value="$(read_env "$1")"; [[ -n "${value}" && "${value}" != '<'*'>' ]] || { echo "Falta $1." >&2; exit 65; }; }
require_exact DEPOSIT_STORAGE_DRIVER r2
require_exact DEPOSIT_STORAGE_REQUIRE_REMOTE true
require_exact DEPOSIT_STORAGE_VERIFY_AFTER_WRITE true
require_exact HUBDIGITAL_VALIDATION_MODE true
require_exact MAIL_MAILER smtp
validation_queue="$(read_env HUBDIGITAL_VALIDATION_QUEUE)"
[[ "${validation_queue}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$ && "${validation_queue}" != default ]] || {
    echo 'HUBDIGITAL_VALIDATION_QUEUE debe ser una cola dedicada valida distinta de default.' >&2
    exit 65
}
for key in APP_KEY DB_DATABASE DB_USERNAME DB_PASSWORD R2_ACCOUNT_ID R2_BUCKET R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY TURNSTILE_SITE_KEY TURNSTILE_SECRET; do require_value "${key}"; done

release_id="${actual_sha:0:16}"
release_dir="/srv/hubdigital/releases/${release_id}"
[[ ! -e "${release_dir}" ]] || { echo 'Ese artefacto ya fue extraído; se rechaza sobrescribirlo.' >&2; exit 73; }
listing="$(tar --list --gzip --file="${archive}")"
grep -Eq '(^/|(^|/)\.\.(/|$))' <<<"${listing}" && { echo 'El artefacto contiene rutas inseguras.' >&2; exit 65; }
tar --list --verbose --gzip --file="${archive}" | awk '$1 ~ /^[lh]/ { exit 1 }' || {
    echo 'El artefacto contiene enlaces; se rechaza antes de extraer.' >&2
    exit 65
}
install -d -m 0755 "${release_dir}"
tar --extract --gzip --file="${archive}" --directory="${release_dir}" --no-same-owner --no-same-permissions
[[ -f "${release_dir}/artisan" && -f "${release_dir}/vendor/autoload.php" && -d "${release_dir}/public/build" && -f "${release_dir}/deploy/oracle/scripts/verify-platform.sh" ]] || {
    mv "${release_dir}" "/srv/hubdigital/release-invalida-${release_id}"
    echo 'El artefacto no contiene el conjunto runtime esperado.' >&2
    exit 65
}

install -d -m 0750 -o www-data -g www-data \
    "${release_dir}/storage/app" "${release_dir}/storage/framework/cache/data" \
    "${release_dir}/storage/framework/sessions" "${release_dir}/storage/framework/views" \
    "${release_dir}/storage/logs" "${release_dir}/bootstrap/cache"
chown -R www-data:www-data "${release_dir}/storage" "${release_dir}/bootstrap/cache"
[[ -d /var/log/php8.4-fpm ]] || { echo 'Falta el directorio del slowlog FPM.' >&2; exit 66; }

candidate_artisan() {
    systemd-run --quiet --wait --collect --pipe --property=User=www-data --property=Group=www-data \
        --property=EnvironmentFile="${env_file}" --working-directory="${release_dir}" \
        /usr/bin/php8.4 artisan "$@"
}
PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/bin/composer bash "${release_dir}/deploy/oracle/scripts/verify-platform.sh" "${release_dir}"
candidate_artisan config:clear
candidate_artisan cache:clear
candidate_artisan migrate:status --no-interaction
candidate_artisan down --retry=60 --no-interaction
candidate_artisan depositos:verificar-almacenamiento --exigir-r2

previous="$(readlink -f /srv/hubdigital/current 2>/dev/null || true)"
if [[ -n "${previous}" && -f "${previous}/artisan" ]]; then
    systemd-run --quiet --wait --collect --pipe --property=User=www-data --property=Group=www-data \
        --property=EnvironmentFile="${env_file}" --working-directory="${previous}" \
        /usr/bin/php8.4 artisan down --retry=60 --no-interaction
fi
for unit in hubdigital-worker.service hubdigital-schedule.timer hubdigital-schedule.service; do
    if systemctl cat "${unit}" >/dev/null 2>&1; then
        systemctl stop "${unit}"
    else
        echo "Unidad no instalada (no se intenta detener): ${unit}" >&2
    fi
done
bash "${release_dir}/deploy/oracle/scripts/preflight-candidate.sh" "${release_dir}"

if [[ "${APPLY_MIGRATIONS:-0}" == 1 ]]; then
    if ! candidate_artisan migrate --force --no-interaction; then
        echo 'La migración falló: current no cambió y se mantiene mantenimiento para revisión manual.' >&2
        exit 1
    fi
else
    echo 'Migraciones no aplicadas (APPLY_MIGRATIONS=1 las habilita tras validar la restauración).'
fi
if ! candidate_artisan migrate:status --pending=1 --no-interaction; then
    echo 'Quedan migraciones pendientes. No se prepara una release activable hasta aplicarlas.' >&2
    exit 65
fi
candidate_artisan db:seed --class='Database\Seeders\InstitucionesCatalogoSeeder' --force --no-interaction
if [[ "$(read_env SEED_BOOTSTRAP_DEPOSITANTE)" == true ]]; then
    require_value BOOTSTRAP_DEPOSITANTE_EMAIL
    require_value BOOTSTRAP_DEPOSITANTE_PASSWORD
    candidate_artisan db:seed --class='Database\Seeders\DepositanteBootstrapSeeder' --force --no-interaction
fi
if ! candidate_artisan optimize || ! candidate_artisan depositos:verificar-almacenamiento --exigir-r2; then
    echo 'El candidato no superó optimización o R2; no se activa y permanece en mantenimiento.' >&2
    exit 1
fi

# Prepara los agregados y facetas antes de exponer la release; el primer
# visitante recibe la misma caché invalidada por revisión que los siguientes.
if ! systemd-run --quiet --wait --collect --pipe --property=User=www-data --property=Group=www-data \
    --property=EnvironmentFile="${env_file}" --working-directory="${release_dir}" \
    /usr/bin/php8.4 "${release_dir}/deploy/oracle/scripts/warm-portal-cache.php"; then
    echo 'No se pudo preparar la caché inicial del portal; la release permanece en mantenimiento.' >&2
    exit 1
fi

install -d -m 0700 /var/lib/hubdigital/migration
"${release_dir}/deploy/oracle/scripts/inspect-restored-state.sh" "${release_dir}"
manifest_sha="$(sha256sum "${release_dir}/RELEASE-MANIFEST.sha256" | awk '{print $1}')"
content_sha="$(jq -er '.content_sha256' "${release_dir}/RELEASE-METADATA.json")"
[[ "${content_sha}" =~ ^[0-9a-f]{64}$ ]] || { echo 'Los metadatos del candidato no contienen un hash de contenido valido.' >&2; exit 65; }
printf '{"release_id":"%s","release_sha256":"%s","manifest_sha256":"%s","content_sha256":"%s","preparado_en":"%s","estado":"validado_en_mantenimiento"}\n' \
    "${release_id}" "${actual_sha}" "${manifest_sha}" "${content_sha}" "$(date --utc +%FT%TZ)" \
    > "/var/lib/hubdigital/migration/release-${release_id}.json"
chmod 0600 "/var/lib/hubdigital/migration/release-${release_id}.json"
printf 'Release preparada en mantenimiento: %s\n' "${release_id}"
printf 'No se iniciaron worker, scheduler ni Tunnel.\n'
printf 'Validaciones superadas. Puede activar ahora copiando y ejecutando:\n'
printf 'sudo %q %q\n' \
    "${release_dir}/deploy/oracle/scripts/activate-release.sh" \
    "${release_id}"
