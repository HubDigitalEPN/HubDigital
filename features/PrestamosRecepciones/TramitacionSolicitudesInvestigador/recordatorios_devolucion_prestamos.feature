# languague: es

Feature: Recordatorios de devolución de mis prestamos
    Como investigador,
    quiero conocer cuándo debo devolver los especímenes prestados,
    para cumplir con los plazos establecidos.

    Scenario Outline: Condición de devolución de un préstamo según su fecha
        Given que existe un préstamo activo con condición <condicion>
        When el investigador consulta el préstamo
        Then puede constatar que el préstamo se encuentra <estado>

        Examples:
            | condicion                                      | estado              |
            | dentro del plazo de devolución                 | dentro del plazo    |
            | próximo a la fecha de devolución configurada   | próximo a vencer    |
            | fuera del plazo de devolución                  | vencido             |
