// main.ino — Seguimiento Físico v2.0 (3 slots, concurrente)
// Arquitectura de dos tareas FreeRTOS + cola:
//   - Tarea SENSADO (core 1): dueña del bus SPI. Barre los lectores sin
//     bloquearse nunca por la red. Cuando detecta un cambio, encola un evento.
//   - Tarea RED (core 0): drena la cola y hace los POST a su ritmo. Si un POST
//     resetea los lectores (dip de 3.3V por WiFi), pide el mutex SPI para
//     reinicializarlos sin chocar con el barrido.
//
// Escala a N lectores sin reestructurar: solo cambia NUM_SLOTS y CS_PINS.
//
// Pines CS validados en hardware: 15, 4, 5 (orden fisico arriba->abajo).
// El sensor 0 va en GPIO 15. NO usar GPIO 0 ni 2 (strapping pins).

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// ── Pines ────────────────────────────────────────────────
constexpr uint8_t PIN_RST   = 22;          // compartido por los lectores
constexpr uint8_t CS_PINS[] = {15, 4, 5};  // un CS por lector (arriba, medio, abajo)
constexpr uint8_t NUM_SLOTS = 3;

// ── Estado de lectores (solo lo toca la tarea de sensado) ─
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
struct Evento {
    char uid[9];
    uint8_t slot;
    bool ingreso;   // true = ingreso, false = retiro
};
QueueHandle_t   colaEventos;     // sensado -> red
SemaphoreHandle_t spiMutex;      // protege el bus SPI durante el reinit

// La cola buffferiza eventos: si la red va lenta, el sensado no se traba ni
// pierde cambios mientras quepan en la cola.
constexpr uint8_t COLA_TAM = 32;

// ── Parametros de tiempo ──────────────────────────────────
constexpr uint32_t SWEEP_INTERVAL_MS = 250;    // barrido agil; el HTTP ya no lo frena
constexpr uint8_t  MISSES_RETIRO     = 3;      // lecturas vacias para confirmar retiro

// Espaciado minimo entre POST consecutivos: deja que el stack TLS/WiFi se
// asiente y el socket anterior se libere. Evita los -1 por POST pegados.
constexpr uint32_t POST_GAP_MS       = 300;
// Reintento RAPIDO para errores transitorios de conexion (-1, timeouts):
// suelen resolverse al instante.
constexpr uint32_t RETRY_FAST_MS     = 1500;
// Backoff LARGO solo para errores reales del servidor (5xx): no tiene sentido
// martillar un backend caido.
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

