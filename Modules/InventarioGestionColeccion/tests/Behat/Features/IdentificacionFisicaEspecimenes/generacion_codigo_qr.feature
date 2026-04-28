#language: es
Característica: Generación de código QR vinculado al espécimen
  Como curador responsable de la colección,
  quiero generar un código QR vinculado al GUID de cada espécimen,
  para facilitar su trazabilidad física mediante escaneo.

  Escenario: Generar código QR para un espécimen que aún no tiene QR
    Dado que existe un espécimen registrado con GUID y sin código QR
    Cuando el curador solicita la generación del código QR del espécimen
    Entonces el código QR queda generado y vinculado al espécimen

  Escenario: No se genera un segundo QR si el espécimen ya tiene uno
    Dado que existe un espécimen registrado con su código QR ya generado
    Cuando el curador solicita generar un nuevo código QR para el mismo espécimen
    Entonces el sistema rechaza la generación indicando que el espécimen ya tiene un QR
