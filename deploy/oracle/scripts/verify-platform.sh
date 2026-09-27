#!/usr/bin/env bash
set -euo pipefail

# Comprueba el lock instalado; nunca resuelve dependencias ni ignora requisitos.
repo_dir="${1:-}"
[[ -n "${repo_dir}" && -f "${repo_dir}/composer.json" && -d "${repo_dir}/vendor" ]] || {
    echo 'Uso: verify-platform.sh /ruta/a/release-con-vendor' >&2
    exit 64
}
php_bin="${PHP_BIN:-php}"
composer_bin="${COMPOSER_BIN:-$(command -v composer || true)}"
[[ -x "${php_bin}" || "${php_bin}" == php ]] || { echo "No se encontró ${php_bin}." >&2; exit 66; }
[[ -n "${composer_bin}" && -r "${composer_bin}" ]] || { echo 'No se encontró Composer.' >&2; exit 66; }

for runtime_file in \
    artisan \
    vendor/autoload.php \
    vendor/livewire/flux/dist/manifest.json \
    public/build/manifest.json \
    resources/bin/hubdigital-pdf-signature.jar \
    deploy/oracle/scripts/verify-deposit-pdf.php \
    deploy/oracle/scripts/verify-migration-classes.php; do
    [[ -f "${repo_dir}/${runtime_file}" ]] || {
        echo "Falta archivo runtime obligatorio: ${runtime_file}." >&2
        exit 65
    }
done

# Este archivo se ejecuta ya durante staging, antes de detener la aplicacion.
# Asi una VM antigua sin runtime documental no deja una release a medio preparar.
for binary in java qpdf; do
    command -v "${binary}" >/dev/null 2>&1 || {
        echo "Falta ${binary} para validar depositos. Instale openjdk-17-jre-headless y qpdf antes del staging." >&2
        exit 65
    }
done

"${php_bin}" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' || {
    echo 'HubDigital bloqueado requiere PHP >= 8.4.' >&2
    exit 65
}
php_modules="$("${php_bin}" -m)"
for extension in curl gd intl mbstring openssl pdo_pgsql xml zip bcmath; do
    grep -Fxq "${extension}" <<<"${php_modules}" || { echo "Falta la extensión PHP ${extension}." >&2; exit 65; }
done
(
    cd "${repo_dir}"
    "${php_bin}" "${composer_bin}" check-platform-reqs --no-dev --no-interaction
)

# Cargar clases detecta incompatibilidades de herencia antes de poner la
# aplicación en mantenimiento; no ejecuta up/down ni modifica PostgreSQL.
"${php_bin}" "${repo_dir}/deploy/oracle/scripts/verify-migration-classes.php"
