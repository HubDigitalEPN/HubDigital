#!/usr/bin/env bash
set -euo pipefail

release_dir="${1:-}"
signature_jar="${release_dir}/resources/bin/hubdigital-pdf-signature.jar"
[[ -r "${signature_jar}" ]] || { echo 'NO OK Java PDF: falta el JAR del candidato.' >&2; exit 65; }
# Las VM de 1 GB no deben mantener dos JVM de firma al mismo tiempo. Solo
# pausamos la unidad que ya estaba activa y la restauramos incluso al fallar.
signature_paused=0
restore_signature() {
    local status=$?
    trap - EXIT
    if [[ "${signature_paused}" == 1 ]]; then
        if ! systemctl start hubdigital-signature.service; then
            echo 'NO OK Java PDF: no se pudo restaurar el servicio de firma anterior.' >&2
            status=65
        fi
    fi
    exit "${status}"
}
trap restore_signature EXIT
if systemctl is-active --quiet hubdigital-signature.service; then
    printf 'Pausando brevemente la JVM de firma para comprobar el candidato sin duplicar memoria.\n' >&2
    signature_paused=1
    systemctl stop hubdigital-signature.service
fi
printf '==> Autoprueba Java del candidato antes de mantenimiento\n' >&2
if ! java_result="$(systemd-run --quiet --wait --collect --pipe \
    --property=User=www-data --property=Group=www-data \
    --property=MemoryMax=320M --property=MemorySwapMax=0 --property=RuntimeMaxSec=300 \
    --working-directory="${release_dir}" \
    /usr/bin/java -Djava.awt.headless=true -Xms24m -Xmx160m \
    -XX:+UseSerialGC -XX:ActiveProcessorCount=2 -jar "${signature_jar}" selftest)"; then
    echo 'NO OK Java PDF: el candidato fallo; no se pone la aplicacion activa en mantenimiento.' >&2
    exit 65
fi
if ! jq -e '.status == "firmado" and .cryptographically_valid == true' >/dev/null 2>&1 <<<"${java_result}"; then
    echo 'NO OK Java PDF: el candidato no confirmo su aceptacion criptografica.' >&2
    exit 65
fi
printf 'OK Java PDF del candidato: firma, QR, contenido y rechazos comprobados.\n'
