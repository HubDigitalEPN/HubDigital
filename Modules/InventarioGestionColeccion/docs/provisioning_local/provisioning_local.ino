// provisioning.ino — cargar una vez, luego reemplazar por main.ino
#include <Preferences.h>

void setup() {
    Serial.begin(115200);
    Preferences p;
    p.begin("hub-digital", false);
    p.putString("wifi_ssid",   "CELERITY_EMA0205");
    p.putString("wifi_pass",   "Ema2022.");
    p.putString("api_url",     "https://semisweet-nonfavorable-milena.ngrok-free.dev");
    p.putString("api_token",   "1|A4hLhAXRfQHgjA2TzPes4eZANu2BeBOozCnxyMIT184ecaa3");
    p.putString("gabinete_id", "f6b06935-0607-441a-a475-36adb25dbf1a");
    p.end();
    Serial.println("OK");
}
void loop() {}