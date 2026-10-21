#!/usr/bin/env python3
"""Instala solamente el backend CPU oficial; no activa el modelo en Laravel."""
import hashlib
import os
from pathlib import Path
import platform
import pwd
import shutil
import subprocess
import tarfile
import time
import urllib.request

VERSION = "v0.40.5"
ARCHIVE_SHA256 = "fbe71ebb1a10e59b8480c4d2839826b5f9dff1a6d5c142428b2e7db9b1a2c642"
MODEL_LAYER = "3e4cb14174460404e7a233e531675303b2fbf7749c02f91864fe311ab6344e4f"


def run(args, **kwargs):
    return subprocess.run(args, check=True, **kwargs)


if os.geteuid() != 0:
    raise SystemExit("Ejecuta con sudo en la VM de producción.")
env = {}
for line in Path("/etc/hubdigital/hubdigital.env").read_text().splitlines():
    if "=" in line and not line.lstrip().startswith("#"):
        key, value = line.split("=", 1)
        env[key.strip()] = value.strip().strip('"').strip("'")
if env.get("APP_ENV") != "production" or env.get("APP_URL", "").rstrip("/") != "https://labinvepn.org":
    raise SystemExit("Este servicio sólo se instala en labinvepn.org de producción.")
mem_kib = int(Path("/proc/meminfo").read_text().split("MemTotal:", 1)[1].split()[0])
if platform.machine() != "aarch64" or mem_kib < 8 * 1024 * 1024:
    raise SystemExit("Se requiere ARM64 con al menos 8 GiB; la Micro de desarrollo queda excluida.")
if shutil.which("zstd") is None:
    raise SystemExit("Instala el paquete zstd antes de continuar.")

root = Path("/opt/hubdigital/ollama")
target = root / VERSION
root.mkdir(parents=True, exist_ok=True)
archive = root / (VERSION + "-arm64.tar.zst")
if not (target / "bin/ollama").is_file() or not (target / "lib/ollama/llama-server").is_file():
    digest = hashlib.sha256()
    request = urllib.request.Request(
        f"https://github.com/ollama/ollama/releases/download/{VERSION}/ollama-linux-arm64.tar.zst",
        headers={"User-Agent": "HubDigital-Deployment"},
    )
    try:
        with urllib.request.urlopen(request, timeout=60) as response, archive.open("wb") as output:
            while chunk := response.read(1024 * 1024):
                digest.update(chunk)
                output.write(chunk)
        if digest.hexdigest() != ARCHIVE_SHA256:
            raise RuntimeError("La distribución no coincide con la huella oficial fijada.")
        target.mkdir(mode=0o755, exist_ok=True)
        process = subprocess.Popen(["zstd", "-dc", str(archive)], stdout=subprocess.PIPE)
        with tarfile.open(fileobj=process.stdout, mode="r|") as bundle:
            for member in bundle:
                relative = Path(member.name.removeprefix("./"))
                # Excluye CUDA, ROCm y los demás backends GPU: esta VM usa CPU.
                allowed = relative == Path("bin/ollama") or (
                    relative.parts[:2] == ("lib", "ollama") and len(relative.parts) == 3
                    and (member.isfile() or member.issym())
                )
                if not allowed or not (member.isfile() or member.issym()):
                    continue
                destination = target / relative
                if ".." in relative.parts or not destination.resolve().is_relative_to(target.resolve()):
                    raise RuntimeError("Ruta inesperada en la distribución oficial.")
                destination.parent.mkdir(mode=0o755, parents=True, exist_ok=True)
                if member.issym():
                    if Path(member.linkname).is_absolute() or not (destination.parent / member.linkname).resolve().is_relative_to(target.resolve()):
                        raise RuntimeError("Enlace de biblioteca fuera de la distribución.")
                    destination.unlink(missing_ok=True)
                    destination.symlink_to(member.linkname)
                    continue
                with bundle.extractfile(member) as source, destination.open("wb") as output:
                    shutil.copyfileobj(source, output)
                destination.chmod(0o755 if member.mode & 0o111 else 0o644)
        if process.wait() != 0 or not (target / "bin/ollama").is_file():
            raise RuntimeError("No se pudo extraer el backend CPU de Ollama.")
    finally:
        archive.unlink(missing_ok=True)

for executable in (target / "bin/ollama", target / "lib/ollama/llama-server", target / "lib/ollama/llama-quantize"):
    if executable.is_file():
        executable.chmod(0o755)
# Registra sólo los SONAME de este directorio, sin cambiar la caché global.
run(["ldconfig", "-n", str(target / "lib/ollama")])

try:
    pwd.getpwnam("hubdigital-ai")
except KeyError:
    run(["useradd", "--system", "--user-group", "--home-dir", "/var/lib/hubdigital-chat-model", "--shell", "/usr/sbin/nologin", "hubdigital-ai"])
current = root / "current"
if current.exists() and not current.is_symlink():
    raise SystemExit("No se reemplaza un directorio ajeno en ollama/current.")
temporary_link = root / "current-new"
temporary_link.unlink(missing_ok=True)
temporary_link.symlink_to(target, target_is_directory=True)
temporary_link.replace(current)
service = Path(__file__).resolve().parents[1] / "systemd/hubdigital-chat-model.service"
warm_script = Path(__file__).resolve().parent / "warm-chat-model.py"
shutil.copyfile(warm_script, root / "warm-chat-model.py")
(root / "warm-chat-model.py").chmod(0o755)
shutil.copyfile(service, "/etc/systemd/system/hubdigital-chat-model.service")
Path("/etc/systemd/system/hubdigital-chat-model.service").chmod(0o644)
run(["systemctl", "daemon-reload"])
run(["systemctl", "enable", "--now", "hubdigital-chat-model.service"])
print("Servicio CPU instalado sólo en loopback; se conserva la configuración de activación de Laravel.", flush=True)
for attempt in range(30):
    try:
        with urllib.request.urlopen("http://127.0.0.1:11434/api/tags", timeout=2):
            break
    except OSError:
        if attempt == 29:
            raise SystemExit("Ollama no quedó disponible; Laravel sigue deshabilitado.")
        time.sleep(1)
request = urllib.request.Request("http://127.0.0.1:11434/api/pull",
    data=b'{"model":"qwen3:4b","stream":false}', headers={"Content-Type": "application/json"})
with urllib.request.urlopen(request, timeout=1200) as response:
    response.read()
blob = Path("/var/lib/hubdigital-chat-model/models/blobs/sha256-" + MODEL_LAYER)
with blob.open("rb") as model_file:
    approved = hashlib.file_digest(model_file, "sha256").hexdigest() == MODEL_LAYER
if not approved:
    run(["systemctl", "stop", "hubdigital-chat-model.service"])
    raise SystemExit("Los pesos de Qwen no coinciden con el modelo aprobado; servicio detenido.")
run(["systemctl", "restart", "hubdigital-chat-model.service"])
print("Qwen3-4B descargado y verificado; proceso limitado a 4 GiB. No se modificó ninguna configuración de desarrollo ni de localhost.")
