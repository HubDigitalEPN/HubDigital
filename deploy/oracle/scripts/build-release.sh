#!/usr/bin/env bash
set -euo pipefail

# Construye desde una selección explícita hacia un staging nuevo. No lee ni
# obliga a borrar .env del árbol de trabajo; los archivos no seleccionados no
# pueden entrar al artefacto por accidente.
repo_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../../.." && pwd)"
output_dir="${OUTPUT_DIR:-${repo_dir}/dist}"
stage="$(mktemp -d "${TMPDIR:-/tmp}/hubdigital-release.XXXXXX")"
cleanup() { rm -rf -- "${stage}"; }
trap cleanup EXIT

[[ -d "${repo_dir}/vendor" ]] || { echo 'Falta vendor/: ejecute composer install fuera de Oracle.' >&2; exit 65; }
[[ -f "${repo_dir}/vendor/autoload.php" ]] || { echo 'Falta vendor/autoload.php: la instalación de Composer no está completa.' >&2; exit 65; }
[[ -d "${repo_dir}/public/build" ]] || { echo 'Falta public/build: ejecute npm run build fuera de Oracle.' >&2; exit 65; }
# Los bundles nuevos conservan el commit de main aunque la VM no tenga .git.
source_metadata="${repo_dir}/SOURCE-METADATA.json"
source_manifest="${repo_dir}/SOURCE-MANIFEST.sha256"
source_head=''
if [[ -e "${source_metadata}" || -e "${source_manifest}" ]]; then
    source_head="$(bash "${repo_dir}/deploy/oracle/scripts/verify-source-identity.sh" "${repo_dir}")"
fi
bash "${repo_dir}/deploy/oracle/scripts/verify-platform.sh" "${repo_dir}"

