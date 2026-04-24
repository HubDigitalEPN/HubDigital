#language: es
Característica: Recordatorios de devolución de mis préstamos
    Como investigador,
    quiero recibir avisos cuando mi préstamo esté por vencer o haya vencido,
    para cumplir con los plazos establecidos.

    Esquema del escenario: Recibir recordatorio de devolución según el plazo
        Dado que existe un préstamo activo con condición <condicion>
        Cuando se evalúa el plazo de devolución del préstamo
        Entonces el investigador recibe un recordatorio por correo indicando que su préstamo está <estado>

        Ejemplos:
            | condicion                                    | estado           |
            | próximo a la fecha de devolución configurada | próximo a vencer |
            | fuera del plazo de devolución                | vencido          |
