#!/usr/bin/env bash
set -euo pipefail

# Comprueba el origen Git y los archivos del bundle sin consultar GitHub ni tocar la base.
source_dir="${1:-}"
metadata="${source_dir}/SOURCE-METADATA.json"
manifest="${source_dir}/SOURCE-MANIFEST.sha256"
[[ -d "${source_dir}" && -s "${metadata}" && -s "${manifest}" ]] || {
    echo 'Falta la identidad del paquete publicado en main.' >&2
    exit 65
}
jq -e '
    .format_version == 1 and
    .repository == "https://github.com/HubDigitalEPN/HubDigital" and
    .git_branch == "main" and
    (.git_commit | test("^[0-9a-f]{40}$")) and
    (.git_tree | test("^[0-9a-f]{40}$")) and
    (.source_manifest_sha256 | test("^[0-9a-f]{64}$")) and
    (.java_jar_sha256 | test("^[0-9a-f]{64}$")) and
    (.frontend_manifest_sha256 | test("^[0-9a-f]{64}$"))
' "${metadata}" >/dev/null || {
    echo 'El paquete no identifica un commit valido de main del repositorio oficial.' >&2
    exit 65
}
[[ "$(sha256sum "${manifest}" | awk '{print $1}')" == "$(jq -r '.source_manifest_sha256' "${metadata}")" ]] || {
    echo 'El manifiesto fuente no coincide con el registrado para main.' >&2
    exit 65
}
source_root="$(realpath -e -- "${source_dir}")"
while IFS= read -r line; do
    [[ "${#line}" -gt 66 && "${line:0:64}" =~ ^[0-9a-f]{64}$ && "${line:64:2}" == '  ' ]] || {
        echo 'El manifiesto fuente contiene una entrada invalida.' >&2
        exit 65
    }
    relative="${line:66}"
    case "${relative}" in
        /*|*../*|../*|*\\*|*$'\n'*)
            echo 'Ruta no permitida en el manifiesto fuente.' >&2
            exit 65 ;;
    esac
    resolved="$(realpath -e -- "${source_root}/${relative}")"
    [[ -f "${resolved}" && "${resolved}" == "${source_root}/"* ]] || {
        echo 'El manifiesto apunta fuera del contenido del paquete.' >&2
        exit 65
    }
done < "${manifest}"
(cd "${source_root}" && sha256sum --check --status SOURCE-MANIFEST.sha256) || {
    echo 'El contenido fuente difiere del commit registrado al crear el paquete.' >&2
    exit 65
}
for pair in 'java_jar_sha256:resources/bin/hubdigital-pdf-signature.jar' 'frontend_manifest_sha256:public/build/manifest.json'; do
    key="${pair%%:*}"
    relative="${pair#*:}"
    [[ -f "${source_root}/${relative}" && "$(sha256sum "${source_root}/${relative}" | awk '{print $1}')" == "$(jq -r --arg key "${key}" '.[$key]' "${metadata}")" ]] || {
        echo "El artefacto no coincide con el que se compilo para main: ${relative}" >&2
        exit 65
    }
done
jq -r '.git_commit' "${metadata}"
