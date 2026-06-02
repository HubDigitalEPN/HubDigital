// main_prod.ino — Seguimiento Físico v2.0 (3 slots, concurrente) — PRODUCCION
// Igual que main_3_slots_concurrente.ino, con dos cambios de RED:
//   1) Conexion WiFi WPA2/WPA3 Enterprise (EPN-LA100, PEAP-MSCHAPv2).
//   2) Servidor REST LOCAL por HTTP plano (WiFiClient), no TLS/ngrok.
// La arquitectura de dos tareas FreeRTOS + cola + mutex SPI NO cambia.

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <WiFiClient.h>          // HTTP plano (antes: WiFiClientSecure)
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// Soporte EAP para core 2.x (esp_wpa2.h) y 3.x (esp_eap_client.h).
#if __has_include(<esp_eap_client.h>)
  #include <esp_eap_client.h>
  #define USE_EAP_CLIENT 1
#else
  #include "esp_wpa2.h"
  #define USE_EAP_CLIENT 0
#endif

// ── Pines ────────────────────────────────────────────────
constexpr uint8_t PIN_RST   = 22;
constexpr uint8_t CS_PINS[] = {15, 4, 5};
constexpr uint8_t NUM_SLOTS = 3;

// ── Estado de lectores ────────────────────────────────────
MFRC522 readers[NUM_SLOTS] = {
    MFRC522(CS_PINS[0], PIN_RST),
    MFRC522(CS_PINS[1], PIN_RST),
    MFRC522(CS_PINS[2], PIN_RST),
};
struct Slot { bool ocupado = false; char uid[9] = {}; uint8_t misses = 0; };
Slot slots[NUM_SLOTS];

// ── Config (flash) ────────────────────────────────────────
char apiUrl[128], apiToken[128], gabineteId[37];

// ── Primitivas de concurrencia ────────────────────────────
struct Evento { char uid[9]; uint8_t slot; bool ingreso; };
QueueHandle_t     colaEventos;
SemaphoreHandle_t spiMutex;
constexpr uint8_t COLA_TAM = 32;

// ── Parametros de tiempo ──────────────────────────────────
constexpr uint32_t SWEEP_INTERVAL_MS = 250;
constexpr uint8_t  MISSES_RETIRO     = 3;
constexpr uint32_t POST_GAP_MS       = 100;
constexpr uint32_t RETRY_FAST_MS     = 1500;
constexpr uint32_t RETRY_BACKOFF_MS  = 15000;

// ── Helpers ───────────────────────────────────────────────
void formatUid(MFRC522& r, char* out) {
    snprintf(out, 9, "%02X%02X%02X%02X",
        r.uid.uidByte[0], r.uid.uidByte[1],
        r.uid.uidByte[2], r.uid.uidByte[3]);
}

void initLector(uint8_t i) {
    readers[i].PCD_Init();
    readers[i].PCD_SetAntennaGain(MFRC522::RxGain_max);
}

// ── Conexion WiFi Enterprise ──────────────────────────────
// Lee las claves EAP desde NVS y asocia a EPN-LA100. Devuelve al asociar.
bool conectarEnterprise(const char* ssid, const char* identity,
                        const char* user, const char* pass) {
    WiFi.disconnect(true);
    delay(200);
    WiFi.mode(WIFI_STA);

#if USE_EAP_CLIENT
    // ESP32 Arduino Core 3.x
    esp_eap_client_set_identity((uint8_t*)identity, strlen(identity));
    esp_eap_client_set_username((uint8_t*)user,     strlen(user));
    esp_eap_client_set_password((uint8_t*)pass,     strlen(pass));
    esp_wifi_sta_enterprise_enable();
    WiFi.begin(ssid);
#else
    // ESP32 Arduino Core 2.x
    esp_wifi_sta_wpa2_ent_set_identity((uint8_t*)identity, strlen(identity));
    esp_wifi_sta_wpa2_ent_set_username((uint8_t*)user,     strlen(user));
    esp_wifi_sta_wpa2_ent_set_password((uint8_t*)pass,     strlen(pass));
    esp_wpa2_config_t config = WPA2_CONFIG_INIT_DEFAULT();
    esp_wifi_sta_wpa2_ent_enable(&config);
    WiFi.begin(ssid);
#endif

    Serial.print("WiFi Enterprise...");
    uint32_t t0 = millis();
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
        if (millis() - t0 > 30000) {     // 30s y reintenta el ciclo
            Serial.println(" timeout, reintentando");
            WiFi.disconnect(true); delay(500);
            WiFi.begin(ssid);
            t0 = millis();
        }
    }
    Serial.printf(" OK  IP=%s\n", WiFi.localIP().toString().c_str());
    return true;
}

