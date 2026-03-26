#language: es

Característica: Resolución de solicitudes de préstamo.
    Como curador,
    quiero evaluar las solicitudes de préstamo,
    para definir su continuidad en el proceso de acuerdo a los criterios establecidos.

    Esquema del escenario: Resolver una solicitud de préstamo
        Dado que existe una solicitud en proceso de evaluación
        Cuando el curador registra la decisión <decision> con un comentario
        Entonces la solicitud cambia a estado <estado>

        Ejemplos:
            | decision   | estado     |
            | aprobar    | aprobada   |
            | rechazar   | rechazada  |
            | observar   | observada  |
