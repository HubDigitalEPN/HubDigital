#language: es

Característica: Bandeja curatorial de solicitudes de prestamos.
    Como curador,
    quiero tener una vista organizada de las solicitudes de préstamo,
    para facilitar su revisión.

    Escenario: Filtrar solicitudes por estado
        Dado que existen solicitudes de préstamo con distintos estados
        Cuando el curador consulta la bandeja y aplica un filtro por estado "enviada"
        Entonces sólo se listan las solicitudes en estado enviada

    Escenario: Consultar información detallada de una solicitud
        Dado que existe una solicitud de préstamo
        Cuando el curador selecciona la solicitud
        Entonces puede constatar todos los datos relevantes de la solicitud para su evaluación

    Escenario: Bandeja sin solicitudes
        Dado que no existen solicitudes pendientes para el curador
        Cuando el curador revisa su bandeja
        Entonces constata que no hay solicitudes asignadas
