#language: es
Característica: Seguimiento del proceso de préstamo
    Como investigador con solicitudes y préstamos activos
    Quiero estar informado sobre el avance de mi proceso de préstamo
    Para actuar oportunamente frente a cualquier cambio.

    Esquema del escenario: Conocer el estado de una solicitud
        Dado que existe una solicitud del investigador en estado <estado>
        Cuando el investigador solicita la información de la solicitud
        Entonces la solicitud es retornada con estado <estado>

        Ejemplos:
            | estado     |
            | borrador   |
            | enviada    |
            | observada  |
            | aprobada   |
            | rechazada  |

    Esquema del escenario: Conocer el estado de un préstamo
        Dado que existe un préstamo del investigador en estado <estado>
        Cuando el investigador solicita la información del préstamo
        Entonces el préstamo es retornado con estado <estado>

        Ejemplos:
            | estado              |
            | activo              |
            | prórroga solicitada |
            | vencido             |
            | en revisión         |
            | cerrado             |
