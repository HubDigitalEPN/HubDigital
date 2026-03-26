#language: es

Característica: Reenvío de solicitud de prestamo observada
    Como investigador,
    quiero corregir y reenviar una solicitud observada,
    para continuar con su evaluación.

    Escenario: Corregir una solicitud observada antes de reenviar
        Dado que existe una solicitud en estado observada
        Cuando el investigador modifica la información de la solicitud
        Entonces la solicitud se mantiene en estado observada con la información actualizada

    Escenario: Reenviar una solicitud observada correctamente
        Dado que existe una solicitud en estado observada con la información corregida
        Cuando el investigador reenvía la solicitud
        Entonces la solicitud cambia a estado enviada
