#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la instalacion HTTPS con sudo.' >&2
    exit 64
fi

release_dir="$(readlink -f /srv/hubdigital/current 2>/dev/null || true)"
source "$(dirname -- "${BASH_SOURCE[0]}")/deployment-host.sh"
[[ -n "${release_dir}" && -f "${release_dir}/artisan" ]] || { echo 'Falta una release activa.' >&2; exit 66; }
[[ -s /etc/letsencrypt/live/${deployment_hostname}/fullchain.pem && -s /etc/letsencrypt/live/${deployment_hostname}/privkey.pem ]] || {
    echo "Emite primero el certificado para ${deployment_hostname}." >&2
    exit 65
}

target=/etc/nginx/sites-available/hubdigital
backup="$(mktemp /tmp/hubdigital-nginx.XXXXXX)"
cleanup() { rm -f -- "${backup}"; }
trap cleanup EXIT
cp -a -- "${target}" "${backup}"
install_nginx_for_host "${release_dir}/deploy/oracle/nginx/hubdigital.conf" "${target}"
if ! nginx -t; then
    install -m 0644 "${backup}" "${target}"
    nginx -t
    echo 'La configuracion HTTPS fue revertida.' >&2
    exit 1
fi
systemctl reload nginx
systemctl enable --now certbot.timer
systemctl is-active --quiet certbot.timer
curl --fail --silent --show-error --max-time 15 \
    --resolve "${deployment_hostname}:443:127.0.0.1" "https://${deployment_hostname}/depositos" >/dev/null
trap - EXIT
rm -f -- "${backup}"
echo 'HTTPS directo instalado; certificado y timer de renovacion activos.'
