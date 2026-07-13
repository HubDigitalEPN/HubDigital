// main_25_slots.ino  —  Seguimiento Físico v3.0
// 25 ranuras | 5 muxes CD74HC4067 × 5 sensores RC522
// Barrido completo → POST /api/v1/seguimiento-fisico/sincronizaciones
//
// ── Pines (esquemático EsquemáticoMadre.png rev 2) ───────────────────────
//
//   Bus SPI (compartido, 25 lectores):
//     MOSI = GP23    MISO = GP19    SCK = GP18
//
//   CS virtual (SIG=GP21):
//     Conecta al pin COM de los 5 muxes.
//     El mux activo enruta GP21 al pin SDA del RC522 seleccionado.
//     La librería MFRC522 gestiona este pin como SS.
//
//   RST compartido:  GP22
//   Dirección mux:   S0=GP25  S1=GP26  S2=GP27  (3 bits → sensores 0-4)
//   Enable muxes (activo LOW, uno por hembra):
//     HEMBRA 1 = GP33   HEMBRA 2 = GP4   HEMBRA 3 = GP13
//     HEMBRA 4 = GP14   HEMBRA 5 = GP16
//
//   Ethernet W5500 (mismo bus SPI, no usado aquí):
//     RST = GP17    CS = GP5
//
// ── Dependencias (PlatformIO) ────────────────────────────────────────────
//   [env:esp32dev]
//   platform  = espressif32
//   board     = esp32dev
//   framework = arduino
//   lib_deps  =
//       miguelbalboa/MFRC522 @ ^1.4.11
//       bblanchon/ArduinoJson @ ^7.0.0
//
// ── Modelo de datos enviado al servidor ──────────────────────────────────
//   POST {api_url}/api/v1/seguimiento-fisico/sincronizaciones
//   {
//     "gabinete_id": "<uuid>",
//     "lecturas": [
//       { "slot_index": 0,  "tag_uid": "A1B2C3D4" },
//       { "slot_index": 1,  "tag_uid": null },
//       ...
//       { "slot_index": 24, "tag_uid": "..." }
//     ]
//   }
//   slot_index es base 0; el servidor lo convierte a número de ranura (base 1).
//   tag_uid null = ranura vacía confirmada por debounce.
// ─────────────────────────────────────────────────────────────────────────

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <WiFiClient.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

#if __has_include(<esp_eap_client.h>)
  #include <esp_eap_client.h>
  #define USE_EAP_CLIENT 1
#else
  #include "esp_wpa2.h"
  #define USE_EAP_CLIENT 0
#endif

// ── Layout ────────────────────────────────────────────────────────────────
constexpr uint8_t NUM_MUXES       = 5;
constexpr uint8_t SENSORS_PER_MUX = 5;
constexpr uint8_t NUM_SLOTS       = NUM_MUXES * SENSORS_PER_MUX;  // 25

// ── Pines ─────────────────────────────────────────────────────────────────
constexpr uint8_t PIN_MUX_SS = 21;   // SIG → COM de todos los muxes → SDA RC522
constexpr uint8_t PIN_RST    = 22;   // RST compartido (todos los RC522)
constexpr uint8_t PIN_S0     = 25;
constexpr uint8_t PIN_S1     = 26;
constexpr uint8_t PIN_S2     = 27;

// Enable individual por hembra (activo LOW).
// Orden: HEMBRA1, HEMBRA2, HEMBRA3, HEMBRA4, HEMBRA5
constexpr uint8_t MUX_EN[NUM_MUXES] = {33, 4, 13, 14, 16};

// ── Objeto RC522 único (reutilizado vía mux) ──────────────────────────────
MFRC522 rfid(PIN_MUX_SS, PIN_RST);

// ── Debounce ──────────────────────────────────────────────────────────────
// Evita reportar retiros falsos por interferencia RF momentánea.
// Un retiro solo se confirma tras MISSES_RETIRO barridos consecutivos sin lectura.
constexpr uint8_t MISSES_RETIRO = 3;
struct EstadoSlot { char uid[9] = {}; uint8_t misses = 0; };
EstadoSlot estadoSlots[NUM_SLOTS];

// ── Snapshot ──────────────────────────────────────────────────────────────
struct Snapshot { char uid[NUM_SLOTS][9]; };  // uid[s]="" → ranura s vacía

