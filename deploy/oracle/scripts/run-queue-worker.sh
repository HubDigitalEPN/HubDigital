#!/usr/bin/env bash
set -euo pipefail

if [[ "${HUBDIGITAL_VALIDATION_MODE:-true}" == true ]]; then
    worker_queue="${HUBDIGITAL_VALIDATION_QUEUE:-validation}"
    [[ "${worker_queue}" != default ]] || { echo 'La validación no consume trabajos operativos.' >&2; exit 65; }
else
    worker_queue=default
fi
[[ "${worker_queue}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$ ]] || { echo 'Nombre de cola inválido.' >&2; exit 65; }
exec /usr/bin/php8.4 artisan queue:work database --queue="${worker_queue}" \
    --sleep=1 --tries=3 --backoff=30,120,300 --timeout=300 --memory=192 --max-time=900 --no-interaction
