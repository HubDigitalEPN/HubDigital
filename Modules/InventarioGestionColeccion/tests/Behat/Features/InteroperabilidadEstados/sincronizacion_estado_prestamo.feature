#language: es
Característica: Sincronización del estado del espécimen al ser prestado
  Como sistema de gestión,
  quiero que el estado del espécimen se actualice a "prestado" al recibir el evento EspecimenPrestado,
  para mantener la trazabilidad del espécimen entre módulos.

  Escenario: Espécimen cambia a "prestado" al recibir el evento EspecimenPrestado
    Dado que existe un espécimen en estado disponible
    Cuando el sistema recibe el evento EspecimenPrestado para ese espécimen
    Entonces el espécimen queda en estado prestado

  Escenario: No se procesa el evento si el espécimen no existe en el sistema
    Dado que no existe ningún espécimen con el identificador del evento
    Cuando el sistema recibe el evento EspecimenPrestado para ese identificador
    Entonces el sistema registra un error indicando que el espécimen no fue encontrado
