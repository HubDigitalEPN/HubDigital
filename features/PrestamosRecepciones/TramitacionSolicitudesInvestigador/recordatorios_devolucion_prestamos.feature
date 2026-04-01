#language: es

Característica: Recordatorios de devolución de mis prestamos
    Como investigador,
    quiero conocer cuándo debo devolver los especímenes prestados,
    para cumplir con los plazos establecidos.

    Esquema del escenario: Recibir recordatorio de devolución según el plazo
        Dado que existe un préstamo activo con condición <condicion>
        Cuando se evalúa el plazo de devolución del préstamo
        Entonces el investigador recibe un recordatorio por correo indicando que su préstamo se encuentra <estado>

        Ejemplos:
            | condicion                                    | estado           |
            | dentro del plazo de devolución               | dentro del plazo |
            | próximo a la fecha de devolución configurada | próximo a vencer |
            | fuera del plazo de devolución                | vencido          |
