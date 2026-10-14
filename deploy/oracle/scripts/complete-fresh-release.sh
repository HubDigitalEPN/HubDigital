#!/usr/bin/env bash
set -euo pipefail

# Segunda fase de base nueva: exige R2 real, verifica el candidato y solo
# entonces permite la activacion posterior. No siembra usuarios ni datos demo.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la finalizacion nueva con sudo.' >&2
    exit 64
fi

release_id="${1:-}"
[[ "${release_id}" =~ ^[0-9a-f]{16}$ ]] || { echo 'Uso: sudo complete-fresh-release.sh <id-de-16-hex>' >&2; exit 64; }
release_dir="/srv/hubdigital/releases/${release_id}"
env_file=/etc/hubdigital/hubdigital.env
[[ -r "${env_file}" && -f "${release_dir}/artisan" ]] || { echo 'Falta entorno o release.' >&2; exit 66; }
"${release_dir}/deploy/oracle/scripts/verify-release-state.sh" "${release_id}" migrada_pendiente_r2
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
require_exact() { [[ "$(read_env "$1")" == "$2" ]] || { echo "$1 debe ser $2." >&2; exit 65; }; }
require_value() { value="$(read_env "$1")"; [[ -n "${value}" && "${value}" != '<'*'>' ]] || { echo "Falta $1." >&2; exit 65; }; }
require_exact DEPOSIT_STORAGE_DRIVER r2
require_exact DEPOSIT_STORAGE_REQUIRE_REMOTE true
require_exact DEPOSIT_STORAGE_VERIFY_AFTER_WRITE true
require_exact HUBDIGITAL_VALIDATION_MODE true
require_exact MAIL_MAILER smtp
for key in R2_ACCOUNT_ID R2_BUCKET R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY DEPOSIT_STORAGE_PREFIX; do require_value "${key}"; done
for unit in hubdigital-worker.service hubdigital-r2-signatures.service hubdigital-schedule.timer hubdigital-schedule.service cloudflared-hubdigital.service; do
    systemctl is-active --quiet "${unit}" && { echo "Servicio activo durante validacion R2: ${unit}" >&2; exit 65; }
done
artisan() {
    systemd-run --quiet --wait --collect --pipe --property=User=www-data --property=Group=www-data \
        --property=EnvironmentFile="${env_file}" --working-directory="${release_dir}" \
        /usr/bin/php8.4 artisan "$@"
}
artisan depositos:verificar-almacenamiento --exigir-r2
artisan optimize
bash "${release_dir}/deploy/oracle/scripts/preflight-candidate.sh" "${release_dir}"
"${release_dir}/deploy/oracle/scripts/update-service-units.sh" "${release_dir}"
state_file="/var/lib/hubdigital/migration/release-${release_id}.json"
jq --arg prepared_at "$(date --utc +%FT%TZ)" '. + {preparado_en:$prepared_at,estado:"instalacion_nueva_validada"}' "${state_file}" > "${state_file}.tmp"
chmod 0600 "${state_file}.tmp"
mv "${state_file}.tmp" "${state_file}"
echo "Release nueva validada con R2: ${release_id}"
