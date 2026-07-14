#!/bin/bash
# deploy.sh — Primer deploy: migra datos de PostgreSQL nativo a Docker
# Para deploys posteriores, comentar el bloque de dump/restore.
set -euo pipefail

echo "════════════════════════════════════════════════"
echo " Hub Digital — Deploy inicial con Docker"
echo "════════════════════════════════════════════════"

# ── PASO 1: Dump de PostgreSQL nativo ──────────────────────────
# Comentar este bloque en deploys posteriores al primero
echo "--> [1/7] Haciendo dump de PostgreSQL nativo..."
pg_dump -h 127.0.0.1 -U admin -d hubdigital > /tmp/hubdigital_dump.sql
echo "    Dump generado: $(du -sh /tmp/hubdigital_dump.sql | cut -f1)"

# ── PASO 2: Detener nginx nativo (libera el puerto 80) ─────────
echo "--> [2/7] Deteniendo nginx nativo..."
sudo systemctl stop nginx || true
sudo systemctl disable nginx || true

# ── PASO 3: Actualizar código ──────────────────────────────────
echo "--> [3/7] Actualizando código (git pull)..."
git pull origin main

# ── PASO 4: Build de imágenes ──────────────────────────────────
echo "--> [4/7] Construyendo imágenes Docker..."
docker compose build --no-cache

# ── PASO 5: Levantar servicios ─────────────────────────────────
echo "--> [5/7] Levantando servicios..."
docker compose up -d

echo "    Esperando que postgres esté listo..."
attempt=0
until docker compose exec -T postgres pg_isready -U admin -d hubdigital 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "ERROR: postgres no respondió en tiempo. Logs:"
        docker compose logs postgres --tail=30
        exit 1
    fi
    echo "    Intento $attempt/30..."
    sleep 2
done

# ── PASO 6: Restaurar dump en el contenedor de postgres ────────
# Comentar este bloque en deploys posteriores al primero
echo "--> [6/7] Restaurando datos en el contenedor postgres..."
docker compose exec -T postgres psql -U admin -d hubdigital < /tmp/hubdigital_dump.sql
echo "    Restauración completada."

# ── PASO 7: Artisan ────────────────────────────────────────────
echo "--> [7/7] Ejecutando comandos de Laravel..."

echo "    Esperando que app esté lista..."
attempt=0
until docker compose exec -T app php -r "exit(0);" 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 20 ]; then
        echo "ERROR: App no respondió. Logs:"
        docker compose logs app --tail=30
        exit 1
    fi
    sleep 3
done

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache

# ── Estado final ───────────────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════"
echo " Estado de los contenedores:"
echo "════════════════════════════════════════════════"
docker compose ps

echo ""
echo "════════════════════════════════════════════════"
echo " Verificación de la app:"
echo "════════════════════════════════════════════════"
sleep 3
curl -sI http://localhost/up | head -5 || echo "ADVERTENCIA: curl falló — revisa docker compose logs nginx"
echo ""
echo "Deploy completado."
