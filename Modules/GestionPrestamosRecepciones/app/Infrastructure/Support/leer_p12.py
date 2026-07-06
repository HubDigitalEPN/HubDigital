"""Extrae el certificado (PEM) de un PKCS#12, tolerando cifrado legacy (RC2).

Uso: python leer_p12.py <ruta.p12>   (la contraseña se lee de stdin)
Imprime el certificado en PEM por stdout. Sale con código != 0 si el archivo o
la contraseña no son válidos. Usa la librería `cryptography` (la misma que pyHanko),
que lee PKCS#12 legacy sin necesitar el proveedor legacy de OpenSSL.
"""
import sys

from cryptography.hazmat.primitives.serialization import Encoding, pkcs12


def main() -> int:
    if len(sys.argv) < 2:
        return 2

    password = sys.stdin.buffer.read() or None

    with open(sys.argv[1], "rb") as fh:
        data = fh.read()

    try:
        _key, cert, _extra = pkcs12.load_key_and_certificates(data, password)
    except Exception:
        return 1

    if cert is None:
        return 1

    sys.stdout.buffer.write(cert.public_bytes(Encoding.PEM))
    return 0


if __name__ == "__main__":
    sys.exit(main())
