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
GPIO 22 (RST)   → RST               → RST  (compartido — no usar GPIO 0, es strapping pin)
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
    p.putString("wifi_ssid",   "CHAROF");
    p.putString("wifi_pass",   "ALEJO29-11-03");
    p.putString("api_url",     "https://semisweet-nonfavorable-milena.ngrok-free.dev");
    p.putString("api_token",   "1|HXNqdS7MAgE4yPeewPrbUMsXUNGUDhr8THL17Ip5ff13bd6a");
    p.putString("gabinete_id", "4fa2cd1a-a759-413c-802a-a0a77675cf99");
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
    constexpr uint8_t PIN_RST    = 22;     // NO usar GPIO 0 — es strapping pin del ESP32
    constexpr uint8_t CS_PINS[]  = {5};   // un pin CS por lector activo
    constexpr uint8_t NUM_SLOTS  = 1;

    // ── Estado ───────────────────────────────────────────────
    MFRC522 readers[NUM_SLOTS] = {
        MFRC522(CS_PINS[0], PIN_RST),
    };

    struct Slot { bool ocupado = false; char uid[9] = {}; uint8_t misses = 0; };
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
        http.addHeader("Content-Type",              "application/json");
        http.addHeader("Accept",                    "application/json");
        http.addHeader("Authorization",             (String("Bearer ") + apiToken).c_str());
        http.addHeader("ngrok-skip-browser-warning", "1");
        http.setTimeout(15000);

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

        // CS pins HIGH antes de iniciar el bus — evita contención SPI en el arranque
        for (uint8_t i = 0; i < NUM_SLOTS; i++) {
            pinMode(CS_PINS[i], OUTPUT);
            digitalWrite(CS_PINS[i], HIGH);
        }

        SPI.begin(18, 19, 23);
        for (uint8_t i = 0; i < NUM_SLOTS; i++) {
            byte ver = 0x00;
            for (uint8_t attempt = 1; attempt <= 3; attempt++) {
                readers[i].PCD_Init();
                delay(50);
                ver = readers[i].PCD_ReadRegister(MFRC522::VersionReg);
                if (ver == 0x91 || ver == 0x92) break;
                Serial.printf("RC522[%d] intento %d/3 version=0x%02X\n", i, attempt, ver);
                delay(100);
            }
            readers[i].PCD_SetAntennaGain(MFRC522::RxGain_max);
            Serial.printf("RC522[%d] version=0x%02X %s\n", i, ver,
                (ver == 0x91 || ver == 0x92) ? "OK" : "ERROR — revisar cableado");
        }

        Serial.printf("Listo — %d slots — gabinete %s\n", NUM_SLOTS, gabineteId);
        Serial.printf("API: %s\n", apiUrl);
    }

    constexpr uint32_t SWEEP_INTERVAL_MS = 3000;   // barrido cada 3 s
    constexpr uint32_t RETRY_BACKOFF_MS  = 15000;  // esperar 15 s antes de reintentar un POST fallido

    // ── Loop ──────────────────────────────────────────────────
    void loop() {
        static uint32_t lastSweep          = 0;
        static uint32_t retryAfter[NUM_SLOTS] = {};

        if (millis() - lastSweep < SWEEP_INTERVAL_MS) return;
        lastSweep = millis();

        Serial.printf("--- barrido %lu ms ---\n", millis());

        for (uint8_t i = 0; i < NUM_SLOTS; i++) {
            MFRC522& r = readers[i];
            char uid[9] = {};

            // WUPA despierta tarjetas en IDLE y HALT — necesario para presencia continua.
            // REQA (PICC_IsNewCardPresent) ignora tarjetas en HALT → falso retiro.
            // El resultado de WUPA se ignora deliberadamente: en gabinetes metálicos puede
            // retornar STATUS_TIMEOUT aunque la tarjeta esté presente (reflecciones RF).
            // ReadCardSerial hace su propia secuencia anti-colisión y es más robusto.
            byte bufferATQA[2];
            byte bufferSize = sizeof(bufferATQA);
            r.PICC_WakeupA(bufferATQA, &bufferSize);

            bool presente = r.PICC_ReadCardSerial();

            if (presente) {
                formatUid(r, uid);
                Serial.printf("[slot %d] UID detectado: %s\n", i, uid);
                r.PICC_HaltA();
                r.PCD_StopCrypto1();
            }

            // Debounce retiro: 5 sweeps consecutivos sin lectura antes de confirmar ausencia.
            // 3 era insuficiente — interferencia metálica del gabinete causa falsos negativos.
            if (!presente && slots[i].ocupado) {
                slots[i].misses++;
                Serial.printf("[slot %d] sin tag (%d/5)\n", i, slots[i].misses);
                if (slots[i].misses < 5) continue;
            } else {
                slots[i].misses = 0;
            }

            if (presente == slots[i].ocupado) continue;

            // Backoff: no reintentar un POST fallido hasta pasado RETRY_BACKOFF_MS.
            if (millis() < retryAfter[i]) {
                Serial.printf("[slot %d] en backoff, próximo intento en %lu s\n",
                    i, (retryAfter[i] - millis()) / 1000);
                continue;
            }

            const char* ev        = presente ? "ingreso" : "retiro";
            const char* uidEvento = presente ? uid : slots[i].uid;

            bool ok = postEvento(uidEvento, i, ev);

            // El burst de TX WiFi durante el POST causa dips en 3.3V que pueden resetear
            // los RC522. Reinicializar siempre después de una llamada HTTP, con voltaje ya
            // estabilizado, y restaurar el gain que PCD_Init() borra.
            for (uint8_t j = 0; j < NUM_SLOTS; j++) {
                readers[j].PCD_Init();
                readers[j].PCD_SetAntennaGain(MFRC522::RxGain_max);
            }
            delay(50);

            if (ok) {
                slots[i].ocupado = presente;
                slots[i].misses  = 0;
                strncpy(slots[i].uid, presente ? uid : "", 9);
            } else {
                retryAfter[i] = millis() + RETRY_BACKOFF_MS;
                Serial.printf("[slot %d] POST fallido — reintento en %d s\n", i, RETRY_BACKOFF_MS / 1000);
            }
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
