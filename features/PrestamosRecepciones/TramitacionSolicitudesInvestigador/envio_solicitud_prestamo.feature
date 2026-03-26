# languague: es

Feature: Envió de solicitud de prestamo
    Como investigador que solicita un prestamo
    quiero enviar una solicitud de prestamo
    para iniciar formalmente su evaluación.

    Scenario: Enviar una solicitud de préstamo correctamente
        Given que existe una solicitud en estado de borrador con su información requerida completa
        When el investigador envía la solicitud
        Then la solicitud cambia a estado enviada

    Scenario: No permitir enviar una solicitud con información incompleta
        Given que existe una solicitud en estado de borrador con información incompleta
        When el investigador intenta enviar la solicitud
        Then la solicitud no es enviada y permanece en estado de borrador

