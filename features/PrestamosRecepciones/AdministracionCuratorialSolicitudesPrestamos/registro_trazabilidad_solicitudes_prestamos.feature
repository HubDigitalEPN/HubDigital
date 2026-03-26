#language: es

Característica: Registro de trazabilidad de solicitudes y préstamos
    Como curador,
    quiero conocer los eventos y acciones realizadas durante el proceso de préstamo,
    para contar con evidencia de las acciones realizadas.

    Escenario: Consultar eventos de una solicitud
        Dado que existe una solicitud con eventos registrados
        Cuando se obtiene el historial de la solicitud
        Entonces el historial incluye los eventos asociados a la solicitud en orden cronológico

    Escenario: Consultar eventos de un préstamo
        Dado que existe un préstamo con eventos registrados
        Cuando se obtiene el historial del préstamo
        Entonces el historial incluye los eventos asociados al préstamo en orden cronológico

    Escenario: Solicitud o préstamo sin eventos registrados
        Dado que existe una solicitud o préstamo sin eventos
        Cuando se obtiene su historial
        Entonces el historial se encuentra vacío