// ═══════════════════════════════════════════════════════════
//  TAREA SENSADO (core 1)
// ═══════════════════════════════════════════════════════════
void tareaSensado(void* pv) {
    for (;;) {
        for (uint8_t i = 0; i < NUM_SLOTS; i++) {
            MFRC522& r = readers[i];
            char uid[9] = {};

            if (xSemaphoreTake(spiMutex, pdMS_TO_TICKS(500)) != pdTRUE) continue;
            byte atqa[2]; byte sz = sizeof(atqa);
            r.PICC_WakeupA(atqa, &sz);
            bool presente = r.PICC_ReadCardSerial();
            if (presente) {
                formatUid(r, uid);
                r.PICC_HaltA();
                r.PCD_StopCrypto1();
            }
            xSemaphoreGive(spiMutex);

            if (!presente && slots[i].ocupado) {
                if (++slots[i].misses < MISSES_RETIRO) continue;
            } else {
                slots[i].misses = 0;
            }

            if (presente == slots[i].ocupado) continue;

            Evento ev;
            ev.slot    = i;
            ev.ingreso = presente;
            strncpy(ev.uid, presente ? uid : slots[i].uid, 9);

            slots[i].ocupado = presente;
            slots[i].misses  = 0;
            strncpy(slots[i].uid, presente ? uid : "", 9);

            if (xQueueSend(colaEventos, &ev, 0) != pdTRUE) {
                Serial.printf("[sensado] COLA LLENA, evento slot %d perdido\n", i);
            } else {
                Serial.printf("[sensado] %s slot %d uid=%s -> encolado\n",
                    presente ? "INGRESO" : "RETIRO", i, ev.uid);
            }
        }
        vTaskDelay(pdMS_TO_TICKS(SWEEP_INTERVAL_MS));
    }
}

// ═══════════════════════════════════════════════════════════
//  TAREA RED (core 0) — HTTP plano sobre la red local
// ═══════════════════════════════════════════════════════════
// Cliente PLANO persistente: socket TCP que vive entre POST (keep-alive).
// Sin TLS porque el servidor local responde por HTTP en el puerto 80.
WiFiClient netClient;
HTTPClient http;
bool       httpListo = false;

bool asegurarConexion() {
    if (httpListo) return true;

    char url[200];
    snprintf(url, sizeof(url), "%s/api/v1/seguimiento-fisico/eventos", apiUrl);

    netClient.setTimeout(15000);      // red local: 15s de socket

    if (!http.begin(netClient, url)) {
        Serial.println("[red] http.begin fallo");
        return false;
    }
    http.setReuse(true);              // mantiene el socket TCP vivo entre POST
    http.setConnectTimeout(8000);     // red local: 8s para conectar
    http.setTimeout(15000);           // red local: 15s por la respuesta
    http.addHeader("Content-Type",  "application/json");
    http.addHeader("Accept",        "application/json");
    http.addHeader("Authorization", (String("Bearer ") + apiToken).c_str());
    http.addHeader("Connection",    "keep-alive");
    httpListo = true;
    return true;
}

void reabrirConexion() {
    http.end();
    netClient.stop();
    httpListo = false;
}