// ── Primitivas de concurrencia ────────────────────────────────────────────
QueueHandle_t     colaSnapshot;   // tamaño 1; xQueueOverwrite = siempre el estado más reciente
SemaphoreHandle_t spiMutex;

// ── Config NVS ────────────────────────────────────────────────────────────
char apiUrl[128], apiToken[128], gabineteId[37];

// ── Tiempos ───────────────────────────────────────────────────────────────
constexpr uint32_t SWEEP_MS      = 3000;   // intervalo entre barridos completos
constexpr uint32_t POST_TIMEOUT  = 15000;
constexpr uint32_t RETRY_FAST_MS = 1500;   // reintento si hay error de conexión
constexpr uint32_t RETRY_SLOW_MS = 15000;  // reintento si el servidor devuelve 5xx

// ═════════════════════════════════════════════════════════════════════════
//  Helpers de mux
// ═════════════════════════════════════════════════════════════════════════

static void desactivarTodosMuxes() {
    for (uint8_t m = 0; m < NUM_MUXES; m++) digitalWrite(MUX_EN[m], HIGH);
}

// Activa el canal `sensor` (0-4) del mux `mux` (0-4).
// Desactiva todos los demás muxes primero para cumplir con el TDM
// (nunca dos antenas adyacentes simultáneas).
static void seleccionarSensor(uint8_t mux, uint8_t sensor) {
    desactivarTodosMuxes();
    digitalWrite(PIN_S0, (sensor >> 0) & 1);
    digitalWrite(PIN_S1, (sensor >> 1) & 1);
    digitalWrite(PIN_S2, (sensor >> 2) & 1);
    delayMicroseconds(150);          // S0-S2 deben estabilizarse antes del EN
    digitalWrite(MUX_EN[mux], LOW);
    delayMicroseconds(150);          // mux necesita tiempo para cerrar el switch
}

// Inicializar los 25 lectores recorriendo todos los canales del mux.
// Se llama en setup() y después de cada POST (los burst WiFi bajan VCC
// y pueden reiniciar los RC522).
static void initTodosLosLectores() {
    for (uint8_t s = 0; s < NUM_SLOTS; s++) {
        seleccionarSensor(s / SENSORS_PER_MUX, s % SENSORS_PER_MUX);
        rfid.PCD_Init();
        delay(50);
        rfid.PCD_SetAntennaGain(MFRC522::RxGain_max);
        desactivarTodosMuxes();
        delay(5);
    }
}

// ═════════════════════════════════════════════════════════════════════════
//  WiFi Enterprise (EPN / WPA2-EAP PEAP-MSCHAPv2)
// ═════════════════════════════════════════════════════════════════════════
static void conectarEnterprise(const char* ssid, const char* identity,
                               const char* user,     const char* pass) {
    WiFi.disconnect(true);
    delay(200);
    WiFi.mode(WIFI_STA);

#if USE_EAP_CLIENT
    esp_eap_client_set_identity((uint8_t*)identity, strlen(identity));
    esp_eap_client_set_username((uint8_t*)user,     strlen(user));
    esp_eap_client_set_password((uint8_t*)pass,     strlen(pass));
    esp_wifi_sta_enterprise_enable();
    WiFi.begin(ssid);
#else
    esp_wifi_sta_wpa2_ent_set_identity((uint8_t*)identity, strlen(identity));
    esp_wifi_sta_wpa2_ent_set_username((uint8_t*)user,     strlen(user));
    esp_wifi_sta_wpa2_ent_set_password((uint8_t*)pass,     strlen(pass));
    esp_wpa2_config_t cfg = WPA2_CONFIG_INIT_DEFAULT();
    esp_wifi_sta_wpa2_ent_enable(&cfg);
    WiFi.begin(ssid);
#endif

    Serial.print("WiFi Enterprise");
    uint32_t t0 = millis();
    while (WiFi.status() != WL_CONNECTED) {
        delay(500); Serial.print(".");
        if (millis() - t0 > 30000) {
            Serial.println(" timeout, reintentando");
            WiFi.disconnect(true); delay(500);
            WiFi.begin(ssid); t0 = millis();
        }
    }
    Serial.printf(" OK  IP=%s\n", WiFi.localIP().toString().c_str());
}

