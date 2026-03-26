# languague: es

Feature: Definición de condiciones de préstamo.
    Como curador,
    quiero establecer los términos y restricciones del préstamo,
    para evitar inconvenientes durante el proceso del préstamo.

    Scenario: Establecer condiciones de préstamo
        Given que existe una solicitud en proceso de evaluación
        When el curador define condiciones para el préstamo
        Then la solicitud queda asociada a las condiciones establecidas

    Scenario: Modificar condiciones de préstamo
        Given que existe una solicitud con condiciones previamente definidas
        When el curador actualiza las condiciones del préstamo
        Then la solicitud refleja las condiciones actualizadas
