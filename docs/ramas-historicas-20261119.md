# Revisión de ramas históricas — 19 de noviembre de 2026

Se comparó el commit `35b73694ed948d82b4620f87aa3319182ff056d8` de
`fix/navegador-por-casos-20261028` con `main` en `bfcee6cf`.
Su parche completo no es equivalente por identidad, pero no requiere fusión:
los 18 archivos siguen presentes y sus contratos fueron incorporados o sustituidos
por reglas posteriores. La rama se conserva como referencia histórica.

| Archivos del parche | Resultado de la comparación |
| --- | --- |
| `AGENTS.md`, `README.md`, `docs/pruebas-cobertura.md`, `docs/validacion-agente-y-paquete.md` | Sustituidos por el acuerdo vigente de selección acotada, responsabilidades 30/70 y evidencia por sector/caso. |
| `composer.lock` | Conserva `symfony/mime` v8.1.10 y la referencia del parche. |
| `bases-sectores-agente.cjs`, `evidencia-agente.cjs` | Conservan todas las funciones del parche y añaden controles posteriores de compatibilidad. |
| `impacto-validacion.cjs`, `plan-validacion.json` | Conservan las funciones; aplican selección propia del cambio y detención ante cobertura desconocida. |
| `crear-paquete-oci-core.ps1`, `validar-cambios-agente.ps1`, `windows/validar-postgresql-temporal.ps1` | Conservan el flujo de evidencia y selección, con recibos posteriores de PDF, navegador y controles focalizados. |
| `progreso-pruebas-php.cjs` | Conserva funciones y exportaciones; añade argumentos filtrados de Pest y puntos de avance de archivos completos. |
| Pruebas `bases-sectores-agente`, `evidencia-agente`, `progreso-pruebas-php` | Se conservan los títulos de todos los casos históricos. |
| Pruebas `impacto-validacion` | Cinco títulos antiguos fueron sustituidos: exigían ejecutar integral automáticamente por falta de base, incertidumbre, cambios críticos, antigüedad o consumidores transitivos. Los casos actuales comprueban selección acotada y detención sin ampliación automática, conforme a `AGENTS.md`. |
| `PdfSignatureCli.java` | Conserva rechazo de QR inexistente y comparación exacta del original, certificado y URL a 144/220 dpi. El lector actual incorpora además el barrido posterior de filas y pruebas de posición; reemplazarlo por el histórico perdería esas mejoras. |

La comparación de nombres de funciones y casos es auxiliar; se revisaron también
los cambios de política y las aserciones QR. No acredita una nueva ejecución de
las suites históricas. La evidencia local incluye huellas de cada archivo y el
último commit que lo modificó en `main`, en
`.local/validation/cierre-pendientes-20261119/history-comparison.json`.

`feature/coleccion-biologica-investigacion-20260930` ya constaba como equivalente
en el cierre anterior; no se vuelve a fusionar ni se borra ninguna rama.
