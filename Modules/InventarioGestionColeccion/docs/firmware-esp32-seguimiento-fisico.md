# Firmware ESP32 — Seguimiento Físico v1.0 (iteración mínima)

2 lectores RC522 conectados directo al ESP32, sin multiplexor.
Valida el ciclo completo: leer UID → POST a Laravel → registrar evento.

---

## Conexión

Los dos RC522 comparten el bus SPI. Solo difieren en el pin CS.

```
ESP32           RC522-0 (slot 0)    RC522-1 (slot 1)
GPIO 23 (MOSI)  → MOSI              → MOSI
GPIO 19 (MISO)  → MISO              → MISO
GPIO 18 (SCK)   → SCK               → SCK
GPIO 5  (CS_0)  → SDA
GPIO 15 (CS_1)              →       SDA
GPIO 0  (RST)   → RST               → RST  (compartido)
3.3V            → VCC               → VCC
GND             → GND               → GND
```

---

## Dependencias (PlatformIO)

```ini
; platformio.ini
[env:esp32dev]
platform  = espressif32
board     = esp32dev
framework = arduino
lib_deps  =
    miguelbalboa/MFRC522 @ ^1.4.11
    bblanchon/ArduinoJson @ ^7.0.0
```

Arduino IDE: instalar `MFRC522` y `ArduinoJson` desde el gestor de librerías.
`WiFi`, `HTTPClient` y `Preferences` vienen con el paquete ESP32 de Espressif.

---

## Provisión (una sola vez)

Cargar este sketch para grabar la configuración en la flash del ESP32:

```cpp
// provisioning.ino — cargar una vez, luego reemplazar por main.ino
#include <Preferences.h>

void setup() {
    Serial.begin(115200);
    Preferences p;
    p.begin("hub-digital", false);
    p.putString("wifi_ssid",   "TuRed");
    p.putString("wifi_pass",   "TuClave");
    p.putString("api_url",     "https://tu-dominio.com");
    p.putString("api_token",   "1|token-sanctum-del-esp32");
    p.putString("gabinete_id", "uuid-del-gabinete-en-bd");
    p.end();
    Serial.println("OK");
}
void loop() {}
```

El `gabinete_id` y el `api_token` se obtienen al completar el setup del backend
(ver sección de backend al final).

---

## Firmware principal

```cpp
// main.ino
#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// ── Pines ────────────────────────────────────────────────
constexpr uint8_t PIN_RST  = 0;
constexpr uint8_t CS_PINS[] = {5, 15};   // un pin CS por lector
constexpr uint8_t NUM_SLOTS = 2;

// ── Estado ───────────────────────────────────────────────
MFRC522 readers[NUM_SLOTS] = {
    MFRC522(CS_PINS[0], PIN_RST),
    MFRC522(CS_PINS[1], PIN_RST),
};

struct Slot { bool ocupado = false; char uid[9] = {}; };
Slot slots[NUM_SLOTS];

char apiUrl[128], apiToken[128], gabineteId[37];

// ── Helpers ───────────────────────────────────────────────
void formatUid(MFRC522& r, char* out) {
    snprintf(out, 9, "%02X%02X%02X%02X",
        r.uid.uidByte[0], r.uid.uidByte[1],
        r.uid.uidByte[2], r.uid.uidByte[3]);
}

bool postEvento(const char* tagUid, uint8_t slotIndex, const char* evento) {
    HTTPClient http;
    char url[200];
    snprintf(url, sizeof(url), "%s/api/v1/seguimiento-fisico/eventos", apiUrl);

    http.begin(url);
    http.addHeader("Content-Type",  "application/json");
    http.addHeader("Accept",        "application/json");
    http.addHeader("Authorization", (String("Bearer ") + apiToken).c_str());
    http.setTimeout(8000);

    JsonDocument doc;
    doc["tag_uid"]     = tagUid;
    doc["gabinete_id"] = gabineteId;
    doc["slot_index"]  = slotIndex;
    doc["evento"]      = evento;
    String body; serializeJson(doc, body);

    int code = http.POST(body);
    http.end();

    Serial.printf("[HTTP %d] %s slot=%d uid=%s\n", code, evento, slotIndex, tagUid);
    return code == 200 || code == 422;  // 422 = dominio rechazó, no reintentar
}

// ── Setup ─────────────────────────────────────────────────
void setup() {
    Serial.begin(115200);

    Preferences p; p.begin("hub-digital", true);
    char ssid[64], pass[64];
    p.getString("wifi_ssid",   ssid,       sizeof(ssid));
    p.getString("wifi_pass",   pass,       sizeof(pass));
    p.getString("api_url",     apiUrl,     sizeof(apiUrl));
    p.getString("api_token",   apiToken,   sizeof(apiToken));
    p.getString("gabinete_id", gabineteId, sizeof(gabineteId));
    p.end();

    WiFi.begin(ssid, pass);
    Serial.print("WiFi...");
    while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
    Serial.println(" OK");

    SPI.begin(18, 19, 23);
    for (auto& r : readers) { r.PCD_Init(); }

    Serial.printf("Listo — %d slots — gabinete %s\n", NUM_SLOTS, gabineteId);
}

// ── Loop ──────────────────────────────────────────────────
void loop() {
    for (uint8_t i = 0; i < NUM_SLOTS; i++) {
        MFRC522& r = readers[i];
        char uid[9] = {};
        bool presente = r.PICC_IsNewCardPresent() && r.PICC_ReadCardSerial();

        if (presente) {
            formatUid(r, uid);
            r.PICC_HaltA();
            r.PCD_StopCrypto1();
        }

        if (presente != slots[i].ocupado) {
            const char* ev = presente ? "ingreso" : "retiro";
            const char* uidEvento = presente ? uid : slots[i].uid;
            if (postEvento(uidEvento, i, ev)) {
                slots[i].ocupado = presente;
                strncpy(slots[i].uid, presente ? uid : "", 9);
            }
        }

        delay(50);
    }
}
```

---

## Setup del backend

**1. Crear el gabinete**
```http
POST /api/v1/gabinetes
{ "codigo": "GAB-01", "nombre": "Prototipo", "total_ranuras": 2 }
```

**2. Crear las 2 ranuras** (`numero_ranura` coincide con `slot_index` del firmware)
```http
POST /api/v1/gabinetes/{id}/ranuras   → { "numero_ranura": 0 }
POST /api/v1/gabinetes/{id}/ranuras   → { "numero_ranura": 1 }
```

**3. Registrar cada caja** (`codigo_rfid` = UID leído del tag, 8 hex chars)
```http
POST /api/v1/cajas
{ "codigo": "CAJA-001", "codigo_rfid": "04AB1C2D" }
```

**4. Crear el token Sanctum para el ESP32**
```bash
php artisan tinker --execute '
    echo \App\Models\User::first()->createToken("esp32")->plainTextToken;
'
```

Usar ese token en `provisioning.ino` y flashear.
