#!/usr/bin/env bash
set -euo pipefail

# Segunda fase intencional: una release ya validada se activa en el origen
# directo. El scheduler permanece separado y no existe dependencia de Tunnel.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la activacion con sudo.' >&2
    exit 64
fi

release_id="${1:-}"
[[ "${release_id}" =~ ^[0-9a-f]{16}$ ]] || {
    echo 'Uso: sudo activate-release.sh <id-de-16-hex>' >&2
    exit 64
}
release_dir="/srv/hubdigital/releases/${release_id}"
state_file="/var/lib/hubdigital/migration/release-${release_id}.json"
env_file=/etc/hubdigital/hubdigital.env
[[ -f "${release_dir}/artisan" && -r "${state_file}" && -r "${env_file}" ]] || {
    echo 'No existe una release preparada con su estado y entorno protegido.' >&2
    exit 66
}
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
artisan() {
    systemd-run --quiet --wait --collect --pipe \
        --property=User=www-data --property=Group=www-data \
        --property=EnvironmentFile="${env_file}" --working-directory="${release_dir}" \
        /usr/bin/php8.4 artisan "$@"
}
[[ "$(read_env HUBDIGITAL_VALIDATION_MODE)" == true && "$(read_env MAIL_MAILER)" == smtp && "$(read_env HUBDIGITAL_ALLOW_AUTH_EMAILS)" == true ]] || {
    echo 'La activacion exige validacion activa y SMTP limitado a correos de alta.' >&2
    exit 65
}
validation_queue="$(read_env HUBDIGITAL_VALIDATION_QUEUE)"
[[ "${validation_queue}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$ && "${validation_queue}" != default ]] || {
    echo 'La activacion exige HUBDIGITAL_VALIDATION_QUEUE dedicada y distinta de default.' >&2
    exit 65
}
for unit in hubdigital-worker.service hubdigital-schedule.service hubdigital-schedule.timer cloudflared-hubdigital.service; do
    systemctl is-active --quiet "${unit}" && {
        echo "Debe detenerse antes de activar: ${unit}" >&2
        exit 65
    }
done

pre_activation_state="$(jq -er '.estado' "${state_file}")"
case "${pre_activation_state}" in
    validado_en_mantenimiento|instalacion_nueva_validada) ;;
    *)
        echo 'La release no esta preparada por una restauracion validada ni por una instalacion nueva validada.' >&2
        exit 65
        ;;
esac
"${release_dir}/deploy/oracle/scripts/verify-release-state.sh" "${release_id}" "${pre_activation_state}"
if ! artisan migrate:status --pending=1 --no-interaction; then
    echo 'La release conserva migraciones pendientes; no se puede activar hasta aplicarlas.' >&2
    exit 65
