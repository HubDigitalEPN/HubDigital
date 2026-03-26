# languague: es

Feature: Avisos sobre solicitudes
    Como investigador,
    quiero recibir avisos sobre la decisión de mis solicitudes,
    para actuar oportunamente frente al resultado.

    Scenario Outline: Disponibilidad del resultado de la solicitud
        Given que existe una solicitud del investigador en proceso de evaluación
        When la solicitud cambia a estado <estado>
        Then el estado resultante de la solicitud queda disponible para el investigador

        Examples:
            | estado     |
            | aprobada   |
            | rechazada  |
            | observada  |
