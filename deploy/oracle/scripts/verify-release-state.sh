#!/usr/bin/env bash
set -euo pipefail

# Comprueba que la release preparada, su manifiesto y el estado protegido son
# la misma identidad antes de activar procesos que puedan escribir en la base.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la verificacion de release con sudo.' >&2
    exit 64
fi

release_id="${1:-}"
expected_state="${2:-}"
[[ "${release_id}" =~ ^[0-9a-f]{16}$ && -n "${expected_state}" ]] || {
    echo 'Uso: sudo verify-release-state.sh <id-de-16-hex> <estado-esperado>' >&2
    exit 64
}

release_dir="/srv/hubdigital/releases/${release_id}"
state_file="/var/lib/hubdigital/migration/release-${release_id}.json"
manifest_file="${release_dir}/RELEASE-MANIFEST.sha256"
metadata_file="${release_dir}/RELEASE-METADATA.json"
[[ -f "${release_dir}/artisan" && -r "${state_file}" && -f "${manifest_file}" && -f "${metadata_file}" ]] || {
    echo 'Falta release, manifiesto, metadatos o estado protegido.' >&2
    exit 66
}

state_id="$(jq -er '.release_id' "${state_file}")"
state_status="$(jq -er '.estado' "${state_file}")"
archive_sha="$(jq -er '.release_sha256' "${state_file}")"
state_manifest_sha="$(jq -er '.manifest_sha256' "${state_file}")"
state_content_sha="$(jq -er '.content_sha256' "${state_file}")"
metadata_content_sha="$(jq -er '.content_sha256' "${metadata_file}")"
current_manifest_sha="$(sha256sum "${manifest_file}" | awk '{print $1}')"

[[ "${state_id}" == "${release_id}" && "${state_status}" == "${expected_state}" ]] || {
    echo 'El estado protegido no corresponde a la fase esperada de esta release.' >&2
    exit 65
}
[[ "${archive_sha}" =~ ^[0-9a-f]{64}$ && "${archive_sha:0:16}" == "${release_id}" ]] || {
    echo 'La identidad del archivo de release no coincide con su identificador.' >&2
    exit 65
}
[[ "${state_manifest_sha}" =~ ^[0-9a-f]{64}$ && "${state_manifest_sha}" == "${current_manifest_sha}" ]] || {
    echo 'El manifiesto de la release no coincide con el estado protegido.' >&2
    exit 65
}
[[ "${state_content_sha}" =~ ^[0-9a-f]{64}$ && "${state_content_sha}" == "${metadata_content_sha}" ]] || {
    echo 'Los metadatos de contenido no coinciden con el estado protegido.' >&2
    exit 65
}
(cd "${release_dir}" && sha256sum --check --status "${manifest_file}") || {
    echo 'La verificacion de contenido de la release fallo.' >&2
    exit 65
}

if [[ -e "${release_dir}/SOURCE-METADATA.json" || -e "${release_dir}/SOURCE-MANIFEST.sha256" ]]; then
    source_head="$(bash "${release_dir}/deploy/oracle/scripts/verify-source-identity.sh" "${release_dir}")"
    [[ "$(jq -r '.git_head' "${metadata_file}")" == "${source_head}" &&
       "$(jq -r '.git_branch' "${metadata_file}")" == main &&
       "$(jq -r '.repository' "${metadata_file}")" == 'https://github.com/HubDigitalEPN/HubDigital' ]] || {
        echo 'La release preparada no corresponde al commit de main de su paquete.' >&2
        exit 65
    }
    echo "Commit main verificado: ${source_head}."
fi

echo "Identidad de release verificada: ${release_id}."
