#language: es
Característica: Seguimiento del proceso de préstamo
    Como curador responsable del proceso de préstamo
    Quiero dar seguimiento al ciclo de préstamo
    Para mantener control sobre su desarrollo.

    Esquema del escenario: Conocer el estado de una solicitud
        Dado que existe una solicitud en estado <estado>
        Cuando el curador solicita la información de la solicitud
        Entonces la solicitud es retornada con estado <estado>

        Ejemplos:
            | estado     |
            | enviada    |
            | observada  |
            | aprobada   |
            | rechazada  |

    Esquema del escenario: Conocer el estado del acta de préstamo
        Dado que existe un acta en estado <estado>
        Cuando el curador solicita la información del acta
        Entonces el acta es retornada con estado <estado>

        Ejemplos:
            | estado                  |
            | pendiente de envío      |
            | pendiente de firma      |
            | pendiente de validación |

    Esquema del escenario: Conocer el estado de un préstamo
        Dado que existe un préstamo en estado <estado>
        Cuando el curador solicita la información del préstamo
        Entonces el préstamo es retornado con estado <estado>

        Ejemplos:
            | estado                  |
            | activo                  |
            | prórroga solicitada     |
            | vencido                 |
            | en revisión             |
            | cerrado                 |
            | cerrado con observación |

    Esquema del escenario: Consultar la trazabilidad de una solicitud
        Dado que existe una solicitud con <condicion>
        Cuando el curador solicita el historial de la solicitud
        Entonces el historial es retornado <resultado>

        Ejemplos:
            | condicion          | resultado                              |
            | eventos registrados| con los eventos en orden cronológico   |
            | sin eventos        | vacío                                  |

    Esquema del escenario: Consultar la trazabilidad de un préstamo
        Dado que existe un préstamo con <condicion>
        Cuando el curador solicita el historial del préstamo
        Entonces el historial es retornado <resultado>

        Ejemplos:
            | condicion           | resultado                             |
            | eventos registrados | con los eventos en orden cronológico  |
            | sin eventos         | vacío                                 |
