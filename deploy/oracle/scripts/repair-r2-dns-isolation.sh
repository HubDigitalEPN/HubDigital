#!/usr/bin/env bash
set -euo pipefail

# Reparación operativa sin cambiar la release ni leer credenciales de R2.
[[ "${EUID}" -eq 0 ]] || { echo 'Ejecutar como root.' >&2; exit 64; }
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
oracle_dir="$(cd -- "${script_dir}/.." && pwd)"
legacy=/etc/nginx/conf.d/hubdigital-r2-transport.conf
config=/etc/hubdigital/r2-transport.conf
host_file=/etc/hubdigital/r2-origin-host
unit=/etc/systemd/system/hubdigital-r2-transport.service
dropin=/etc/systemd/system/nginx.service.d/hubdigital.conf
socket=/run/hubdigital/r2-transport/r2.sock
had_legacy=0
if [[ -f "${legacy}" ]]; then
    had_legacy=1
    host="$(sed -nE 's/^[[:space:]]*server ([a-f0-9]{32}(\.eu|\.fedramp)?\.r2\.cloudflarestorage\.com):443;$/\1/p' "${legacy}")"
elif [[ -f "${host_file}" ]]; then
    host="$(cat "${host_file}")"
else
    echo 'Falta el transporte conocido: no se modifica la configuración.' >&2
    exit 66
fi
[[ "${host}" =~ ^[a-f0-9]{32}(\.eu|\.fedramp)?\.r2\.cloudflarestorage\.com$ ]] || {
    echo 'El destino no es un host R2 autorizado.' >&2; exit 65;
}
install -d -m 0700 /var/backups/hubdigital
backup="$(mktemp -d /var/backups/hubdigital/r2-dns.XXXXXXXX)"
files=("${legacy}" "${config}" "${host_file}" "${unit}" "${dropin}")
was_enabled="$(systemctl is-enabled hubdigital-r2-transport.service 2>/dev/null || true)"
was_active="$(systemctl is-active hubdigital-r2-transport.service 2>/dev/null || true)"
for file in "${files[@]}"; do
    if [[ -e "${file}" ]]; then cp -a --parents "${file}" "${backup}/"; fi
done
echo "Respaldo: ${backup}"
rollback() {
    local status=$?
    trap - ERR
    echo 'Fallo: se restaura la configuración y se conserva el respaldo.' >&2
    systemctl stop hubdigital-r2-transport.service || true
    if [[ "${was_enabled}" != enabled ]]; then systemctl disable hubdigital-r2-transport.service || true; fi
    for file in "${files[@]}"; do
        if [[ -e "${backup}${file}" ]]; then cp -a "${backup}${file}" "${file}"; else rm -f -- "${file}"; fi
    done
    systemctl daemon-reload
    if nginx -t; then systemctl reload nginx || true; fi
    if [[ "${was_active}" == active ]]; then systemctl start hubdigital-r2-transport.service || true; fi
    exit "${status}"
}
trap rollback ERR
install -d -m 0750 -o root -g www-data /etc/hubdigital
install -d -m 0755 /etc/systemd/system/nginx.service.d
sed "s/__R2_HOST__/${host}/g" "${oracle_dir}/nginx/r2-transport.conf.template" > "${backup}/candidate.conf"
# Comprobación de aceptación de la configuración concreta en este host.
nginx -t -c "${backup}/candidate.conf"
install -m 0644 "${backup}/candidate.conf" "${config}"
printf '%s' "${host}" > "${host_file}"
chown root:www-data "${host_file}"
chmod 0440 "${host_file}"
install -m 0644 "${oracle_dir}/systemd/hubdigital-r2-transport.service" "${unit}"
install -m 0644 "${oracle_dir}/systemd/nginx-hubdigital.conf" "${dropin}"
systemd-analyze verify "${unit}"
rm -f -- "${legacy}"
nginx -t
systemctl daemon-reload
# Recarga gradual: el frontal sigue atendiendo durante la separación.
systemctl reload nginx
if [[ "${had_legacy}" -eq 1 && -S "${socket}" ]]; then rm -f -- "${socket}"; fi
systemctl enable hubdigital-r2-transport.service
if systemctl restart hubdigital-r2-transport.service; then
    [[ "$(stat -c '%a:%U:%G' "${socket}")" == '600:www-data:www-data' ]]
else
    echo 'Aviso: R2 conserva HTTPS directo; su fallo no detiene la web.' >&2
fi
systemctl is-active nginx
[[ ! -e "${legacy}" ]]
trap - ERR
echo 'Transporte R2 aislado; release de aplicación conservada.'