install -d -m 0755 "${stage}"
for directory in app config database lang Modules public resources routes vendor deploy scripts/depositos; do
    if [[ "${directory}" == scripts/depositos && ! -d "${repo_dir}/${directory}" ]]; then continue; fi
    [[ -d "${repo_dir}/${directory}" ]] || { echo "Falta ${directory}/ en el árbol fuente." >&2; exit 65; }
    while IFS= read -r -d '' link; do
        target="$(realpath -e -- "${link}")" || { echo "Enlace roto: ${link}" >&2; exit 65; }
        case "${target}" in
            "${repo_dir}"/*) ;;
            *) echo "Enlace fuera del repositorio: ${link}" >&2; exit 65 ;;
        esac
    done < <(find "${repo_dir}/${directory}" -type l -print0)
    install -d -m 0755 "$(dirname -- "${stage}/${directory}")"
    cp -aL -- "${repo_dir}/${directory}" "${stage}/${directory}"
done
# Los bundles fuente creados en Windows no conservan el bit ejecutable POSIX.
# El artefacto Linux normaliza todos los scripts operativos antes de generar
# el manifiesto, para que las llamadas entre scripts no dependan del origen.
find "${stage}/deploy/oracle/scripts" -maxdepth 1 -type f -name '*.sh' -exec chmod 0755 {} +
# Los módulos conservan sus pruebas junto al código fuente, pero no son parte
# del runtime de producción. Se eliminan sólo del staging antes del manifiesto;
# el árbol de trabajo y las dependencias de desarrollo no se modifican.
find "${stage}/Modules" -type d -name tests -prune -exec rm -rf -- {} +
install -d -m 0755 "${stage}/bootstrap/cache"
install -m 0644 "${repo_dir}/bootstrap/app.php" "${stage}/bootstrap/app.php"
install -m 0644 "${repo_dir}/bootstrap/providers.php" "${stage}/bootstrap/providers.php"
install -m 0644 "${repo_dir}/bootstrap/cache/.gitignore" "${stage}/bootstrap/cache/.gitignore"
for file in artisan composer.json composer.lock modules_statuses.json; do
    [[ -f "${repo_dir}/${file}" ]] || { echo "Falta ${file} en el Ã¡rbol fuente." >&2; exit 65; }
    [[ ! -L "${repo_dir}/${file}" ]] || { echo "No se permiten enlaces en archivo runtime: ${file}" >&2; exit 65; }
    install -m 0644 "${repo_dir}/${file}" "${stage}/${file}"
done
chmod 0755 "${stage}/artisan"
if [[ -n "${source_head}" ]]; then
    install -m 0644 "${source_metadata}" "${stage}/SOURCE-METADATA.json"
    install -m 0644 "${source_manifest}" "${stage}/SOURCE-MANIFEST.sha256"
fi


remaining_link="$(find "${stage}" -type l -print -quit)"
[[ -z "${remaining_link}" ]] || { echo "El staging conserva un enlace: ${remaining_link}" >&2; exit 65; }

# Rechaza secretos, cachés de configuración y respaldos incluso si aparecieron
# dentro de un directorio permitido. Se imprimen sólo rutas, nunca contenido.
forbidden="$(find "${stage}" -type f \( \
    -name '.env' -o -name '.env.*' -o -name 'config.php' -path '*/bootstrap/cache/*' -o \
    -name '*.key' -o -name '*.pem' -o -name '*.p12' -o -name '*.pfx' -o \
    -name '*.dump' -o -name '*.sqlite' -o -name '*.sqlite3' -o \
    -name '*backup*' -o -name '*respaldo*' -o -name 'php-error.log' \
\) -print -quit)"
[[ -z "${forbidden}" ]] || { echo "El staging contiene un archivo prohibido: ${forbidden#"${stage}"/}" >&2; exit 65; }

# El lockfile actual incluye este SQL de Sail para crear una base de pruebas de
# PostgreSQL. No es un dump ni se ejecuta en el despliegue. Cualquier otro SQL
# obliga a revisión explícita: no se permite introducir respaldos por accidente.
while IFS= read -r -d '' sql_file; do
    relative_sql="${sql_file#"${stage}"/}"
    [[ "${relative_sql}" == 'vendor/laravel/sail/database/pgsql/create-testing-database.sql' ]] || {
        echo "El staging contiene SQL no autorizado: ${relative_sql}" >&2
        exit 65
    }
done < <(find "${stage}" -type f -name '*.sql' -print0)

manifest="${stage}/RELEASE-MANIFEST.sha256"
(
    cd "${stage}"
    find . -type f ! -name RELEASE-MANIFEST.sha256 -print0 | LC_ALL=C sort -z | xargs -0 sha256sum
) > "${manifest}"
content_sha="$(sha256sum "${manifest}" | awk '{print $1}')"
head="${source_head}"
if [[ -z "${head}" ]]; then
    # Compatibilidad con releases anteriores; no se altera su restauracion.
    head="$(git -C "${repo_dir}" rev-parse --short=12 HEAD 2>/dev/null || printf 'sin-git')"
fi
release_id="${head}-${content_sha:0:12}"
metadata="${stage}/RELEASE-METADATA.json"
printf '{"git_head":"%s","content_sha256":"%s","php_version":"%s"}\n' \
    "${head}" "${content_sha}" "$("${PHP_BIN:-php}" -r 'echo PHP_VERSION;')" > "${metadata}"
if [[ -n "${source_head}" ]]; then
    jq --slurpfile source "${source_metadata}" '
        . + {git_branch: $source[0].git_branch, repository: $source[0].repository,
             git_tree: $source[0].git_tree, source_manifest_sha256: $source[0].source_manifest_sha256}
    ' "${metadata}" > "${stage}/metadata.tmp"
    mv -- "${stage}/metadata.tmp" "${metadata}"
fi

mkdir -p "${output_dir}"
archive="${output_dir}/hubdigital-${release_id}.tar.gz"
[[ ! -e "${archive}" ]] || { echo "Ya existe ${archive}; no se sobrescribe." >&2; exit 73; }
tar --create --gzip --file="${archive}" --directory="${stage}" \
    --files-from <(cd "${stage}" && find . -mindepth 1 -maxdepth 1 -printf '%P\n' | LC_ALL=C sort)
sha256sum "${archive}" > "${archive}.sha256"
cp -- "${manifest}" "${archive}.contents.sha256"
printf '%s\n' "${archive}"
