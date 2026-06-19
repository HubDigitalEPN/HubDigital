# language: es
@listo
Característica: Localización y guía de ubicación de especímenes en la colección
  Como curador o visitante de la colección,
  quiero consultar la disposición física de la colección y localizar un espécimen por su nombre científico,
  para encontrar material y entender su organización recorriendo la jerarquía Gabinete → Ranura → Caja → Unit Tray → Espécimen sin asistencia del curador.

  Escenario: El curador consulta la composición taxonómica de un gabinete
    Dado que existe un gabinete con cajas clasificadas en sus ranuras
    Cuando el curador consulta la composición del gabinete
    Entonces se obtienen las subfamilias y géneros presentes en el gabinete

  Escenario: El curador consulta la ocupación de las ranuras de un gabinete
    Dado que existe un gabinete con cajas ubicadas en sus ranuras
    Cuando el curador consulta la ocupación del gabinete
    Entonces se obtiene, para cada ranura, la caja que la ocupa y su clasificación

  Escenario: El curador consulta el contenido de una caja
    Dado que existe una caja con unit trays asignados
    Cuando el curador consulta el contenido de la caja
    Entonces se obtienen sus unit trays con la clasificación dominante de cada uno

  Escenario: El curador ve una caja que alberga varias subfamilias y géneros en la ocupación del gabinete
    Dado que existe un gabinete con una caja que alberga varias subfamilias y géneros
    Cuando el curador consulta la ocupación del gabinete
    Entonces la caja muestra su subfamilia y género dominantes junto con todas las subfamilias y géneros que alberga

  Escenario: El curador consulta el contenido de un unit tray
    Dado que existe un unit tray con especímenes asignados
    Cuando el curador consulta el contenido del unit tray
    Entonces se obtienen los especímenes con su nombre científico

  # Guía pública para visitantes. La ubicación es siempre compartible: lo único que la hace
  # "no disponible" es que el espécimen aún no esté colocado en un unit tray.
  Escenario: El visitante localiza un espécimen colocado en la colección por su nombre científico
    Dado que existe un espécimen colocado en un unit tray de la colección
    Cuando el visitante localiza el espécimen por su nombre científico
    Entonces se obtiene la ruta física hasta el espécimen: el gabinete, la caja y el unit tray donde se encuentra

  Escenario: El visitante no obtiene la ubicación de un espécimen que aún no está colocado en un unit tray
    Dado que existe un espécimen que aún no está colocado en ningún unit tray
    Cuando el visitante localiza el espécimen por su nombre científico
    Entonces la ubicación física no se entrega y se informa que no está disponible públicamente
