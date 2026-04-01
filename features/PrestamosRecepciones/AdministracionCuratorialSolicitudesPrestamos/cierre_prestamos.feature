#language: es

Característica: Cierre de préstamo.
    Como curador responsable de la coleccion
    Quiero registrar el resultado de la devolución
    Para cerrar formalmente el préstamo.

    Esquema del escenario: Registrar el resultado de la devolución de un préstamo
        Dado que existe un préstamo en estado pendiente de verificación
        Cuando el curador registra la verificación de devolución con resultado <resultado>
        Entonces el préstamo queda en estado <estado>
        Y el espécimen queda en condición <condicion_especimen>

        Ejemplos:
            | resultado       | estado                  | condicion_especimen |
            | sin novedades   | cerrado                 | apto                |
            | con observación | cerrado con observación | no apto             |
