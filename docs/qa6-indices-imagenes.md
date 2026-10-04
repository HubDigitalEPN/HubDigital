# Derivados responsivos de los índices QA6

Se conservaron los siete PNG originales de `public/images/indices` (1448×1086).
Se prepararon variantes WebP de 320×240 y 640×480 mediante reducción Lanczos y
codificación a calidad 85. No se generó contenido nuevo ni se recortó la composición.

| Índice | PNG original (bytes) | WebP 320 (bytes) | WebP 640 (bytes) |
| --- | ---: | ---: | ---: |
| calidad | 1.815.475 | 14.144 | 35.088 |
| decadas | 2.161.614 | 16.844 | 48.196 |
| especies | 2.314.969 | 20.848 | 59.312 |
| filos | 2.373.614 | 13.688 | 45.856 |
| mapa | 2.301.917 | 24.500 | 64.874 |
| raras | 1.685.696 | 9.856 | 23.124 |
| riqueza | 2.299.512 | 23.304 | 61.964 |
| Total | 14.952.797 | 123.184 | 338.414 |

Todas las variantes 640 quedan por debajo del objetivo inicial de 200.000 bytes por
imagen. Estos son tamaños de los artefactos preparados, no una medición del ahorro
real de red ni de Core Web Vitals. La selección del navegador depende de `srcset`,
`sizes`, ancho efectivo y densidad de píxel.

Los nombres son `<indice>-320.webp` y `<indice>-640.webp`. El script reproducible es
`scripts/images/preparar-indices-webp.py` y requiere Pillow con WebP. La salida es
determinista bajo el mismo codificador; cambios de versión de Pillow/libwebp pueden
cambiar los bytes aun conservando la misma configuración.

Los originales y sus metadatos C2PA permanecen intactos. Las firmas del original
no se copian al derivado como si certificaran sus nuevos bytes. Esta preparación
no valida firmas ni cadenas C2PA. La etiqueta de ilustración temática generada
debe mantenerse en la interfaz y no usarse como identificación de un ejemplar.

Se revisó visualmente una composición comparativa de los siete PNG y sus variantes
640, presentadas al tamaño de tarjeta: se conserva contenido, encuadre y legibilidad
de los motivos. La composición de revisión se guardó en `.local/qa6-indices-preview.png`,
un artefacto local temporal que no forma parte del producto. La selección efectiva
de recursos por el navegador y los distintos viewports requiere su recorrido UX.

No se ejecutó compilación Vite, suite automatizada ni `crear-paquete-oci` para esta
preparación; esas comprobaciones quedan a cargo de la siguiente ejecución completa
autorizada del paquete.
