// provisioning.ino — cargar una vez, luego reemplazar por main.ino
#include <Preferences.h>

void setup() {
    Serial.begin(115200);
    Preferences p;
    p.begin("hub-digital", false);
    p.putString("wifi_ssid",   "alejo");
    p.putString("wifi_pass",   "29112003");
    p.putString("api_url",     "https://semisweet-nonfavorable-milena.ngrok-free.dev");
    p.putString("api_token",   "1|zO2J3uBW3pv5Nk75IpfqiczH7H6pVW4x0N3qPSR844e1c477");
    p.putString("gabinete_id", "f6b06935-0607-441a-a475-36adb25dbf1a");
    p.end();
    Serial.println("OK");
}
void loop() {}