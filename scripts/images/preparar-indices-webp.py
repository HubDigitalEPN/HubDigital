"""Prepara derivados responsivos sin modificar ni copiar firmas del PNG original.

Uso: python scripts/images/preparar-indices-webp.py
Requiere Pillow con soporte WebP. No forma parte de las validaciones del paquete.
"""

from io import BytesIO
from pathlib import Path

from PIL import Image


def main() -> None:
    directory = Path(__file__).resolve().parents[2] / "public" / "images" / "indices"
    for source in sorted(directory.glob("*.png")):
        with Image.open(source) as original:
            for width in (320, 640):
                height = round(original.height * width / original.width)
                resized = original.convert("RGB").resize((width, height), Image.Resampling.LANCZOS)
                for quality in (85, 80, 75, 70, 65, 60):
                    encoded = BytesIO()
                    resized.save(encoded, format="WEBP", quality=quality, method=6)
                    content = encoded.getvalue()
                    if len(content) <= 200_000:
                        break
                else:
                    raise RuntimeError(f"No se pudo preparar {source.name} a {width}px bajo 200000 bytes")
                destination = source.with_name(f"{source.stem}-{width}.webp")
                destination.write_bytes(content)
                print(f"{destination.name}: {width}x{height}, {len(content)} bytes, calidad {quality}")


if __name__ == "__main__":
    main()
