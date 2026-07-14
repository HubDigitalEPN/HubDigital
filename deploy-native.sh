#!/bin/bash
# deploy-native.sh — Deploy en el servidor NATIVO (nginx + php-fpm + supervisord).
#
# Úsalo SIEMPRE en lugar de un `git pull` manual. Garantiza que las cachés de
# Laravel (config/route/view) se regeneren tras cada cambio de código y que los
# workers de cola recarguen el código nuevo. Así se evita el bug del
# "procesador de tareas en segundo plano no está activo" causado por config
# cacheada obsoleta o workers corriendo código viejo.
set -euo pipefail

cd "$(dirname "$0")"

echo "════════════════════════════════════════════════"
echo " Hub Digital — Deploy nativo"
echo "════════════════════════════════════════════════"

# ── 1. Traer código ────────────────────────────────────────────
echo "--> [1/6] git pull origin main..."
git pull origin main

# ── 2. Dependencias PHP (producción) ───────────────────────────
echo "--> [2/6] composer install (sin dev, optimizado)..."
composer install --no-dev --optimize-autoloader --no-interaction

# ── 3. Migraciones ─────────────────────────────────────────────
# NUNCA se usa migrate:fresh/refresh: la data de producción es vital.
echo "--> [3/6] php artisan migrate --force..."
php artisan migrate --force

# ── 4. Regenerar cachés (config + route + view) ────────────────
# `optimize` limpia y vuelve a cachear en un solo paso. ESTE es el paso
# que faltaba y provocaba que bootstrap/cache/config.php quedara viejo.
echo "--> [4/6] php artisan optimize (config/route/view cache)..."
php artisan optimize

# ── 5. Recargar workers de cola ────────────────────────────────
# queue:restart señala a los workers vivos que terminen el job actual y
# reinicien, recargando el código nuevo. Complementado con el restart de
# supervisor para un reinicio inmediato y garantizado.
echo "--> [5/6] Recargando workers de cola..."
php artisan queue:restart
if command -v supervisorctl >/dev/null 2>&1; then
    sudo supervisorctl restart hubdigital-worker:* || \
        echo "    ADVERTENCIA: no se pudo reiniciar via supervisor (revisa sudo/permisos). queue:restart ya fue enviado."
else
    echo "    supervisorctl no disponible; se confía en queue:restart."
fi

# ── 6. Verificación ────────────────────────────────────────────
echo "--> [6/6] Verificación post-deploy..."
echo -n "    queue.default = "; php artisan tinker --execute="echo config('queue.default');" 2>/dev/null | tail -1
echo ""
echo -n "    ai.default    = "; php artisan tinker --execute="echo config('ai.default');" 2>/dev/null | tail -1
echo ""
echo "    workers activos:"
ps -o pid,etime,cmd -C php 2>/dev/null | grep "queue:work" | grep -v grep || echo "    ⚠ NO hay workers corriendo — revisa supervisor"

echo ""
echo "════════════════════════════════════════════════"
echo " Deploy nativo completado."
echo "════════════════════════════════════════════════"