fi
"${release_dir}/deploy/oracle/scripts/inspect-restored-state.sh" "${release_dir}"
assert_validation_queue_empty() {
    local db_name db_user db_password pgpass escaped_password pending
    db_name="$(read_env DB_DATABASE)"
    db_user="$(read_env DB_USERNAME)"
    db_password="$(read_env DB_PASSWORD)"
    [[ "${db_name}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,44}$ && "${db_user}" =~ ^[A-Za-z_][A-Za-z0-9_]{0,62}$ && -n "${db_password}" ]] || {
        echo 'Configuracion PostgreSQL invalida.' >&2
        exit 65
    }
    pgpass="$(mktemp /run/hubdigital/pgpass.activate.XXXXXX)"
    chmod 0600 "${pgpass}"
    # El fallo de psql no puede dejar una contraseña temporal en /run. El trap
    # se mantiene hasta que la consulta termine y cubre errores, interrupciones
    # y salidas por `set -e` dentro de esta función.
    cleanup_activate_pgpass() { rm -f -- "${pgpass}"; }
    trap cleanup_activate_pgpass EXIT
    escaped_password="$(printf '%s' "${db_password}" | sed -e 's/\\/\\\\/g' -e 's/:/\\:/g')"
    printf '127.0.0.1:5432:%s:%s:%s\n' "${db_name}" "${db_user}" "${escaped_password}" > "${pgpass}"
    # psql no interpola :'<variable>' cuando la consulta se pasa con --command.
    # Por stdin, el trap ya instalado elimina pgpass tambien ante SQL o conexion fallidos.
    pending="$(printf '%s\n' "SELECT count(*) FROM jobs WHERE queue = :'validation_queue';" | PGPASSFILE="${pgpass}" psql --no-psqlrc --set=ON_ERROR_STOP=1 --tuples-only --no-align --quiet --host=127.0.0.1 --username="${db_user}" --dbname="${db_name}" --set="validation_queue=${validation_queue}")"
    cleanup_activate_pgpass
    trap - EXIT
    [[ "${pending}" =~ ^[0-9]+$ && "${pending}" -eq 0 ]] || {
        echo 'La cola de validacion contiene trabajos restaurados; cuarentenelos o reviselos antes de iniciar worker.' >&2
        exit 65
    }
}
assert_validation_queue_empty
"${release_dir}/deploy/oracle/scripts/update-service-units.sh" "${release_dir}"
ln -sfn "${release_dir}" /srv/hubdigital/current
nginx_source="${release_dir}/deploy/oracle/nginx/hubdigital-http.conf"
if [[ -s /etc/letsencrypt/live/dev.labinvepn.org/fullchain.pem && -s /etc/letsencrypt/live/dev.labinvepn.org/privkey.pem ]]; then
    nginx_source="${release_dir}/deploy/oracle/nginx/hubdigital.conf"
fi
install -m 0644 "${nginx_source}" /etc/nginx/sites-available/hubdigital
ln -sfn /etc/nginx/sites-available/hubdigital /etc/nginx/sites-enabled/hubdigital
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable php8.4-fpm nginx hubdigital-worker
if ! systemctl restart php8.4-fpm nginx; then
    echo 'Fallo al reiniciar FPM o nginx; la release queda en mantenimiento y no se inician escritores.' >&2
    exit 1
fi
if [[ "${nginx_source}" == *hubdigital.conf ]]; then
    local_check=(curl --fail --silent --show-error --max-time 15 --resolve dev.labinvepn.org:443:127.0.0.1 https://dev.labinvepn.org/depositos)
else
    local_check=(curl --fail --silent --show-error --max-time 15 --resolve dev.labinvepn.org:80:127.0.0.1 http://dev.labinvepn.org/depositos)
fi
if ! artisan up --no-interaction || ! "${local_check[@]}" >/dev/null; then
    artisan down --retry=60 --no-interaction || true
    echo 'Fallo de comprobacion local; no se inician worker ni scheduler.' >&2
    exit 1
fi
if ! systemctl restart hubdigital-worker.service; then
    artisan down --retry=60 --no-interaction || true
    echo 'Fallo al activar worker de validacion; scheduler permanece detenido.' >&2
    exit 1
fi
systemctl is-active --quiet hubdigital-worker.service
! systemctl is-active --quiet hubdigital-schedule.service
! systemctl is-active --quiet hubdigital-schedule.timer

verification_failed=0
echo
echo 'Verificacion final automatica:'

current_target="$(readlink -f /srv/hubdigital/current 2>/dev/null || true)"
if [[ "${current_target}" == "${release_dir}" ]]; then
    echo "OK current: ${current_target}"
else
    echo "NO OK current: se esperaba ${release_dir}, se obtuvo ${current_target:-sin destino}." >&2
    verification_failed=1
fi

check_public_url() {
    local url="$1" status
    if ! status="$(curl --silent --show-error --location --max-time 20 \
        --output /dev/null --write-out '%{http_code}' "${url}" 2>&1)"; then
        echo "NO OK ${url}: curl fallo: ${status}." >&2
        return 1
    fi
    if [[ "${status}" != 200 ]]; then
        echo "NO OK ${url}: respondio HTTP ${status}; se esperaba HTTP 200." >&2
        return 1
    fi
    echo "OK ${url}: HTTP ${status}"
}

check_public_url 'https://dev.labinvepn.org/' || verification_failed=1
check_public_url 'https://dev.labinvepn.org/depositos' || verification_failed=1

# Comprueba el artefacto Java de ESTA release y el camino real de admisión PDF
# de Laravel. La autoprueba firma, altera y rechaza PDFs sintéticos; la prueba
# PHP invoca el inspector y validador Java sin tocar R2.
signature_jar="${release_dir}/resources/bin/hubdigital-pdf-signature.jar"
if [[ ! -r "${signature_jar}" ]] || ! command -v java >/dev/null 2>&1; then
    echo 'NO OK Java PDF: falta el JAR de la release o el runtime Java.' >&2
    verification_failed=1
elif ! java_result="$(runuser -u www-data -- java -jar "${signature_jar}" selftest)"; then
    echo 'NO OK Java PDF: fallo la autoprueba criptografica.' >&2
    verification_failed=1
elif ! jq -e '.status == "firmado" and .cryptographically_valid == true' >/dev/null 2>&1 <<<"${java_result}"; then
    echo "NO OK Java PDF: la autoprueba no confirmo la firma valida: ${java_result}" >&2
    verification_failed=1
else
    echo 'OK Java PDF: JAR de la release ejecutado; firma valida, alteracion, PDF sin firma, PDF activo y PDF danado comprobados.'
fi

if ! pdf_result="$(systemd-run --quiet --wait --collect --pipe \
    --property=User=www-data --property=Group=www-data \
    --property=EnvironmentFile="${env_file}" --working-directory="${release_dir}" \
    /usr/bin/php8.4 "${release_dir}/deploy/oracle/scripts/verify-deposit-pdf.php" 2>&1)"; then
    echo "NO OK solucion PDF depositos: ${pdf_result}" >&2
    verification_failed=1
else
    echo "${pdf_result}"
fi

if ! chat_result="$(systemd-run --quiet --wait --collect --pipe \
    --property=User=www-data --property=Group=www-data \
    --property=EnvironmentFile="${env_file}" --working-directory="${release_dir}" \
    /usr/bin/php8.4 "${release_dir}/deploy/oracle/scripts/verify-portal-chat.php" 2>&1)"; then
    echo "NO OK asistente del portal: ${chat_result}" >&2
    verification_failed=1
else
    echo "${chat_result}"
fi

for unit in hubdigital-worker.service php8.4-fpm.service nginx.service; do
    if systemctl is-active --quiet "${unit}"; then
        echo "OK ${unit}: active"
    else
        echo "NO OK ${unit}: estado $(systemctl is-active "${unit}" 2>/dev/null || true); se esperaba active." >&2
        verification_failed=1
    fi
done

for unit in hubdigital-schedule.service hubdigital-schedule.timer cloudflared-hubdigital.service; do
    if systemctl is-active --quiet "${unit}"; then
        echo "NO OK ${unit}: esta active y debe permanecer detenido durante la validacion." >&2
        verification_failed=1
    else
        echo "OK ${unit}: detenido"
    fi
done

if [[ "${verification_failed}" -ne 0 ]]; then
    systemctl stop hubdigital-worker.service || true
    artisan down --retry=60 --no-interaction || true
    echo 'Verificacion final NO OK: la aplicacion volvio a mantenimiento y el worker fue detenido.' >&2
    exit 1
fi

jq --arg activated_at "$(date --utc +%FT%TZ)" --arg worker_queue "${validation_queue}" \
    '. + {activado_local_en:$activated_at, worker_queue:$worker_queue, estado:"activo_local_validacion"}' "${state_file}" > "${state_file}.tmp"
chmod 0600 "${state_file}.tmp"
mv "${state_file}.tmp" "${state_file}"
echo 'Verificacion final OK: release, URLs publicas, servicios, Java, admision PDF y asistente del portal en el estado esperado.'
echo "Release activa en el origen directo: ${release_id}. Worker limitado a ${validation_queue}; scheduler detenido."

echo
echo 'Aplicando politica automatica de retencion...'
if ! "${release_dir}/deploy/oracle/scripts/cleanup-old-releases.sh" --apply; then
    echo 'ADVERTENCIA: la release esta activa, pero la limpieza automatica fallo. Ejecute el script manual en modo simulacion para diagnosticar.' >&2
fi
