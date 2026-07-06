# Firma digital automática del acta (curador)

Cuando el curador **aprueba** un acta (`ValidarActaFirmada`), el PDF se firma
criptográficamente (PAdES) en el servidor con el certificado `.p12` del curador,
usando **pyHanko** vía `Symfony\Process`. Sin FirmaEC manual.

## Requisitos de servidor (calibración)

1. **Instalar la CLI de pyHanko** (Python 3.9+). ⚠️ Desde pyHanko 0.29 la CLI está en
   un paquete **separado** (`pyhanko-cli`); `pip install pyHanko` NO trae el comando:
   ```bash
   pip install pyhanko-cli          # instala también pyhanko + click
   pyhanko --version                # -> pyHanko, version 0.35.x (CLI 0.4.x)
   ```
2. **`PYHANKO_BIN` debe apuntar a un ejecutable único**, no a `python -m pyhanko`
   (Symfony\Process lo trataría como un solo argv y fallaría).
   - Linux (prod): `PYHANKO_BIN=pyhanko` (queda en el PATH tras `pip install`).
   - Windows (dev): ruta al `pyhanko.exe` del venv, p. ej.
     `C:\Users\...\venv\Scripts\pyhanko.exe`.
3. **PATH del worker de Supervisor.** Igual que `pdfsig`, el worker no hereda el
   entorno; el adaptador pasa `HOME` explícito. Asegura `pyhanko` en el PATH del worker.
4. **Variables de entorno** (`.env`):
   ```
   PYHANKO_BIN=pyhanko
   FIRMA_CAMPO=1/50,50,300,120/FirmaCurador   # pagina/x1,y1,x2,y2/Nombre — ajustar a la plantilla del acta
   FIRMA_TSA_URL=                              # URL de la TSA; vacío = PAdES-B-B sin sello de tiempo
   ```

## Contraseña por archivo, no por stdin

Verificado en pyHanko 0.35 / CLI 0.4: pasar la contraseña por **stdin cuelga** el
proceso (pyHanko la pide del terminal). El adaptador la escribe en un archivo temporal
`0600` y usa `--passfile`. Comando real que ejecuta:

```bash
pyhanko sign addsig --field "1/50,50,300,120/FirmaCurador" \
    pkcs12 --passfile <archivo_temp> entrada.pdf salida.pdf cert.p12
```

Prueba manual una vez (confirma flags si actualizas pyHanko):
```bash
openssl req -x509 -newkey rsa:2048 -keyout k.pem -out c.pem -days 365 -nodes -subj "//CN=PRUEBA"
openssl pkcs12 -export -in c.pem -inkey k.pem -out test.p12 -passout pass:1234
printf 1234 > pass.txt
pyhanko sign addsig --field "1/50,50,300,120/Sig" pkcs12 --passfile pass.txt entrada.pdf salida.pdf test.p12
pdfsig salida.pdf     # (Linux) debe reportar "Signature #1"
```
En git-bash de Windows usa `//CN=` (el `/CN=` se convierte a ruta).

## Registro del certificado del curador

El curador sube su `.p12` + contraseña una sola vez en
`/prestamos/curador/certificado`. Se guardan **cifrados** (`Crypt` / `APP_KEY`) en
`prestamos.certificados_curador`. Sin certificado registrado, aprobar lanza
`CertificadoCuradorNoConfigurado`.

## Notas legales y de seguridad

- La firma electrónica en Ecuador es **personal e intransferible**. Custodiar el
  `.p12` del curador en el servidor y firmar automáticamente es una **zona gris**:
  rompe el control exclusivo de la clave y el no-repudio. Alternativa recomendada a
  futuro: usar un **certificado institucional** (emitido a la institución/sistema),
  mismo código cambiando solo el origen del `.p12`.
- El secreto se cifra con `APP_KEY`. Si `APP_KEY` se filtra, se filtran las claves.
  Nunca loguear el `.p12` ni la contraseña (el adaptador ya lo evita).

## OpenSSL 3

Si `openssl_pkcs12_read` falla con un `.p12` legado en OpenSSL 3, habilita el
proveedor legacy o reempaqueta el certificado con cifrado moderno.