// ═════════════════════════════════════════════════════════════════════════
//  TAREA SENSADO (core 1)
//  Barre las 25 ranuras de forma secuencial (TDM), aplica debounce
//  y publica el snapshot completo a la cola.
// ═════════════════════════════════════════════════════════════════════════

static void escanearSlot(uint8_t slot) {
    uint8_t mux    = slot / SENSORS_PER_MUX;
    uint8_t sensor = slot % SENSORS_PER_MUX;

    xSemaphoreTake(spiMutex, portMAX_DELAY);

    seleccionarSensor(mux, sensor);

    char uid[9] = {};
    byte atqa[2]; byte sz = sizeof(atqa);
    // WUPA detecta tarjetas en estado HALT (presencia continua sin falsos retiros).
    rfid.PICC_WakeupA(atqa, &sz);
    bool presente = rfid.PICC_ReadCardSerial();
    if (presente) {
        snprintf(uid, 9, "%02X%02X%02X%02X",
            rfid.uid.uidByte[0], rfid.uid.uidByte[1],
            rfid.uid.uidByte[2], rfid.uid.uidByte[3]);
        rfid.PICC_HaltA();
        rfid.PCD_StopCrypto1();
    }

    desactivarTodosMuxes();   // aislar antena antes de pasar a la siguiente (TDM)
    xSemaphoreGive(spiMutex);

    // Debounce: solo confirmar retiro tras MISSES_RETIRO lecturas vacías consecutivas.
    if (presente) {
        estadoSlots[slot].misses = 0;
        memcpy(estadoSlots[slot].uid, uid, 9);
    } else if (estadoSlots[slot].uid[0] != '\0') {
        if (++estadoSlots[slot].misses >= MISSES_RETIRO) {
            estadoSlots[slot].uid[0] = '\0';
            estadoSlots[slot].misses = 0;
        }
        // Si misses < umbral: uid se conserva → snapshot aún reporta ranura ocupada
    }
}

void tareaSensado(void* pv) {
    Snapshot snap;
    for (;;) {
        for (uint8_t s = 0; s < NUM_SLOTS; s++) escanearSlot(s);

        // Construir snapshot con el estado debounced
        for (uint8_t s = 0; s < NUM_SLOTS; s++)
            memcpy(snap.uid[s], estadoSlots[s].uid, 9);

        // xQueueOverwrite nunca bloquea: si la tarea de red aún no procesó el
        // anterior, lo reemplaza con el estado más reciente.
        xQueueOverwrite(colaSnapshot, &snap);

        vTaskDelay(pdMS_TO_TICKS(SWEEP_MS));
    }
}

// ═════════════════════════════════════════════════════════════════════════
//  TAREA RED (core 0)
//  Recibe el snapshot, serializa el JSON y lo envía al servidor.
//  Usa un socket TCP persistente (keep-alive) sobre HTTP plano (red local).
// ═════════════════════════════════════════════════════════════════════════
WiFiClient netClient;
HTTPClient http;
bool       httpListo = false;

static bool asegurarConexion() {
    if (WiFi.status() != WL_CONNECTED) return false;
    if (httpListo) return true;

    char url[200];
    snprintf(url, sizeof(url),
        "%s/api/v1/seguimiento-fisico/sincronizaciones", apiUrl);

    netClient.setTimeout(POST_TIMEOUT / 1000);
    if (!http.begin(netClient, url)) return false;

    http.setReuse(true);
    http.setConnectTimeout(8000);
    http.setTimeout(POST_TIMEOUT);
    http.addHeader("Content-Type",  "application/json");
    http.addHeader("Accept",        "application/json");
    http.addHeader("Authorization", (String("Bearer ") + apiToken).c_str());
    http.addHeader("Connection",    "keep-alive");
    httpListo = true;
    return true;
}

static void cerrarConexion() {
    http.end();
    netClient.stop();
    httpListo = false;
}

static int postSincronizacion(const Snapshot& snap) {
    if (!asegurarConexion()) return -1;

    JsonDocument doc;
    doc["gabinete_id"] = gabineteId;
    JsonArray lecturas = doc["lecturas"].to<JsonArray>();
    for (uint8_t s = 0; s < NUM_SLOTS; s++) {
        JsonObject l = lecturas.add<JsonObject>();
        l["slot_index"] = s;
        if (snap.uid[s][0] != '\0') l["tag_uid"] = snap.uid[s];
        else                         l["tag_uid"] = nullptr;
    }

    String body;
    serializeJson(doc, body);
    int code = http.POST(body);
    if (code <= 0) cerrarConexion();

    Serial.printf("[red] POST sincronizacion → HTTP %d  (%u B)\n",
        code, body.length());
    return code;
}

