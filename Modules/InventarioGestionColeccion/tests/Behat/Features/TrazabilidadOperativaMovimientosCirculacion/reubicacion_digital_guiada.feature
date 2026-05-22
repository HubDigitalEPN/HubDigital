# language: es
Característica: Reubicación digital guiada de unit trays y guía para visitantes
  Como curador o visitante autorizado de la colección,
  quiero moverme dentro de la colección siguiendo una guía digital que respete la jerarquía Gabinete → Ranura → Caja → Unit Tray y el orden taxonómico,
  para mantener el inventario digital alineado con la realidad física y que cualquier persona encuentre un espécimen de forma autónoma.

  Escenario: El curador traslada un unit tray a una caja de destino respetando el orden por especie
    Dado que existe un unit tray en una caja de origen
    Y existe una caja de destino con espacio disponible donde el unit tray respetaría el orden alfabético por especie
    Cuando el curador traslada el unit tray a la caja de destino
    Entonces el unit tray queda registrado en la caja de destino
    Y se guarda un registro en el historial de custodia con las cajas de origen y destino

  Escenario: El curador intenta trasladar un unit tray a una caja sin espacio disponible
    Dado que existe un unit tray en una caja de origen
    Y la caja de destino ha alcanzado su capacidad máxima de unit trays
    Cuando el curador traslada el unit tray a la caja de destino
    Entonces se informa que la caja de destino no tiene espacio disponible
    Y el unit tray permanece en su caja de origen

  Escenario: El curador recibe una advertencia al trasladar un unit tray que rompería el orden por especie en la caja de destino
    Dado que existe un unit tray en una caja de origen
    Y en la caja de destino el unit tray quedaría fuera del orden alfabético por especie
    Cuando el curador traslada el unit tray a la caja de destino
    Entonces se muestra una advertencia de "Orden Taxonómico Fuera de Secuencia"
    Y el curador puede confirmar la reubicación o cancelarla

  Esquema del escenario: El curador consulta el historial de custodia de un unit tray
    Dado que existe un unit tray con <condicion>
    Cuando el curador solicita el historial de custodia del unit tray
    Entonces el historial es retornado <resultado>

    Ejemplos:
      | condicion             | resultado                                |
      | reubicaciones previas | con los movimientos en orden cronológico |
      | sin reubicaciones     | vacío                                    |

  Escenario: El visitante escanea el código QR de un gabinete y ve su contenido taxonómico
    Dado que existe un gabinete con cajas registradas y acceso público habilitado
    Cuando el visitante escanea el código QR del gabinete con su dispositivo móvil
    Entonces se muestra el contenido del gabinete organizado por subfamilia, género y especie

  Escenario: El visitante sigue la guía hasta el unit tray de un espécimen buscado
    Dado que existe un espécimen con su ubicación física habilitada para visitantes
    Cuando el visitante busca el espécimen por su nombre científico
    Entonces se muestra la ruta completa: gabinete, caja y unit tray donde se encuentra el espécimen
