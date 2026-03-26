#language: es

Característica: Seguimiento de solicitudes
    Como investigador,
    quiero consultar el estado y la línea de tiempo de mis solicitudes,
    para dar seguimiento a sus eventos en el proceso.

    Escenario: Consultar el estado de una solicitud
        Dado que existe una solicitud asociada al investigador
        Cuando el investigador consulta la solicitud
        Entonces se muestra el estado actual de la solicitud

    Escenario: Consultar la línea de tiempo de una solicitud
        Dado que existe una solicitud con eventos registrados
        Cuando el investigador consulta la solicitud
        Entonces se muestra la secuencia de eventos de la solicitud
