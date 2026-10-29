#!/usr/bin/env bash
set -euo pipefail

[[ "$EUID" == 0 ]] || { echo 'Ejecute con sudo.' >&2; exit 64; }
release_id="${1:-}"
[[ "$release_id" =~ ^[0-9a-f]{16}$ ]] || exit 64
record="/var/lib/hubdigital/migration/transferencia-${release_id}.txt"
[[ -f "$record" ]] || exit 0
release="/srv/hubdigital/releases/${release_id}"
[[ "$(readlink -f /srv/hubdigital/current)" == "$release" ]]
[[ ! -e "$release/storage/framework/down" ]]
jq -e --arg id "$release_id" '.release_id == $id' "$release/RELEASE-STATUS.json" >/dev/null
mapfile -t fields < "$record"
[[ "${#fields[@]}" -ge 4 ]]
environment="${fields[0]}"
directory="${fields[1]}"
if [[ "$environment" == PROD ]]; then
    # La activacion generica precede a la aceptacion final de produccion.
    jq -e --arg id "$release_id" '.environment == "prod" and .release_id == $id and .validation_mode == false' \
        "/var/lib/hubdigital/migration/production-activation-${release_id}.json" >/dev/null
else
    [[ "$environment" == DEV ]]
fi
root=/home/ubuntu/TEMP-BORRAR
[[ ! -L "$root" && "$(dirname -- "$directory")" == "$root" ]]
package="$(basename -- "$directory")"
[[ "$package" =~ ^[a-z0-9-]+$ ]]
[[ ! -L "$directory" && "$(readlink -f -- "$directory")" == "$directory" ]]
for name in "${fields[@]:2}"; do
    case "$name" in
        "$package-AMBOS.tar.gz"|"$package-DEV.sha256"|"$package-DEV-cloudshell-vm.sh"|"$package-DEV-hubdigital.env"|"$package-PROD-produccion-vm.sh") ;;
        *) echo 'Registro de transferencia inesperado; se conserva la carpeta.' >&2; exit 65 ;;
    esac
done
shopt -s nullglob dotglob
for file in "$directory"/*; do
    [[ -f "$file" && ! -L "$file" ]] || { echo 'Temporal con enlaces o subcarpetas; no se elimina.' >&2; exit 65; }
    found=0
    for name in "${fields[@]:2}"; do [[ "${file##*/}" != "$name" ]] || found=1; done
    [[ "$found" == 1 ]] || { echo 'Hay archivos ajenos; se conserva TEMP-BORRAR.' >&2; exit 65; }
done
for name in "${fields[@]:2}"; do rm -f -- "$directory/$name"; done
rmdir -- "$directory"
rm -f -- "$record"
if rmdir -- "$root" 2>/dev/null; then
    echo 'TEMP-BORRAR eliminada junto con los archivos temporales del despliegue.'
else
    echo 'Carpeta del paquete eliminada; TEMP-BORRAR conserva otras transferencias pendientes.'
fi
