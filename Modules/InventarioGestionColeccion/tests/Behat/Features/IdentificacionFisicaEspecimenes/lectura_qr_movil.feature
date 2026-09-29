#language: es
@listo
Característica: Resolución del QR a la ficha digital del espécimen
  Como curador o investigador en campo,
  quiero escanear el código QR de un espécimen y obtener su ficha digital,
  para verificar su información sin acceder manualmente al sistema.

  Escenario: Resolver QR válido a la ficha del espécimen
    Dado que existe un espécimen con su código QR generado
    Cuando se resuelve el QR con el payload correcto del espécimen
    Entonces se retorna la ficha digital completa del espécimen

  Escenario: QR con payload inválido retorna error
    Dado que se intenta resolver un QR con un payload que no corresponde a ningún espécimen
    Cuando se procesa el payload del QR inválido
    Entonces el sistema retorna un error indicando que el espécimen no fue encontrado

  Escenario: Corregir datos de campo conserva la etiqueta y actualiza la ficha al escanear
    Dado que existe un espécimen con su código QR generado
    Cuando el curador corrige la localidad a "Imbabura, Ecuador" y el colector a "María Gómez" sin cambiar la etiqueta
    Y se resuelve el QR con el payload correcto del espécimen
    Entonces la ficha del QR muestra la localidad "Imbabura, Ecuador" y el colector "María Gómez"
    Y la ficha resuelta desde la misma etiqueta muestra el taxón "Morpho peleides"
    Y la etiqueta conserva el mismo espécimen, código de catálogo y payload

  Escenario: Una nueva determinación científica aparece al escanear la etiqueta existente
    Dado que existe un espécimen con su código QR generado
    Cuando el curador identifica el espécimen etiquetado como "Morpho menelaus"
    Y se resuelve el QR con el payload correcto del espécimen
    Entonces la ficha resuelta desde la misma etiqueta muestra el taxón "Morpho menelaus"
    Y la etiqueta conserva el mismo espécimen, código de catálogo y payload

  Escenario: Corregir un espécimen mantiene independientes las fichas de dos etiquetas
    Dado que existen dos especímenes con etiquetas QR independientes
    Cuando el curador corrige la localidad a "Imbabura, Ecuador" y el colector a "María Gómez" sin cambiar la etiqueta
    Y el visitante escanea las dos etiquetas originales
    Entonces la ficha del QR muestra la localidad "Imbabura, Ecuador" y el colector "María Gómez"
    Y la segunda etiqueta conserva la localidad "Pichincha, Ecuador" y el colector "Ana Torres"
    Y la etiqueta conserva el mismo espécimen, código de catálogo y payload

  Escenario: Identificar un espécimen previamente indeterminado no exige reimprimir su etiqueta
    Dado que existe un espécimen sin determinar con su código QR generado
    Cuando se resuelve el QR con el payload correcto del espécimen
    Entonces la ficha del QR todavía no presenta una identificación taxonómica
    Cuando el curador identifica el espécimen etiquetado como "Atta cephalotes"
    Y se resuelve el QR con el payload correcto del espécimen
    Entonces la ficha resuelta desde la misma etiqueta muestra el taxón "Atta cephalotes"
    Y la etiqueta conserva el mismo espécimen, código de catálogo y payload
