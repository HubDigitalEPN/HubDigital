# languague: es

Feature: Reenvío de solicitud de prestamo observada
    Como investigador,
    quiero corregir y reenviar una solicitud observada,
    para continuar con su evaluación.

    Scenario: Corregir una solicitud observada antes de reenviar
        Given que existe una solicitud en estado observada
        When el investigador modifica la información de la solicitud
        Then la solicitud se mantiene en estado observada con la información actualizada

    Scenario: Reenviar una solicitud observada correctamente
        Given que existe una solicitud en estado observada con la información corregida
        When el investigador reenvía la solicitud
        Then la solicitud cambia a estado enviada
