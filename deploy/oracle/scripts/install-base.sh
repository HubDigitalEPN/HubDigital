#!/usr/bin/env bash
set -euo pipefail

# Ejecutar como root en Ubuntu 24.04 Minimal desde una copia de release ya
# verificada. Instala servicios nativos: evita el daemon Docker y sus imagenes
# permanentes en la Micro. No inicia la aplicacion ni crea secretos.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta este instalador con sudo.' >&2
    exit 64
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
oracle_dir="$(cd -- "${script_dir}/.." && pwd)"

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends \
    ca-certificates certbot curl gnupg jq software-properties-common nginx postgresql-16 postgresql-client-16 \
    poppler-utils tesseract-ocr tesseract-ocr-eng tesseract-ocr-spa openjdk-17-jre-headless \
    sysstat logrotate

# Noble mantiene PHP 8.3 como versión predeterminada; el lock de HubDigital
# incluye Symfony 8, que requiere PHP >= 8.4. El PPA mantenido de PHP publica
# paquetes coinstalables para las LTS actuales. Se añade de forma explícita para
# poder auditar y actualizar PHP 8.4 con apt, sin rebajar dependencias.
LC_ALL=C.UTF-8 add-apt-repository --yes ppa:ondrej/php
apt-get update
apt-get install -y --no-install-recommends \
    composer php8.4-cli php8.4-common php8.4-curl php8.4-fpm php8.4-gd php8.4-intl \
    php8.4-mbstring php8.4-pgsql php8.4-xml php8.4-zip php8.4-bcmath

install -d -m 0750 -o root -g www-data /etc/hubdigital
install -d -m 0755 /srv/hubdigital/releases
install -d -m 0755 /var/lib/hubdigital/migration
install -d -m 0750 -o www-data -g www-data /var/log/php8.4-fpm

install -m 0644 "${oracle_dir}/postgresql/90-hubdigital.conf" /etc/postgresql/16/main/conf.d/90-hubdigital.conf
install -m 0644 "${oracle_dir}/php/99-hubdigital.ini" /etc/php/8.4/fpm/conf.d/99-hubdigital.ini
install -m 0644 "${oracle_dir}/php/99-hubdigital.ini" /etc/php/8.4/cli/conf.d/99-hubdigital.ini
install -m 0644 "${oracle_dir}/php/hubdigital.conf" /etc/php/8.4/fpm/pool.d/hubdigital.conf
# El certificado todavia no existe: se instala primero HTTP para que nginx -t
# sea valido y Certbot pueda resolver HTTP-01. HTTPS se instala despues.
install -m 0644 "${oracle_dir}/nginx/hubdigital-http.conf" /etc/nginx/sites-available/hubdigital
ln -sfn /etc/nginx/sites-available/hubdigital /etc/nginx/sites-enabled/hubdigital
rm -f /etc/nginx/sites-enabled/default

# La VM queda dedicada a HubDigital; evitar el pool www duplicado conserva RAM.
if [[ -f /etc/php/8.4/fpm/pool.d/www.conf ]]; then
    mv /etc/php/8.4/fpm/pool.d/www.conf /etc/php/8.4/fpm/pool.d/www.conf.disabled
fi

install -m 0644 "${oracle_dir}/tmpfiles/hubdigital.conf" /etc/tmpfiles.d/hubdigital.conf
install -m 0644 "${oracle_dir}/systemd/hubdigital-worker.service" /etc/systemd/system/hubdigital-worker.service
install -m 0644 "${oracle_dir}/systemd/hubdigital-schedule.service" /etc/systemd/system/hubdigital-schedule.service
install -m 0644 "${oracle_dir}/systemd/hubdigital-schedule.timer" /etc/systemd/system/hubdigital-schedule.timer
install -m 0644 "${oracle_dir}/systemd/hubdigital-temp-cleanup.service" /etc/systemd/system/hubdigital-temp-cleanup.service
install -m 0644 "${oracle_dir}/systemd/hubdigital-temp-cleanup.timer" /etc/systemd/system/hubdigital-temp-cleanup.timer
install -m 0644 "${oracle_dir}/systemd/run-hubdigital.mount" /etc/systemd/system/run-hubdigital.mount
install -d -m 0755 /etc/systemd/coredump.conf.d
install -m 0644 "${oracle_dir}/systemd/coredump-hubdigital.conf" /etc/systemd/coredump.conf.d/hubdigital.conf
install -d -m 0755 /etc/systemd/system/php8.4-fpm.service.d /etc/systemd/system/postgresql@16-main.service.d /etc/systemd/system/nginx.service.d
install -m 0644 "${oracle_dir}/systemd/php8.4-fpm-hubdigital.conf" /etc/systemd/system/php8.4-fpm.service.d/hubdigital.conf
install -m 0644 "${oracle_dir}/systemd/postgresql-16-main-hubdigital.conf" /etc/systemd/system/postgresql@16-main.service.d/hubdigital.conf
install -m 0644 "${oracle_dir}/systemd/nginx-hubdigital.conf" /etc/systemd/system/nginx.service.d/hubdigital.conf

systemctl daemon-reload
systemctl enable --now run-hubdigital.mount
systemd-tmpfiles --create /etc/tmpfiles.d/hubdigital.conf
systemctl disable --now php8.3-fpm.service 2>/dev/null || true
systemctl enable php8.4-fpm.service
systemctl enable --now hubdigital-temp-cleanup.timer
nginx -t

echo 'Base PHP 8.4 instalada con HTTP para ACME. Cree /etc/hubdigital/hubdigital.env; Cloudflare Tunnel no es parte de esta arquitectura.'