void tareaRed(void* pv) {
    Snapshot snap;
    for (;;) {
        if (xQueueReceive(colaSnapshot, &snap, portMAX_DELAY) != pdTRUE) continue;

        if (WiFi.status() != WL_CONNECTED) {
            Serial.println("[red] WiFi caído — esperando reconexión automática");
            cerrarConexion();
            vTaskDelay(pdMS_TO_TICKS(5000));
            continue;
        }

        int code = postSincronizacion(snap);

        // Re-inicializar lectores tras el burst WiFi que puede bajar VCC
        if (xSemaphoreTake(spiMutex, pdMS_TO_TICKS(2000)) == pdTRUE) {
            initTodosLosLectores();
            xSemaphoreGive(spiMutex);
        }

        bool exito = (code >= 200 && code < 300);
        if (!exito) {
            uint32_t espera = (code <= 0 || code >= 500) ? RETRY_SLOW_MS : RETRY_FAST_MS;
            if (code >= 400 && code < 500) {
                // Error del cliente (payload inválido) — no reintentar; el sensor
                // producirá un snapshot fresco en el próximo barrido.
                Serial.printf("[red] error cliente %d — descartado\n", code);
            } else {
                Serial.printf("[red] fallo %d — reintento en %lu ms\n", code, espera);
                vTaskDelay(pdMS_TO_TICKS(espera));
                xQueueOverwrite(colaSnapshot, &snap);
            }
        }
    }
}

// ═════════════════════════════════════════════════════════════════════════
//  SETUP
// ═════════════════════════════════════════════════════════════════════════
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

    // Pines de dirección y enable del mux
    pinMode(PIN_S0, OUTPUT); digitalWrite(PIN_S0, LOW);
    pinMode(PIN_S1, OUTPUT); digitalWrite(PIN_S1, LOW);
    pinMode(PIN_S2, OUTPUT); digitalWrite(PIN_S2, LOW);
    for (uint8_t m = 0; m < NUM_MUXES; m++) {
        pinMode(MUX_EN[m], OUTPUT);
        digitalWrite(MUX_EN[m], HIGH);  // todos desactivados al arranque
    }

    SPI.begin(18, 19, 23);   // SCK=18, MISO=19, MOSI=23

    // Verificar e inicializar los 25 lectores
    Serial.println("Verificando RC522...");
    uint8_t errores = 0;
    for (uint8_t s = 0; s < NUM_SLOTS; s++) {
        uint8_t mux    = s / SENSORS_PER_MUX;
        uint8_t sensor = s % SENSORS_PER_MUX;
        seleccionarSensor(mux, sensor);
        rfid.PCD_Init();
        delay(50);
        byte ver = rfid.PCD_ReadRegister(MFRC522::VersionReg);
        bool ok  = (ver == 0x91 || ver == 0x92);
        if (ok) rfid.PCD_SetAntennaGain(MFRC522::RxGain_max);
        desactivarTodosMuxes();
        Serial.printf("  slot %02d (mux=%d sen=%d) ver=0x%02X  %s\n",
            s, mux, sensor, ver, ok ? "OK" : "SIN RESPUESTA — revisar cableado");
        if (!ok) errores++;
        delay(10);
    }
    if (errores > 0)
        Serial.printf("ADVERTENCIA: %d ranura(s) sin respuesta\n", errores);

    conectarEnterprise(ssid, identity, user, pass);
    WiFi.setAutoReconnect(true);

    colaSnapshot = xQueueCreate(1, sizeof(Snapshot));
    spiMutex     = xSemaphoreCreateMutex();

    xTaskCreatePinnedToCore(tareaSensado, "sensado", 4096, NULL, 2, NULL, 1);
    xTaskCreatePinnedToCore(tareaRed,     "red",     8192, NULL, 1, NULL, 0);

    Serial.printf("Listo — %d slots — gabinete %s\n", NUM_SLOTS, gabineteId);
    Serial.printf("API: %s\n", apiUrl);
}

void loop() {
    vTaskDelay(pdMS_TO_TICKS(1000));
}
