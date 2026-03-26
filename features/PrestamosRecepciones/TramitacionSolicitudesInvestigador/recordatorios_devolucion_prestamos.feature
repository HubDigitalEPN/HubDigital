#language: es

Característica: Recordatorios de devolución de mis prestamos
    Como investigador,
    quiero conocer cuándo debo devolver los especímenes prestados,
    para cumplir con los plazos establecidos.

    Esquema del escenario: Condición de devolución de un préstamo según su fecha
        Dado que existe un préstamo activo con condición <condicion>
        Cuando el investigador consulta el préstamo
        Entonces puede constatar que el préstamo se encuentra <estado>

        Ejemplos:
            | condicion                                      | estado              |
            | dentro del plazo de devolución                 | dentro del plazo    |
            | próximo a la fecha de devolución configurada   | próximo a vencer    |
            | fuera del plazo de devolución                  | vencido             |
