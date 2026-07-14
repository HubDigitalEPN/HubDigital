// provisioning_25_slots.ino — Grabar configuración en NVS del ESP32
//
// Cargar UNA SOLA VEZ antes de flashear main_25_slots.ino.
// Ajustar los valores de las constantes y subir al ESP32.
// El monitor serial mostrará "OK" cuando termine.
//
// Claves NVS escritas:
//   wifi_ssid    → SSID de la red WiFi Enterprise
//   eap_identity → identidad EAP (usualmente usuario@dominio)
//   eap_user     → usuario EAP (sin dominio)
//   eap_pass     → contraseña EAP
//   api_url      → URL base del servidor (sin barra final, sin /api/...)
//   api_token    → token Sanctum con ability "esp32"
//   gabinete_id  → UUID del gabinete físico asignado a este ESP32

#include <Preferences.h>

// ── Ajustar antes de flashear ─────────────────────────────────────────────
const char* WIFI_SSID    = "EPN-LA100";
const char* EAP_IDENTITY = "usuario@epn.edu.ec";
const char* EAP_USER     = "usuario";
const char* EAP_PASS     = "contraseña";

// URL base del servidor Laravel (HTTP plano, sin barra final)
// Ejemplo red local:  "http://192.168.100.5"
// Ejemplo Herd:       "http://hubdigital.test"
const char* API_URL      = "http://192.168.100.5";

// Obtener con: php artisan tinker --execute 'echo \App\Models\User::first()->createToken("esp32", ["esp32"])->plainTextToken;'
const char* API_TOKEN    = "REEMPLAZAR_CON_TOKEN_SANCTUM";

// UUID del gabinete (debe existir en iot.gabinetes):
// php artisan tinker --execute 'echo \Modules\InventarioGestionColeccion\...\Gabinete::first()->id;'
const char* GABINETE_ID  = "REEMPLAZAR_CON_UUID_DEL_GABINETE";
// ─────────────────────────────────────────────────────────────────────────

void setup() {
    Serial.begin(115200);
    delay(500);

    Preferences p;
    p.begin("hub-digital", false);   // false = lectura/escritura
    p.putString("wifi_ssid",    WIFI_SSID);
    p.putString("eap_identity", EAP_IDENTITY);
    p.putString("eap_user",     EAP_USER);
    p.putString("eap_pass",     EAP_PASS);
    p.putString("api_url",      API_URL);
    p.putString("api_token",    API_TOKEN);
    p.putString("gabinete_id",  GABINETE_ID);
    p.end();

    // Verificar lectura
    p.begin("hub-digital", true);    // true = solo lectura
    Serial.println("=== Configuración grabada ===");
    Serial.printf("wifi_ssid    : %s\n", p.getString("wifi_ssid",    "").c_str());
    Serial.printf("eap_identity : %s\n", p.getString("eap_identity", "").c_str());
    Serial.printf("eap_user     : %s\n", p.getString("eap_user",     "").c_str());
    Serial.printf("eap_pass     : %s\n", p.getString("eap_pass",     "").c_str());
    Serial.printf("api_url      : %s\n", p.getString("api_url",      "").c_str());
    Serial.printf("api_token    : %s\n", p.getString("api_token",    "").c_str());
    Serial.printf("gabinete_id  : %s\n", p.getString("gabinete_id",  "").c_str());
    p.end();

    Serial.println("OK — Flashear main_25_slots.ino");
}

void loop() {}
