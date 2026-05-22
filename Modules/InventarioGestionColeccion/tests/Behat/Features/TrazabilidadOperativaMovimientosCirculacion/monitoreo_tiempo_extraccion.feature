# language: es
Característica: Monitoreo de tiempo de extracción y localización de especímenes
  Como curador o visitante autorizado de la colección,
  quiero tener visibilidad sobre dónde se encuentra cada caja y espécimen en tiempo real,
  para evitar el extravío del material biológico y permitir que visitantes encuentren especímenes sin necesitar asistencia del curador.

  Esquema del escenario: El curador recibe alertas según el tiempo que lleva una caja fuera de su posición
    Dado que existe una caja fuera de su posición con condición <condicion>
    Cuando se verifican los tiempos de extracción
    Entonces <resultado>

    Ejemplos:
      | condicion                          | resultado                                                                                              |
      | dentro del límite de tiempo        | el estado permanece "En Tránsito" sin alertas                                                          |
      | próxima a superar el límite        | se envía una notificación preventiva al curador responsable                                            |
      | superando el límite de 1 día hábil | se genera una alerta de "Extracción Prolongada" y el estado de la caja cambia a "Extracción Prolongada"|

  Escenario: El curador registra la devolución de una caja en extracción prolongada
    Dado que existe una caja en estado "Extracción Prolongada"
    Cuando el curador registra la devolución de la caja a su ranura en el gabinete
    Entonces la devolución se registra
    Y el estado de la caja vuelve a "En Gabinete"

  Escenario: El visitante localiza físicamente un espécimen a partir de su nombre científico
    Dado que existe un espécimen divulgado con su ubicación física habilitada para visitantes
    Cuando el visitante consulta la ubicación del espécimen por su nombre científico
    Entonces se muestra el gabinete y la caja donde se encuentra el espécimen

  Escenario: El visitante no puede ver la ubicación de un espécimen cuya ubicación no está habilitada
    Dado que existe un espécimen divulgado sin su ubicación física habilitada para visitantes
    Cuando el visitante consulta la ubicación del espécimen
    Entonces se informa que la ubicación física no está disponible públicamente