int postEvento(const Evento& ev) {
    if (!asegurarConexion()) return -1;

    JsonDocument doc;
    doc["tag_uid"]     = ev.uid;
    doc["gabinete_id"] = gabineteId;
    doc["slot_index"]  = ev.slot;
    doc["evento"]      = ev.ingreso ? "ingreso" : "retiro";
    String body; serializeJson(doc, body);

    int code = http.POST(body);

    // -1 sobre socket persistente = el otro extremo cerro la conexion ociosa.
    // Cerramos de nuestro lado para que el reintento reabra limpio.
    if (code <= 0) reabrirConexion();

    Serial.printf("[red] HTTP %d  slot=%d uid=%s\n", code, ev.slot, ev.uid);
    return code;
}

void tareaRed(void* pv) {
    Evento ev;
    for (;;) {
        if (xQueueReceive(colaEventos, &ev, portMAX_DELAY) != pdTRUE) continue;

        int code = postEvento(ev);

        if (xSemaphoreTake(spiMutex, pdMS_TO_TICKS(2000)) == pdTRUE) {
            for (uint8_t j = 0; j < NUM_SLOTS; j++) initLector(j);
            xSemaphoreGive(spiMutex);
        }

        bool exito = (code == 200 || code == 422);

        if (!exito) {
            uint32_t espera;
            if (code <= 0) {
                espera = RETRY_FAST_MS;
                Serial.printf("[red] error conexion (%d) slot %d, reintento en %lu ms\n",
                    code, ev.slot, espera);
            } else if (code >= 500) {
                espera = RETRY_BACKOFF_MS;
                Serial.printf("[red] servidor %d slot %d, reintento en %lu s\n",
                    code, ev.slot, espera / 1000);
            } else {
                Serial.printf("[red] peticion rechazada %d slot %d, descartado\n",
                    code, ev.slot);
                espera = 0;
            }
            if (espera > 0) {
                vTaskDelay(pdMS_TO_TICKS(espera));
                xQueueSend(colaEventos, &ev, 0);
            }
        }

        vTaskDelay(pdMS_TO_TICKS(POST_GAP_MS));
    }
}

// ── Setup ─────────────────────────────────────────────────
void setup() {
    Serial.begin(115200);

    Preferences p; p.begin("hub-digital", true);
    char ssid[64], identity[64], user[64], pass[64];
    p.getString("wifi_ssid",    ssid,       sizeof(ssid));
    p.getString("eap_identity", identity,   sizeof(identity));
    p.getString("eap_user",     user,       sizeof(user));
    p.getString("eap_pass",     pass,       sizeof(pass));
    p.getString("api_url",      apiUrl,     sizeof(apiUrl));
    p.getString("api_token",    apiToken,   sizeof(apiToken));
    p.getString("gabinete_id",  gabineteId, sizeof(gabineteId));
    p.end();

    conectarEnterprise(ssid, identity, user, pass);

    for (uint8_t i = 0; i < NUM_SLOTS; i++) {
        pinMode(CS_PINS[i], OUTPUT);
        digitalWrite(CS_PINS[i], HIGH);
    }
    SPI.begin(18, 19, 23);

    for (uint8_t i = 0; i < NUM_SLOTS; i++) {
        byte ver = 0x00;
        for (uint8_t intento = 1; intento <= 3; intento++) {
            readers[i].PCD_Init();
            delay(50);
            ver = readers[i].PCD_ReadRegister(MFRC522::VersionReg);
            if (ver == 0x91 || ver == 0x92) break;
            delay(100);
        }
        readers[i].PCD_SetAntennaGain(MFRC522::RxGain_max);
        Serial.printf("RC522[%d] (CS=%d) version=0x%02X %s\n",
            i, CS_PINS[i], ver,
            (ver == 0x91 || ver == 0x92) ? "OK" : "ERROR — revisar cableado");
    }

    colaEventos = xQueueCreate(COLA_TAM, sizeof(Evento));
    spiMutex    = xSemaphoreCreateMutex();

    xTaskCreatePinnedToCore(tareaSensado, "sensado", 4096, NULL, 2, NULL, 1);
    xTaskCreatePinnedToCore(tareaRed,     "red",     8192, NULL, 1, NULL, 0);

    Serial.printf("Listo — %d slots — gabinete %s\n", NUM_SLOTS, gabineteId);
    Serial.printf("API: %s\n", apiUrl);
}

void loop() {
    vTaskDelay(pdMS_TO_TICKS(1000));
}
