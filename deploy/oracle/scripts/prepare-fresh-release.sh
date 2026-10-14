#!/usr/bin/env bash
set -euo pipefail

# Primera fase para una base nueva: extrae una release verificada y migra antes
# de cualquier consulta de tablas. No requiere ni toca R2 y no inicia servicios.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la preparacion nueva con sudo.' >&2
    exit 64
fi

archive="${1:-}"
checksum_file="${2:-${archive}.sha256}"
[[ -r "${archive}" && -r "${checksum_file}" ]] || { echo 'Uso: sudo prepare-fresh-release.sh release.tar.gz release.tar.gz.sha256' >&2; exit 64; }
expected_sha="$(awk 'NR == 1 && $1 ~ /^[0-9a-fA-F]{64}$/ {print tolower($1)}' "${checksum_file}")"
actual_sha="$(sha256sum "${archive}" | awk '{print $1}')"
[[ -n "${expected_sha}" && "${expected_sha}" == "${actual_sha}" ]] || { echo 'Checksum invalido.' >&2; exit 65; }

env_file=/etc/hubdigital/hubdigital.env
[[ -r "${env_file}" ]] || { echo 'Falta el entorno protegido.' >&2; exit 66; }
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
for key in APP_KEY DB_DATABASE DB_USERNAME DB_PASSWORD; do
    value="$(read_env "${key}")"
    [[ -n "${value}" && "${value}" != '<'*'>' ]] || { echo "Falta ${key}." >&2; exit 65; }
done

release_id="${actual_sha:0:16}"
release_dir="/srv/hubdigital/releases/${release_id}"
[[ ! -e "${release_dir}" ]] || { echo 'La release ya existe; se rechaza sobrescribirla.' >&2; exit 73; }
listing="$(tar --list --gzip --file="${archive}")"
grep -Eq '(^/|(^|/)\.\.(/|$))' <<<"${listing}" && { echo 'El artefacto contiene rutas inseguras.' >&2; exit 65; }
tar --list --verbose --gzip --file="${archive}" | awk '$1 ~ /^[lh]/ { exit 1 }' || { echo 'El artefacto contiene enlaces.' >&2; exit 65; }
install -d -m 0755 "${release_dir}"
tar --extract --gzip --file="${archive}" --directory="${release_dir}" --no-same-owner --no-same-permissions
[[ -f "${release_dir}/artisan" && -f "${release_dir}/vendor/autoload.php" ]] || { echo 'Runtime incompleto.' >&2; exit 65; }
install -d -m 0750 -o www-data -g www-data \
    "${release_dir}/storage/app" "${release_dir}/storage/framework/cache/data" \
    "${release_dir}/storage/framework/sessions" "${release_dir}/storage/framework/views" \
    "${release_dir}/storage/logs" "${release_dir}/bootstrap/cache"
chown -R www-data:www-data "${release_dir}/storage" "${release_dir}/bootstrap/cache"

for unit in hubdigital-worker.service hubdigital-r2-signatures.service hubdigital-schedule.timer hubdigital-schedule.service cloudflared-hubdigital.service; do
    systemctl is-active --quiet "${unit}" && { echo "Servicio activo durante migracion: ${unit}" >&2; exit 65; }
done
artisan() {
    systemd-run --quiet --wait --collect --pipe --property=User=www-data --property=Group=www-data \
        --property=EnvironmentFile="${env_file}" --working-directory="${release_dir}" \
        /usr/bin/php8.4 artisan "$@"
}
PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/bin/composer bash "${release_dir}/deploy/oracle/scripts/verify-platform.sh" "${release_dir}"
artisan config:clear
# CACHE_STORE=database consulta la tabla cache; en una base nueva solo puede
# limpiarse despues de que migrate haya creado las tablas requeridas.
artisan migrate --force --no-interaction
artisan db:seed --class='Database\Seeders\InstitucionesCatalogoSeeder' --force --no-interaction
if [[ "$(read_env SEED_BOOTSTRAP_DEPOSITANTE)" == true ]]; then
    for key in BOOTSTRAP_DEPOSITANTE_EMAIL BOOTSTRAP_DEPOSITANTE_PASSWORD; do
        value="$(read_env "${key}")"
        [[ -n "${value}" && "${value}" != '<'*'>' ]] || { echo "Falta ${key}." >&2; exit 65; }
    done
    artisan db:seed --class='Database\Seeders\DepositanteBootstrapSeeder' --force --no-interaction
fi
artisan cache:clear
artisan migrate:status --no-interaction
artisan down --retry=60 --no-interaction

install -d -m 0700 /var/lib/hubdigital/migration
manifest_sha="$(sha256sum "${release_dir}/RELEASE-MANIFEST.sha256" | awk '{print $1}')"
content_sha="$(jq -er '.content_sha256' "${release_dir}/RELEASE-METADATA.json")"
printf '{"release_id":"%s","release_sha256":"%s","manifest_sha256":"%s","content_sha256":"%s","preparado_en":"%s","estado":"migrada_pendiente_r2"}\n' \
    "${release_id}" "${actual_sha}" "${manifest_sha}" "${content_sha}" "$(date --utc +%FT%TZ)" \
    > "/var/lib/hubdigital/migration/release-${release_id}.json"
chmod 0600 "/var/lib/hubdigital/migration/release-${release_id}.json"
echo "Base nueva migrada; R2 pendiente antes de activar: ${release_id}"
