# language: es
Característica: Registro y confirmación de muestras de colecta
  Como curador responsable de la colección,
  quiero registrar muestras de colecta con código tentativo y confirmar su validez,
  para preservar la jerarquía muestra → especímenes y resolver la trazabilidad post-import.

  Escenario: El curador registra una muestra mínima con motivo de revisión
    Dado que el curador tiene los datos de una muestra de colecta tentativa
    Cuando el curador registra la muestra
    Entonces la muestra queda registrada en estado pendiente con su motivo

  Escenario: El curador confirma una muestra pendiente
    Dado que existe una muestra de colecta en estado pendiente
    Cuando el curador confirma la muestra
    Entonces la muestra queda en estado confirmada sin motivo de revisión

  Escenario: El curador marca una muestra confirmada para nueva revisión
    Dado que existe una muestra de colecta confirmada
    Cuando el curador marca la muestra para revisión con un motivo
    Entonces la muestra vuelve a estado pendiente con el motivo registrado
