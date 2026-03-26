# languague: es

Feature: Bandeja curatorial de solicitudes de prestamos.
    Como curador,
    quiero tener una vista organizada de las solicitudes de préstamo,
    para facilitar su revisión.

    Scenario: Filtrar solicitudes por estado
        Given que existen solicitudes de préstamo con distintos estados
        When el curador consulta la bandeja y aplica un filtro por estado "enviada"
        Then sólo se listan las solicitudes en estado enviada

    Scenario: Consultar información detallada de una solicitud
        Given que existe una solicitud de préstamo
        When el curador selecciona la solicitud
        Then puede constatar todos los datos relevantes de la solicitud para su evaluación

    Scenario: Bandeja sin solicitudes
        Given que no existen solicitudes pendientes para el curador
        When el curador revisa su bandeja
        Then constata que no hay solicitudes asignadas
