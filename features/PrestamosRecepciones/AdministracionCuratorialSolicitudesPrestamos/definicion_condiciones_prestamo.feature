#language: es

Característica: Definición de condiciones de préstamo.
    Como curador,
    quiero establecer los términos y restricciones del préstamo,
    para evitar inconvenientes durante el proceso del préstamo.

    Escenario: Establecer condiciones de préstamo
        Dado que existe una solicitud en proceso de evaluación
        Cuando el curador define condiciones para el préstamo
        Entonces la solicitud queda asociada a las condiciones establecidas

    Escenario: Modificar condiciones de préstamo
        Dado que existe una solicitud con condiciones previamente definidas
        Cuando el curador actualiza las condiciones del préstamo
        Entonces la solicitud refleja las condiciones actualizadas
