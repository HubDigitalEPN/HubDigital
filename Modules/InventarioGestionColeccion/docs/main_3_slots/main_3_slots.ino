// main.ino — Seguimiento Físico v1.1 (3 slots)
// Escalado de 2/1 a 3 lectores RC522 directo al ESP32 (sin multiplexor).
// Mantiene la arquitectura original: WUPA para presencia continua,
// debounce de retiro, backoff de POST y reinit de lectores tras cada HTTP.
//
// Pines CS validados en hardware: 15, 4, 5 (orden fisico arriba->abajo).
// NO usar GPIO 0, 2 ni 15: son strapping pins y dan lecturas 0x00 / 0xEE.

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// ── Pines ────────────────────────────────────────────────
constexpr uint8_t PIN_RST    = 22;          // compartido por los 3 lectores
constexpr uint8_t CS_PINS[]  = {15, 4, 5};  // un CS por lector (arriba, medio, abajo)
constexpr uint8_t NUM_SLOTS  = 3;

// ── Estado ───────────────────────────────────────────────
MFRC522 readers[NUM_SLOTS] = {
    MFRC522(CS_PINS[0], PIN_RST),
    MFRC522(CS_PINS[1], PIN_RST),
    MFRC522(CS_PINS[2], PIN_RST),
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
    static uint32_t lastSweep             = 0;
    static uint32_t retryAfter[NUM_SLOTS] = {0, 0, 0};
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
        // Debounce retiro: 3 sweeps consecutivos sin lectura antes de confirmar ausencia.
        if (!presente && slots[i].ocupado) {
            slots[i].misses++;
            Serial.printf("[slot %d] sin tag (%d/3)\n", i, slots[i].misses);
            if (slots[i].misses < 3) continue;
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