// ═══════════════════════════════════════════════════════════
//  TAREA SENSADO  (core 1) — dueña exclusiva del SPI en el barrido
// ═══════════════════════════════════════════════════════════
void tareaSensado(void* pv) {
    for (;;) {
        for (uint8_t i = 0; i < NUM_SLOTS; i++) {
            MFRC522& r = readers[i];
            char uid[9] = {};

            // Toma el mutex por lectura: garantiza que el reinit de la red
            // nunca parta una transaccion SPI a la mitad.
            if (xSemaphoreTake(spiMutex, pdMS_TO_TICKS(500)) != pdTRUE) continue;
            byte atqa[2]; byte sz = sizeof(atqa);
            r.PICC_WakeupA(atqa, &sz);          // despierta tags en HALT
            bool presente = r.PICC_ReadCardSerial();
            if (presente) {
                formatUid(r, uid);
                r.PICC_HaltA();
                r.PCD_StopCrypto1();
            }
            xSemaphoreGive(spiMutex);

            // Debounce de retiro: varias lecturas vacias antes de confirmar.
            if (!presente && slots[i].ocupado) {
                if (++slots[i].misses < MISSES_RETIRO) continue;
            } else {
                slots[i].misses = 0;
            }

            if (presente == slots[i].ocupado) continue;  // sin cambio

            // Hubo cambio de estado -> arma evento y encola.
            Evento ev;
            ev.slot    = i;
            ev.ingreso = presente;
            strncpy(ev.uid, presente ? uid : slots[i].uid, 9);

            // Actualiza estado local de inmediato; la red lo confirmara aparte.
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
//  TAREA RED  (core 0) — drena la cola y hace los POST
// ═══════════════════════════════════════════════════════════
// Cliente HTTP reutilizado como objeto, pero cada POST abre/cierra su propio
// socket (Connection: close). Vive en la tarea de red.
HTTPClient http;

// Devuelve el codigo HTTP crudo: >0 es respuesta del servidor, <=0 es error de
// conexion (p.ej. -1 = conexion rechazada/cortada).
int postEvento(const Evento& ev) {
    char url[200];
    snprintf(url, sizeof(url), "%s/api/v1/seguimiento-fisico/eventos", apiUrl);

    // Socket nuevo y limpio por POST: evita heredar un socket medio-cerrado de
    // la peticion anterior, que es lo que disparaba el -1 intermitente.
    WiFiClientSecure client;
    client.setInsecure();                // ngrok: no validamos cert
    client.setTimeout(15000);

    if (!http.begin(client, url)) {
        Serial.println("[red] http.begin fallo");
        return -1;
    }
    http.addHeader("Content-Type",               "application/json");
    http.addHeader("Accept",                     "application/json");
    http.addHeader("Authorization",              (String("Bearer ") + apiToken).c_str());
    http.addHeader("ngrok-skip-browser-warning", "1");
    http.addHeader("Connection",                 "close");   // sin keep-alive sobre ngrok
    http.setReuse(false);                                     // coherente con begin/end por POST
    http.setTimeout(15000);

    JsonDocument doc;
    doc["tag_uid"]     = ev.uid;
    doc["gabinete_id"] = gabineteId;
    doc["slot_index"]  = ev.slot;
    doc["evento"]      = ev.ingreso ? "ingreso" : "retiro";
    String body; serializeJson(doc, body);

    int code = http.POST(body);
    http.end();
    Serial.printf("[red] HTTP %d  slot=%d uid=%s\n", code, ev.slot, ev.uid);
    return code;
}

void tareaRed(void* pv) {
    Evento ev;
    for (;;) {
        // Espera bloqueante hasta que haya un evento (no quema CPU).
        if (xQueueReceive(colaEventos, &ev, portMAX_DELAY) != pdTRUE) continue;

        int code = postEvento(ev);

        // El TX de WiFi pudo causar un dip de 3.3V que reseteo los lectores.
        // Reinicializa bajo mutex para no chocar con el barrido del sensado.
        if (xSemaphoreTake(spiMutex, pdMS_TO_TICKS(2000)) == pdTRUE) {
            for (uint8_t j = 0; j < NUM_SLOTS; j++) initLector(j);
            xSemaphoreGive(spiMutex);
        }

        // Clasifica el resultado:
        //   200 / 422  -> exito (422 = dominio rechazo, no reintentar)
        //   <= 0       -> error de conexion (-1, timeout): reintento RAPIDO
        //   >= 500     -> servidor caido: backoff LARGO
        //   otros 4xx  -> error de peticion, no reintentar (evita loop infinito)
        bool exito = (code == 200 || code == 422);

        if (!exito) {
            uint32_t espera;
            if (code <= 0) {
                espera = RETRY_FAST_MS;       // transitorio, reintenta pronto
                Serial.printf("[red] error conexion (%d) slot %d, reintento en %lu ms\n",
                    code, ev.slot, espera);
            } else if (code >= 500) {
                espera = RETRY_BACKOFF_MS;     // backend caido, espera largo
                Serial.printf("[red] servidor %d slot %d, reintento en %lu s\n",
                    code, ev.slot, espera / 1000);
            } else {
                // 4xx distinto de 422: la peticion esta mal, reintentar no ayuda.
                Serial.printf("[red] peticion rechazada %d slot %d, descartado\n",
                    code, ev.slot);
                espera = 0;
            }
            if (espera > 0) {
                vTaskDelay(pdMS_TO_TICKS(espera));
                xQueueSend(colaEventos, &ev, 0);
            }
        }

        // Espaciado minimo entre POST: evita encadenar dos peticiones pegadas,
        // que es lo que disparaba los -1. Da aire al stack TLS/WiFi.
        vTaskDelay(pdMS_TO_TICKS(POST_GAP_MS));
    }
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

    // CS en HIGH antes de abrir el bus — evita contencion SPI en arranque.
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

    // Crea cola y mutex antes de lanzar las tareas.
    colaEventos = xQueueCreate(COLA_TAM, sizeof(Evento));
    spiMutex    = xSemaphoreCreateMutex();

    // Sensado en core 1, red en core 0 (donde corre el stack WiFi por defecto).
    xTaskCreatePinnedToCore(tareaSensado, "sensado", 4096, NULL, 2, NULL, 1);
    xTaskCreatePinnedToCore(tareaRed,     "red",     8192, NULL, 1, NULL, 0);

    Serial.printf("Listo — %d slots — gabinete %s\n", NUM_SLOTS, gabineteId);
    Serial.printf("API: %s\n", apiUrl);
}

// El trabajo vive en las tareas; loop() queda vacio.
void loop() {
    vTaskDelay(pdMS_TO_TICKS(1000));
}
