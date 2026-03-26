# languague: es

Feature: Seguimiento de solicitudes
    Como investigador,
    quiero consultar el estado y la línea de tiempo de mis solicitudes,
    para dar seguimiento a sus eventos en el proceso.

    Scenario: Consultar el estado de una solicitud
        Given que existe una solicitud asociada al investigador
        When el investigador consulta la solicitud
        Then se muestra el estado actual de la solicitud

    Scenario: Consultar la línea de tiempo de una solicitud
        Given que existe una solicitud con eventos registrados
        When el investigador consulta la solicitud
        Then se muestra la secuencia de eventos de la solicitud
