# languague: es

Feature: Resolución de solicitudes de préstamo.
    Como curador,
    quiero evaluar las solicitudes de préstamo,
    para definir su continuidad en el proceso de acuerdo a los criterios establecidos.

    Scenario Outline: Resolver una solicitud de préstamo
        Given que existe una solicitud en proceso de evaluación
        When el curador registra la decisión <decision> con un comentario
        Then la solicitud cambia a estado <estado>

        Examples:
            | decision   | estado     |
            | aprobar    | aprobada   |
            | rechazar   | rechazada  |
            | observar   | observada  |
