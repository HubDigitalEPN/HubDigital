#language: es

Característica: Avisos sobre solicitudes
    Como investigador,
    quiero recibir avisos sobre la decisión de mis solicitudes,
    para actuar oportunamente frente al resultado.

    Esquema del escenario: Disponibilidad del resultado de la solicitud
        Dado que existe una solicitud del investigador en proceso de evaluación
        Cuando la solicitud cambia a estado <estado>
        Entonces el estado resultante de la solicitud queda disponible para el investigador

        Ejemplos:
            | estado     |
            | aprobada   |
            | rechazada  |
            | observada  |
