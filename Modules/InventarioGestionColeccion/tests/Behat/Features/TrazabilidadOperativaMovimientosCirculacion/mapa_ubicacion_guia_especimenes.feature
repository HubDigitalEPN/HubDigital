# language: es
Característica: Mapa de ubicación y guía de ubicación de especímenes
  Como curador o visitante de la colección,
  quiero ver la colección como un mapa navegable y localizar un espécimen por su nombre científico,
  para entender la disposición física y encontrar material sin asistencia del curador.

  Escenario: El curador ve el mapa de gabinetes con su composición taxonómica
    Dado que existen gabinetes con cajas clasificadas
    Cuando el curador abre el mapa de ubicación
    Entonces se muestran los gabinetes con las subfamilias y géneros que contienen

  Escenario: El curador explora un gabinete y ve sus ranuras
    Dado que existe un gabinete con cajas ubicadas en sus ranuras
    Cuando el curador selecciona el gabinete en el mapa
    Entonces se muestran las ranuras del gabinete con la caja de cada una y su clasificación

  Escenario: El curador explora una caja y ve sus unit trays
    Dado que existe una caja con unit trays asignados
    Cuando el curador selecciona la caja en el mapa
    Entonces se muestran los unit trays de la caja con su clasificación dominante

  Escenario: El curador explora un unit tray y ve sus especímenes
    Dado que existe un unit tray con especímenes asignados
    Cuando el curador selecciona el unit tray en el mapa
    Entonces se muestran los especímenes del unit tray con su nombre científico

  Escenario: El visitante localiza un espécimen habilitado por su nombre científico
    Dado que existe un espécimen divulgado con su ubicación física habilitada para visitantes
    Cuando el visitante consulta la ubicación del espécimen por su nombre científico
    Entonces se muestra la ruta hasta el espécimen con el gabinete, la caja y el unit tray donde se encuentra

  Escenario: El visitante no ve la ubicación de un espécimen no habilitado
    Dado que existe un espécimen divulgado sin su ubicación física habilitada para visitantes
    Cuando el visitante consulta la ubicación del espécimen por su nombre científico
    Entonces se informa que la ubicación física no está disponible públicamente
