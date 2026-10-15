#!/usr/bin/env bash
set -euo pipefail
[[ "${EUID}" -eq 0 ]] || { echo 'Ejecuta la instalación de ETE con sudo.' >&2; exit 64; }
release_dir="$(realpath -e -- "${1:?Falta la ruta de release}")"
case "${release_dir}" in /srv/hubdigital/releases/*) ;; *) echo 'ETE exige una release preparada.' >&2; exit 65 ;; esac
requirements="${release_dir}/resources/python/portal_ete/requirements.txt"
[[ -r "${requirements}" && -r "${release_dir}/resources/python/portal_ete/server.py" ]] || { echo 'Falta el motor ETE de la release.' >&2; exit 66; }
# La identidad se comprueba antes de instalar dependencias de esta release.
bash "${release_dir}/deploy/oracle/scripts/verify-source-identity.sh" "${release_dir}"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends python3-venv libgl1 libegl1 libxrender1 libxext6 fonts-dejavu-core
venv_options=()
if [[ "$(dpkg --print-architecture)" == arm64 ]]; then
    # PyPI no distribuye ruedas Linux ARM de estas versiones de PyQt5/Qt5.
    # Ubuntu provee los enlaces nativos para su Python; el resto queda aislado.
    apt-get install -y --no-install-recommends python3-pyqt5 python3-pyqt5.qtsvg
    venv_options=(--system-site-packages)
fi
requirements_sha="$(sha256sum "${requirements}" | awk '{print $1}')"
runtime="/opt/hubdigital/ete/${requirements_sha}"
install -d -m 0755 /opt/hubdigital/ete
if [[ ! -f "${runtime}/INSTALLED" ]]; then
    python3 -m venv "${venv_options[@]}" "${runtime}"
    "${runtime}/bin/python" -m pip install --disable-pip-version-check -r "${requirements}"
    printf '%s\n' "${requirements_sha}" > "${runtime}/INSTALLED"
fi
ln -sfn "${runtime}" /opt/hubdigital/ete/current
echo 'Runtime ETE nativo de OCI preparado; no se copia el entorno Windows.'
