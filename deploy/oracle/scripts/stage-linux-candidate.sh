#!/usr/bin/env bash
set -euo pipefail

# No heredar la mascara privada de un asistente que tambien crea respaldos.
umask 0022

# Recibe un bundle fuente ya filtrado y construye/valida un artefacto Linux en
# staging. No crea base, no lee el entorno protegido y no inicia servicios.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta el staging con sudo.' >&2
    exit 64
fi

source_archive="${1:-}"
[[ -r "${source_archive}" ]] || { echo 'Uso: sudo stage-linux-candidate.sh /ruta/bundle-fuente.tar.gz' >&2; exit 64; }

source_dir="$(mktemp -d /tmp/hubdigital-source.XXXXXX)"
build_dir="$(mktemp -d /tmp/hubdigital-build.XXXXXX)"
heartbeat=''
cleanup() {
    if [[ -n "${heartbeat}" ]]; then kill "${heartbeat}" 2>/dev/null || true; wait "${heartbeat}" 2>/dev/null || true; fi
    rm -rf -- "${source_dir}" "${build_dir}"
}
trap cleanup EXIT

# BEGIN RELEASE PHASE
run_phase() {
    local label="$1" started="${SECONDS}" status=0
    shift
    printf '==> %s\n' "${label}" >&2
    (while sleep 10; do printf 'En curso: %s (%s s)\n' "${label}" "$((SECONDS - started))" >&2; done) &
    heartbeat=$!
    "$@" || status=$?
    kill "${heartbeat}" 2>/dev/null || true
    wait "${heartbeat}" 2>/dev/null || true
    heartbeat=''
    printf '%s: %s (%s s)\n' "$([[ "${status}" -eq 0 ]] && printf OK || printf FALLO)" "${label}" "$((SECONDS - started))" >&2
    return "${status}"
}
# END RELEASE PHASE

run_phase 'Extrayendo el bundle fuente' tar --extract --gzip --file="${source_archive}" --directory="${source_dir}" --no-same-owner --no-same-permissions
[[ -f "${source_dir}/artisan" && -f "${source_dir}/vendor/autoload.php" ]] || { echo 'El bundle fuente no contiene runtime.' >&2; exit 65; }

build_log="${build_dir}/build.log"
build_linux() {
    OUTPUT_DIR="${build_dir}/dist" PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/bin/composer \
        bash "${source_dir}/deploy/oracle/scripts/build-release.sh" 2>&1 | tee "${build_log}" >&2
}
run_phase 'Construyendo y comprobando el candidato Linux' build_linux
archive="$(tail -n 1 "${build_log}")"
checksum="${archive}.sha256"
[[ -r "${archive}" && -r "${checksum}" ]] || { echo 'No se produjo candidato y suma.' >&2; exit 1; }
sha_expected="$(awk 'NR == 1 {print $1}' "${checksum}")"
sha_actual="$(sha256sum "${archive}" | awk '{print $1}')"
[[ "${sha_actual}" == "${sha_expected}" ]] || { echo 'Checksum del candidato invalido.' >&2; exit 1; }

release_id="${sha_actual:0:16}"
staging=/srv/hubdigital/staging
target_dir="${staging}/${release_id}"
[[ ! -e "${target_dir}" ]] || { echo 'El staging de esta release ya existe; no se sobrescribe.' >&2; exit 73; }
install -d -m 0755 "${target_dir}"
install -m 0644 "${archive}" "${staging}/$(basename "${archive}")"
install -m 0644 "${checksum}" "${staging}/$(basename "${checksum}")"
run_phase 'Extrayendo el candidato Linux' tar --extract --gzip --file="${archive}" --directory="${target_dir}" --no-same-owner --no-same-permissions
run_phase 'Comprobando la plataforma del candidato' env PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/bin/composer bash "${target_dir}/deploy/oracle/scripts/verify-platform.sh" "${target_dir}"
/usr/bin/php8.4 -l "${target_dir}/config/deposit-storage.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/GestionPrestamosRecepciones/app/Infrastructure/Storage/AlmacenamientoDepositos.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/ConocimientoPortal.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/TextoChat.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/ConversacionBasica.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/ConsultaCatalogoPublico.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/RankingIntencionesChat.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/SenalesIntencionChat.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/CorpusChat.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/DetectorEntidadesChat.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/AnaliticaChat.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/EvaluadorCorpusChat.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Application/UseCases/ConsultarChatBot/TransferenciaConocimientoChat.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/database/migrations/2026_09_26_000005_chat_confidence_observations.php" >/dev/null
for migration in 000006_chat_quality_and_versions 000007_chat_node_drafts 000008_chat_train_vocabulary 000009_chat_trace; do
    /usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/database/migrations/2026_09_26_${migration}.php" >/dev/null
done
/usr/bin/php8.4 -l "${target_dir}/Modules/CatalogoPublico/app/Presentation/Http/Controllers/AdministrarAsistente.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/deploy/oracle/scripts/verify-portal-chat.php" >/dev/null

printf 'candidate=%s\nchecksum=%s\nstaging=%s\nplatform=ok\n' "$(basename "${archive}")" "${sha_actual}" "${target_dir}"
