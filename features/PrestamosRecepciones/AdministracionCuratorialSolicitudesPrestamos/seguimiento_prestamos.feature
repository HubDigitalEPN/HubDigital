# languague: es

Feature: Seguimiento de préstamos.
    Como curador,
    quiero conocer el estado y la situación de los préstamos en curso,
    para mantener control sobre su desarrollo.

    Scenario Outline: Visualizar préstamos en curso por condición
        Given que existen préstamos activos en estado <estado>
        When se obtiene el listado de préstamos en curso
        Then el listado incluye únicamente préstamos en estado <estado>

        Examples:
            | estado               |
            | en curso             |
            | próximo a vencer     |
            | vencido              |
