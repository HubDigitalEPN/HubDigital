# language: es
Característica: Habilitación del envío de especímenes en préstamos internacionales

  Escenario: El curador sube el documento del Ministerio y habilita el envío
    Dado que existe un préstamo internacional pendiente de documento del ministerio
    Cuando el curador sube el documento de aprobación del Ministerio del Ambiente
    Entonces el préstamo pasa al estado en tránsito
    Y el documento queda adjunto al acta

  Escenario: No se puede habilitar el envío con una ruta de documento vacía
    Dado que existe un préstamo internacional pendiente de documento del ministerio
    Cuando el curador intenta habilitar el envío sin proporcionar una ruta de documento
    Entonces el sistema rechaza la operación

  Escenario: No se puede adjuntar un documento de exportación a un préstamo nacional
    Dado que existe un acta de alcance nacional en estado validada
    Cuando el curador intenta adjuntar un documento de exportación al acta nacional
    Entonces el sistema rechaza la operación

  Escenario: Un préstamo nacional se activa directamente en tránsito al validar el acta
    Dado que existe una solicitud de alcance nacional aprobada con su acta en estado pendiente de validación
    Cuando el curador valida el acta firmada
    Entonces el préstamo queda en estado en tránsito
