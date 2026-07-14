#!/bin/sh
set -e

APP=/var/www/html

echo "[entrypoint] Inicializando bootstrap/cache..."
chown -R www-data:www-data "${APP}/bootstrap/cache"
chmod -R 775 "${APP}/bootstrap/cache"

echo "[entrypoint] Asegurando estructura de storage..."
mkdir -p \
    "${APP}/storage/app/private/actas" \
    "${APP}/storage/app/private/documentos-identidad" \
    "${APP}/storage/app/private/firmas-investigador" \
    "${APP}/storage/app/private/livewire-tmp" \
    "${APP}/storage/app/public/depositos" \
    "${APP}/storage/framework/cache/data" \
    "${APP}/storage/framework/sessions" \
    "${APP}/storage/framework/testing" \
    "${APP}/storage/framework/views" \
    "${APP}/storage/logs"

echo "[entrypoint] Iniciando PHP-FPM..."
exec "$@"
