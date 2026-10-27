#!/usr/bin/env bash
set -euo pipefail

# Verifica el montaje y las tres rutas de limpieza sin almacenar un documento:
# salida normal, salida con error y directorio huérfano atendido por tmpfiles.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la verificación con sudo.' >&2
    exit 64
fi

root=/run/hubdigital
document_root="${root}/document-processing"
[[ "$(findmnt --noheadings --output TARGET --mountpoint "${root}" | tr -d ' ')" == "${root}" ]] || {
    echo '/run/hubdigital no tiene un montaje temporal dedicado.' >&2
    exit 65
}
[[ "$(findmnt --noheadings --output FSTYPE --target "${root}" | tr -d ' ')" == tmpfs ]] || {
    echo '/run/hubdigital no está montado en tmpfs.' >&2
    exit 65
}
available_bytes="$(findmnt --bytes --noheadings --output AVAIL --target "${root}" | tr -d ' ')"
[[ "${available_bytes}" =~ ^[0-9]+$ && "${available_bytes}" -ge 117440512 ]] || {
    echo '/run/hubdigital no conserva 48 MiB de trabajo mas 64 MiB de margen.' >&2
    exit 65
}
for directory in "${document_root}" "${root}/livewire-tmp" "${root}/php-upload" "${root}/php-tmp" "${root}/nginx-client" "${root}/nginx-fastcgi"; do
    [[ -d "${directory}" ]] || { echo "Falta ${directory}." >&2; exit 66; }
done

normal="$(mktemp -d "${document_root}/health-normal.XXXXXX")"
rmdir "${normal}"
[[ ! -e "${normal}" ]] || { echo 'No se limpió el temporal normal.' >&2; exit 1; }

error="$(mktemp -d "${document_root}/health-error.XXXXXX")"
(
    trap 'rmdir "${error}"' EXIT
    false
) || true
[[ ! -e "${error}" ]] || { echo 'No se limpió el temporal de error.' >&2; exit 1; }

orphan="$(mktemp -d "${document_root}/health-orphan.XXXXXX")"
cleanup_config="$(mktemp "${root}/tmpfiles-health.XXXXXX")"
trap 'rm -f -- "${cleanup_config}"' EXIT
printf 'R %s\n' "${orphan}" > "${cleanup_config}"
systemd-tmpfiles --remove "${cleanup_config}"
[[ ! -e "${orphan}" ]] || { echo 'El limpiador no retiró el temporal huérfano.' >&2; exit 1; }

# Prueba la semantica real de edad de systemd-tmpfiles en una raiz aislada.
# No se aplica una regla de prueba a operaciones reales.
ttl_root="$(mktemp -d "${document_root}/health-ttl.XXXXXX")"
ttl_active="${ttl_root}/activa"
ttl_expired="${ttl_root}/vencida"
mkdir -m 0700 "${ttl_active}" "${ttl_expired}"
touch "${ttl_active}/marca" "${ttl_expired}/marca"
ttl_config="$(mktemp "${root}/tmpfiles-ttl-health.XXXXXX")"
trap 'rm -f -- "${cleanup_config}" "${ttl_config}"; rm -rf -- "${ttl_root}"' EXIT
printf 'd %s 0700 www-data www-data 2s\n' "${ttl_root}" > "${ttl_config}"
# Supera dos marcas de reloj completas para evitar falsos negativos por el
# redondeo de segundos que usa systemd-tmpfiles al evaluar la antigüedad.
sleep 4
touch "${ttl_active}/marca"
systemd-tmpfiles --clean "${ttl_config}"
[[ -d "${ttl_active}" && ! -e "${ttl_expired}" ]] || {
    echo 'La regla de antiguedad no preservo la operacion activa o no limpio el resto vencido.' >&2
    exit 1
}
rm -rf -- "${ttl_root}"

cli_tmp="$(runuser -u www-data -- /usr/bin/php8.4 -r 'echo sys_get_temp_dir();')"
[[ "${cli_tmp}" == "${root}/php-tmp" ]] || { echo "PHP CLI usa ${cli_tmp}, no ${root}/php-tmp." >&2; exit 65; }
# Consumir toda la salida evita SIGPIPE del productor con grep -q y pipefail.
# Se conserva el rechazo tanto de una ruta ausente como de un daemon que falla.
php-fpm8.4 -tt 2>&1 | grep -F 'php_admin_value[sys_temp_dir] = /run/hubdigital/php-tmp' >/dev/null || {
    echo 'PHP-FPM no tiene sys_temp_dir efectivo bajo /run.' >&2
    exit 65
}
systemctl is-active --quiet hubdigital-temp-cleanup.timer
systemctl is-enabled --quiet hubdigital-temp-cleanup.timer
nginx -T 2>&1 | grep -F 'client_body_temp_path /run/hubdigital/nginx-client' >/dev/null || {
    echo 'Nginx no tiene buffer de cliente bajo /run.' >&2
    exit 65
}
echo 'Temporales comprobados: tmpfs, limpieza normal/error/huérfano y CLI/FPM/nginx.'
