#!/usr/bin/env bash
set -Eeuo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
php_bin="$(command -v "${PHP_BIN:-/usr/bin/php8.4}" || true)"
fpm_bin="$(command -v "${PHP_FPM_BIN:-/usr/sbin/php-fpm8.4}" || true)"
[[ -x "${php_bin}" && -x "${fpm_bin}" ]] || { echo 'Faltan los ejecutables PHP CLI o PHP-FPM.' >&2; exit 66; }
cli_version="$("${php_bin}" -r 'echo PHP_VERSION;')"
fpm_version="$("${fpm_bin}" -v | sed -nE 's/^PHP ([0-9]+\.[0-9]+\.[0-9]+) .*/\1/p' | head -n 1)"
for version in "${cli_version}" "${fpm_version}"; do
    "${php_bin}" -r '$policy = require $argv[1]; exit($policy($argv[2]) ? 0 : 1);' \
        "${script_dir}/php-security-policy.php" "${version}" || {
        echo "PHP2Shell: versión rechazada ${version}. Actualice CLI y FPM a PHP 8.4.27 o 8.5.12 estables, o un parche posterior de esas ramas." >&2
        exit 65
    }
done
if [[ "${1:-}" == --active ]]; then
    [[ ${EUID} -eq 0 ]] || { echo 'La revisión de procesos FPM requiere sudo.' >&2; exit 64; }
    systemctl is-active --quiet php8.4-fpm.service
    master="$(systemctl show php8.4-fpm.service --property=MainPID --value)"
    [[ ${master} =~ ^[1-9][0-9]*$ ]] || exit 65
    expected="$(readlink -f -- "${fpm_bin}")"
    mapfile -t workers < <(pgrep -P "${master}" || true)
    for pid in "${master}" "${workers[@]}"; do
        actual="$(readlink -- "/proc/${pid}/exe")"
        [[ "${actual}" == "${expected}" ]] || {
            echo "PHP2Shell: proceso FPM ${pid} usa otro ejecutable o una versión sustituida. Reinicie php8.4-fpm.service." >&2
            exit 65
        }
    done
fi
printf 'PHP2SHELL_OK CLI=%s FPM=%s CVE-2026-109618 y CVE-2026-109619\n' "${cli_version}" "${fpm_version}"
