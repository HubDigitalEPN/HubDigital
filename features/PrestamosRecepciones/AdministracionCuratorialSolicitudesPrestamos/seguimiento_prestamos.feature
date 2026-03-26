#language: es

Característica: Seguimiento de préstamos.
    Como curador,
    quiero conocer el estado y la situación de los préstamos en curso,
    para mantener control sobre su desarrollo.

    Esquema del escenario: Visualizar préstamos en curso por condición
        Dado que existen préstamos activos en estado <estado>
        Cuando se obtiene el listado de préstamos en curso
        Entonces el listado incluye únicamente préstamos en estado <estado>

        Ejemplos:
            | estado               |
            | en curso             |
            | próximo a vencer     |
            | vencido              |
