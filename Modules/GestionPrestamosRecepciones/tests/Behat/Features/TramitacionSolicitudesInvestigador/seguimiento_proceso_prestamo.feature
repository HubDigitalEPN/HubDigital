#language: es
@investigador @curador
Característica: Consulta del estado del proceso de préstamo
    Como participante del proceso de préstamo
    Quiero consultar el estado e historial de solicitudes, actas y préstamos
    Para estar informado sobre el avance del proceso.

    @investigador
    Escenario: Conocer el estado de mi solicitud en borrador
        Dado que existe una solicitud del investigador en estado borrador
        Cuando el investigador consulta la información de la solicitud
        Entonces la solicitud es retornada con estado borrador

    @investigador @curador
    Esquema del escenario: Conocer el estado de una solicitud
        Dado que existe una solicitud en estado <estado>
        Cuando el usuario consulta la información de la solicitud
        Entonces la solicitud es retornada con estado <estado>

        Ejemplos:
            | estado    |
            | enviada   |
            | observada |
            | aprobada  |
            | rechazada |

    @investigador @curador
    Esquema del escenario: Conocer el estado del acta de préstamo
        Dado que existe un acta en estado <estado>
        Cuando el usuario consulta la información del acta
        Entonces el acta es retornada con estado <estado>

        Ejemplos:
            | estado                  |
            | pendiente de envío      |
            | pendiente de firma      |
            | pendiente de validación |
            | validada                |

    @investigador @curador
    Esquema del escenario: Conocer el estado de un préstamo
        Dado que existe un préstamo en estado <estado>
        Cuando el usuario consulta la información del préstamo
        Entonces el préstamo es retornado con estado <estado>

        Ejemplos:
            | estado                  |
            | activo                  |
            | prórroga solicitada     |
            | vencido                 |
            | en revisión             |
            | cerrado                 |
            | cerrado con observación |

    @investigador @curador
    Esquema del escenario: Consultar la trazabilidad de una solicitud
        Dado que existe una solicitud con <condicion>
        Cuando el usuario solicita el historial de la solicitud
        Entonces el historial es retornado <resultado>

        Ejemplos:
            | condicion           | resultado                            |
            | eventos registrados | con los eventos en orden cronológico |
            | sin eventos         | vacío                                |

    @investigador @curador
    Esquema del escenario: Consultar la trazabilidad de un préstamo
        Dado que existe un préstamo con <condicion>
        Cuando el usuario solicita el historial del préstamo
        Entonces el historial es retornado <resultado>

        Ejemplos:
            | condicion           | resultado                            |
            | eventos registrados | con los eventos en orden cronológico |
            | sin eventos         | vacío                                |
