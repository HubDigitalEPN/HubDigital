#!/usr/bin/env sh
set -eu

# Variables obligatorias: COMPOSE_FILE, COMPOSE_PROJECT, BACKUP_ROOT,
# BACKUP_ID, BACKUP_PREFIX. PGUSER y PGDATABASE se leen del entorno del
# contenedor PostgreSQL; las credenciales nunca se imprimen.
: "${COMPOSE_FILE:?Falta COMPOSE_FILE}"
: "${COMPOSE_PROJECT:?Falta COMPOSE_PROJECT}"
: "${BACKUP_ROOT:?Falta BACKUP_ROOT}"
: "${BACKUP_ID:?Falta BACKUP_ID}"
: "${BACKUP_PREFIX:?Falta BACKUP_PREFIX}"

umask 077
destino="$BACKUP_ROOT/$BACKUP_ID"
lock="$BACKUP_ROOT/.respaldo-depositos.lock"
mkdir -p "$BACKUP_ROOT"

if ! mkdir "$lock" 2>/dev/null; then
    if [ -f "$lock/pid" ] && kill -0 "$(cat "$lock/pid")" 2>/dev/null; then
        echo "Ya existe un respaldo coordinado activo." >&2
        exit 4
    fi
    mv "$lock" "$lock.abandonado.$(date -u +%Y%m%dT%H%M%SZ)"
    mkdir "$lock"
fi
echo "$$" >"$lock/pid"
echo "$(date -u +%FT%TZ)" >"$lock/iniciado-en"
restaurar_servicios=0
preservar_manifiesto=0
limpiar_y_recuperar() {
    estado=$?
    recuperacion_fallo=0
    if [ "$restaurar_servicios" -eq 1 ]; then
        if ! dc start app worker scheduler nginx >/dev/null 2>&1; then
            echo "Fallo al reiniciar servicios; se requiere recuperacion manual." >&2
            recuperacion_fallo=1
        fi
        if ! dc exec -T app php artisan up >/dev/null 2>&1; then
            echo "Fallo al retirar el modo mantenimiento; se requiere recuperacion manual." >&2
            recuperacion_fallo=1
        fi
    fi
    if [ "$estado" -ne 0 ] || [ "$recuperacion_fallo" -ne 0 ]; then
        if [ -n "${manifesto:-}" ] && [ "$preservar_manifiesto" -eq 0 ]; then
            printf '%s\n' "{\"version_formato\":1,\"id\":\"$BACKUP_ID\",\"creado_en\":\"$(date -u +%FT%TZ)\",\"estado\":\"INCOMPLETO\",\"codigo_fallo\":$estado,\"recuperacion_servicios_fallida\":$recuperacion_fallo}" >"$manifesto"
            chmod 600 "$manifesto"
        fi
    fi
    rm -f "$lock/pid" "$lock/iniciado-en"
    rmdir "$lock" 2>/dev/null || true
    if [ "$recuperacion_fallo" -ne 0 ]; then
        exit 5
    fi
    exit "$estado"
}
trap limpiar_y_recuperar EXIT
trap 'exit 130' HUP INT TERM

mkdir -p "$destino"
manifesto_documentos="$destino/manifiesto-documentos.json"
manifesto="$destino/manifiesto-coordinado.json"
dump="$destino/postgresql.dump"

if [ -s "$manifesto" ] && grep -Eq '"estado"[[:space:]]*:[[:space:]]*"COMPLETO"' "$manifesto"; then
    echo "El respaldo coordinado $BACKUP_ID ya esta COMPLETO y no se sobrescribira." >&2
    preservar_manifiesto=1
    exit 4
fi

dc() { docker compose -p "$COMPOSE_PROJECT" -f "$COMPOSE_FILE" "$@"; }

# Barrera de escritura: tráfico, workers y scheduler quedan detenidos. El
# contenedor efímero posterior solo ejecuta la copia documental.
restaurar_servicios=1
dc exec -T app php artisan down --retry=60 >/dev/null
dc stop nginx worker scheduler >/dev/null

reanudar=""
[ -f "$manifesto_documentos" ] && reanudar="--reanudar"
set +e
dc run --rm --no-deps --user "0:0" --entrypoint php -v "$destino:/evidencia" app artisan depositos:respaldar-documentos \
    --id="$BACKUP_ID" \
    --prefijo-destino="$BACKUP_PREFIX" \
    --salida-manifiesto="/evidencia/manifiesto-documentos.json" \
    $reanudar
resultado_respaldo=$?
set -e
if [ -e "$manifesto_documentos" ]; then
    dc run --rm --no-deps --user "0:0" --entrypoint chown -v "$destino:/evidencia" app \
        "$(id -u):$(id -g)" /evidencia/manifiesto-documentos.json
fi
[ "$resultado_respaldo" -eq 0 ] || exit "$resultado_respaldo"
test -s "$manifesto_documentos"

dc exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' >"$dump"
test -s "$dump"
dump_sha="$(sha256sum "$dump" | cut -d ' ' -f 1)"
docs_sha="$(sha256sum "$manifesto_documentos" | cut -d ' ' -f 1)"
jobs="$(dc exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from jobs"')"
failed_jobs="$(dc exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from failed_jobs"')"
source_environment="${SOURCE_ENVIRONMENT:-codespaces}"
source_instance="${SOURCE_INSTANCE:-$COMPOSE_PROJECT}"
source_database="$(dc exec -T postgres sh -c 'printf %s "$POSTGRES_DB"')"
source_revision="${SOURCE_REVISION:-$(git rev-parse --verify HEAD 2>/dev/null || printf sin-git)}"
for value in "$source_environment" "$source_instance" "$source_database" "$source_revision"; do
    case "$value" in
        ''|*[!A-Za-z0-9._:@/-]*)
            echo 'Los metadatos de origen contienen caracteres no permitidos.' >&2
            exit 65
            ;;
    esac
done

cat >"$manifesto" <<EOF
{"version_formato":1,"id":"$BACKUP_ID","creado_en":"$(date -u +%FT%TZ)","estado":"COMPLETO","origen":{"entorno":"$source_environment","instancia":"$source_instance","base_datos":"$source_database","revision":"$source_revision","capturado_en":"$(date -u +%FT%TZ)"},"postgresql":{"archivo":"postgresql.dump","sha256":"$dump_sha"},"documentos":{"archivo":"manifiesto-documentos.json","sha256":"$docs_sha","prefijo":"$BACKUP_PREFIX"},"colas":{"pendientes":$jobs,"fallidos":$failed_jobs}}
EOF
chmod 600 "$destino"/*

dc start app worker scheduler nginx >/dev/null
dc exec -T app php artisan up >/dev/null
restaurar_servicios=0
printf '%s\n' "$manifesto"
