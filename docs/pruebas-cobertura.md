# Distribución de cobertura

crear-paquete-oci centraliza Pest con PostgreSQL, Behat, Java, Python, Node,
compilaciones y la integridad de las fuentes del paquete. No se ejecutan esas
suites por separado durante una tarea ordinaria.

Pest cubre persistencia, permisos, aislamiento entre usuarios, integridad de
documentos, navegación del depósito, cantidades positivas, localidad opcional
y recuperación de errores. Java comprueba criptografía y estructura PDF. Behat
conserva los contratos de negocio y flujos de API exclusivos; antes de agregar
un escenario se comparan sus entradas, flujo y expectativas con Pest. Node y
la prueba de navegador cubren los contratos del frontend.

El validador Windows ejecuta Pest en hasta cuatro procesos, cada uno con una copia
temporal propia de PostgreSQL y su almacenamiento separado. Las transacciones
y los fixtures sólo afectan esas copias; al finalizar se retiran. Las rutas y
la configuración se reutilizan en memoria sin retirar casos ni aserciones.

Los recorridos manuales complementarios utilizan Opera Neon QA en localhost.
Los PDF privados de docs se revisan directamente en ese navegador por
solicitud del usuario y no se incorporan al paquete. No se conservan capturas,
informes ni resultados de esos recorridos.

La interpretación opcional Qwen se cubre en Pest mediante HTTP simulado: exclusión
de localhost y dev aunque se copie la variable, lista de intenciones autorizadas,
caché, concurrencia, indisponibilidad y conservación de los controles documentales.
Una intención inferida en el portal se ofrece como pregunta para confirmar;
en la ayuda documental la orientación se expresa de forma condicional.
Las menciones de rúbrica electrónica se atienden directamente con las reglas de firma.
No duplica estos casos en Gherkin. La medición manual del backend ARM64 sólo
comprueba latencia y recursos del servicio instalado; no repite esas suites.
