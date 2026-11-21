#!/usr/bin/env bash
# Configuración del host externo; nunca imprime el entorno ni sus secretos.
deployment_hostname="${HUBDIGITAL_DEPLOY_HOSTNAME:-dev.labinvepn.org}"
if [[ -r /etc/hubdigital/hubdigital.env ]]; then
    deployment_url="$(sed -n 's/^APP_URL=//p' /etc/hubdigital/hubdigital.env | tail -n 1 | tr -d '\r')"
    case "${deployment_url}" in
        https://dev.labinvepn.org|https://dev.labinvepn.org/) deployment_hostname=dev.labinvepn.org ;;
        https://labinvepn.org|https://labinvepn.org/) deployment_hostname=labinvepn.org ;;
        *) echo 'APP_URL debe corresponder al dominio HTTPS de desarrollo o producción.' >&2; exit 65 ;;
    esac
fi
case "${deployment_hostname}" in
    dev.labinvepn.org|labinvepn.org) ;;
    *) echo 'Host de despliegue no permitido.' >&2; exit 65 ;;
esac

render_nginx_for_host() {
    local template="$1" cotizador="$2" include_line=''
    if [[ "${deployment_hostname}" == labinvepn.org && "${cotizador}" == yes ]]; then
        include_line='    include /etc/nginx/snippets/ingram-cotizador.conf;'
    fi
    sed -e "s/dev\\.labinvepn\\.org/${deployment_hostname}/g" \
        -e "s|^    # __INGRAM_COTIZADOR__[[:space:]]*$|${include_line}|" "${template}"
}

install_nginx_for_host() {
    local template="$1" target="$2" cotizador=no candidate
    if [[ -f /etc/nginx/snippets/ingram-cotizador.conf ]]; then
        cotizador=yes
    fi
    candidate="$(mktemp "${target}.XXXXXX")" || return
    if render_nginx_for_host "${template}" "${cotizador}" > "${candidate}" \
        && chmod 0644 "${candidate}" && mv -fT -- "${candidate}" "${target}"; then
        return
    fi
    rm -f -- "${candidate}"
    return 1
}
