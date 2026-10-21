#!/usr/bin/env python3
"""Precarga local y acotada, sin preguntas, transcripciones ni acceso a la nube."""
import json
import time
import urllib.error
import urllib.request

for attempt in range(15):
    try:
        with urllib.request.urlopen("http://127.0.0.1:11434/api/tags", timeout=2) as response:
            models = json.load(response).get("models", [])
        if not any(item.get("name") == "qwen3:4b" for item in models):
            # La primera instalación todavía debe descargar los pesos fijados.
            raise SystemExit(0)
        break
    except OSError:
        if attempt == 14:
            raise SystemExit("El servicio local no respondió al precalentamiento.")
        time.sleep(1)

request = urllib.request.Request("http://127.0.0.1:11434/api/generate",
    data=b'{"model":"qwen3:4b","stream":false,"keep_alive":-1,"options":{"num_ctx":1024,"num_thread":2}}',
    headers={"Content-Type": "application/json"})
with urllib.request.urlopen(request, timeout=45) as response:
    result = json.load(response)
if not result.get("done"):
    raise SystemExit("No se confirmó la precarga de Qwen.")
print("Qwen3-4B precargado en CPU; sin transcripción ni consulta de usuario.")
