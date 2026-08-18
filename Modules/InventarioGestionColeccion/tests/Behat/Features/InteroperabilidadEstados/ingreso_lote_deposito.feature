# language: es
@listo
Característica: Ingreso a la colección del material de un depósito aprobado
  Como sistema de gestión,
  quiero trasladar a la colección la matriz de un depósito recibido,
  para que el material quede descrito, trazable hasta su trámite y sin duplicados.

  Escenario: El sistema traslada a la colección los datos declarados en la matriz
    Dado que un depósito aprobado trae una matriz con permisos y jerarquía taxonómica
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen conserva los permisos y el determinador declarados

  Escenario: El sistema engancha al árbol taxonómico lo que el curador ya validó
    Dado que un depósito aprobado trae una matriz con permisos y jerarquía taxonómica
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen queda enganchado a un taxón canónico

  Escenario: El sistema no engancha al árbol lo que aún no tiene visto bueno taxonómico
    Dado que un depósito trae un registro con la taxonomía sin validar
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen conserva la jerarquía declarada sin taxón canónico

  Escenario: El sistema no consulta el árbol taxonómico si nada está validado
    Dado que un depósito trae un registro con la taxonomía sin validar
    Cuando el sistema ingresa el lote a la colección
    Entonces el sistema no llegó a consultar el árbol taxonómico

  Escenario: El sistema ata cada espécimen al trámite que lo trajo
    Dado que un depósito aprobado trae una matriz con permisos y jerarquía taxonómica
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen queda atado a su solicitud y a su fila de matriz

  Escenario: El sistema no duplica el material si el ingreso se reejecuta
    Dado que un depósito aprobado ya ingresó su material a la colección
    Cuando el sistema ingresa el lote a la colección
    Entonces la colección conserva un solo espécimen por fila de matriz

  Escenario: El sistema no duplica el material aunque cambie la posición de las filas
    Dado que un depósito ya ingresado reordena las filas de su matriz
    Cuando el sistema ingresa el lote a la colección
    Entonces la colección conserva un solo espécimen por fila de matriz

  Escenario: El sistema sí ingresa la fila que se añadió al reordenar la matriz
    Dado que un depósito ya ingresado reordena las filas de su matriz
    Cuando el sistema ingresa el lote a la colección
    Entonces la colección incorpora la fila nueva sin descartarla

  Escenario: El sistema respeta la corrección taxonómica que aceptó el curador
    Dado que el curador aceptó corregir el nombre científico de un registro
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen adopta el nombre corregido por el curador

  Escenario: El sistema anota el código QR del lote como acta de recepción
    Dado que un depósito aprobado trae una matriz con permisos y jerarquía taxonómica
    Cuando el sistema ingresa el lote a la colección
    Entonces el acta de recepción del espécimen es el código QR del lote

  Escenario: El sistema deja el acta vacía si el lote aún no tiene código QR
    Dado que un depósito aprobado ingresa sin código QR de lote
    Cuando el sistema ingresa el lote a la colección
    Entonces el acta de recepción del espécimen queda vacía

  Escenario: El sistema toma del trámite el permiso que la matriz ya no pide
    Dado que un depósito aprobado trae una matriz sin permisos por fila
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen queda amparado por el permiso del trámite

  Escenario: El sistema informa qué espécimen produjo cada fila de la matriz
    Dado que un depósito aprobado trae una matriz con permisos y jerarquía taxonómica
    Cuando el sistema ingresa el lote a la colección
    Entonces el resultado indica el espécimen que produjo cada fila

  Escenario: El sistema informa el motivo de revisión del registro que lo necesita
    Dado que un depósito trae un registro con la taxonomía sin validar
    Cuando el sistema ingresa el lote a la colección
    Entonces el resultado indica por qué esa fila quedó para revisión

  Escenario: El sistema atribuye el material a la institución que lo depositó
    Dado que un depósito aprobado trae una matriz con permisos y jerarquía taxonómica
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen queda atribuido a la institución depositante

  Escenario: El sistema deja para revisión el material sin validación taxonómica
    Dado que un depósito trae un registro con la taxonomía sin validar
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen queda pendiente de revisión indicando el motivo

  Escenario: El sistema ingresa el material con el régimen de custodia del trámite
    Dado que un depósito aprobado ingresa con el régimen "<regimen>"
    Cuando el sistema ingresa el lote a la colección
    Entonces el espécimen queda bajo el régimen "<regimen>"

    Ejemplos:
      | regimen    |
      | Temporal   |
      | Permanente |
      | Cuarentena |
