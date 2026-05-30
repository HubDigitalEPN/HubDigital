// provisioning.ino — cargar una vez, luego reemplazar por main.ino
#include <Preferences.h>

void setup() {
    Serial.begin(115200);
    Preferences p;
    p.begin("hub-digital", false);
    p.putString("wifi_ssid",   "CLARO_MICHAEL");
    p.putString("wifi_pass",   "0401064001");
    p.putString("api_url",     "https://semisweet-nonfavorable-milena.ngrok-free.dev");
    p.putString("api_token",   "2|PfS7OOGuIk5qb4UMwaLtwvtkXgWzlg51S4agpLY4adecdd71");
    p.putString("gabinete_id", "46ce7395-6ea1-4762-ac62-5e4c58cbb23b");
    p.end();
    Serial.println("OK");
}
void loop() {}