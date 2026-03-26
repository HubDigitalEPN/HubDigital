# languague: es

Feature: Registro de trazabilidad de solicitudes y préstamos
    Como curador,
    quiero conocer los eventos y acciones realizadas durante el proceso de préstamo,
    para contar con evidencia de las acciones realizadas.

    Scenario: Consultar eventos de una solicitud
        Given que existe una solicitud con eventos registrados
        When se obtiene el historial de la solicitud
        Then el historial incluye los eventos asociados a la solicitud en orden cronológico

    Scenario: Consultar eventos de un préstamo
        Given que existe un préstamo con eventos registrados
        When se obtiene el historial del préstamo
        Then el historial incluye los eventos asociados al préstamo en orden cronológico

    Scenario: Solicitud o préstamo sin eventos registrados
        Given que existe una solicitud o préstamo sin eventos
        When se obtiene su historial
        Then el historial se encuentra vacío
