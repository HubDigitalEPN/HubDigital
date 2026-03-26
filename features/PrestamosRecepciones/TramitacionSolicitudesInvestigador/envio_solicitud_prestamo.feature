#language: es

Característica: Envió de solicitud de prestamo
    Como investigador que solicita un prestamo
    quiero enviar una solicitud de prestamo
    para iniciar formalmente su evaluación.

    Escenario: Enviar una solicitud de préstamo correctamente
        Dado que existe una solicitud en estado de borrador con su información requerida completa
        Cuando el investigador envía la solicitud
        Entonces la solicitud cambia a estado enviada

    Escenario: No permitir enviar una solicitud con información incompleta
        Dado que existe una solicitud en estado de borrador con información incompleta
        Cuando el investigador intenta enviar la solicitud
        Entonces la solicitud no es enviada y permanece en estado de borrador

