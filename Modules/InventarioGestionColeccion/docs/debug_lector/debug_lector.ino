// debug_lector.ino
// Apunta a UN solo lector para diagnosticar por qué no lee tags.
// Cambia DEBUG_SLOT al índice del lector problemático (0, 1 o 2).
// Reporta: versión del chip, nivel de ganancia, intentos WUPA, UID detectado.

#include <SPI.h>
#include <MFRC522.h>

// ── Configura aquí ────────────────────────────────────────
#define DEBUG_SLOT   1          // lector a diagnosticar
#define CS_PIN       4          // pin CS de ese lector
#define RST_PIN      22
// ──────────────────────────────────────────────────────────

MFRC522 r(CS_PIN, RST_PIN);

void setup() {
    Serial.begin(115200);
    delay(500);

    pinMode(CS_PIN, OUTPUT);
    digitalWrite(CS_PIN, HIGH);
    SPI.begin(18, 19, 23);

    Serial.println("\n===== DEBUG LECTOR RC522 =====");
    Serial.printf("CS=GPIO%d  RST=GPIO%d\n", CS_PIN, RST_PIN);

    // Intento 1: init normal
    r.PCD_Init();
    delay(50);
    byte ver = r.PCD_ReadRegister(MFRC522::VersionReg);
    Serial.printf("Version tras PCD_Init():  0x%02X", ver);
    Serial.println(versionOk(ver) ? "  ✓ OK" : "  ✗ REVISAR CABLEADO");

    // Intento 2: reset duro + reinit
    r.PCD_Reset();
    delay(100);
    r.PCD_Init();
    delay(50);
    ver = r.PCD_ReadRegister(MFRC522::VersionReg);
    Serial.printf("Version tras Reset+Init:  0x%02X", ver);
    Serial.println(versionOk(ver) ? "  ✓ OK" : "  ✗ REVISAR CABLEADO");

    // Registros internos clave
    byte txCtrl = r.PCD_ReadRegister(MFRC522::TxControlReg);
    Serial.printf("TxControlReg:  0x%02X", txCtrl);
    // Bits 0 y 1 activan TX1 y TX2 (las antenas). Deben ser 1.
    Serial.println((txCtrl & 0x03) == 0x03 ? "  ✓ Antenas TX1+TX2 activas" : "  ✗ Antenas APAGADAS");

    // Fuerza ganancia máxima y verifica
    r.PCD_SetAntennaGain(MFRC522::RxGain_max);
    byte gain = r.PCD_ReadRegister(MFRC522::RFCfgReg);
    Serial.printf("RFCfgReg (ganancia):  0x%02X  (esperado 0x70 = max)\n", gain);

    Serial.println("\n----- Acerca una tarjeta/tag -----");
    Serial.println("Mostrando intentos cada 500 ms...\n");
}

void loop() {
    static uint32_t ciclo = 0;
    ciclo++;

    // Intento con WUPA (detecta tags en HALT también)
    byte atqa[2];
    byte atqaSize = sizeof(atqa);
    MFRC522::StatusCode wupa = r.PICC_WakeupA(atqa, &atqaSize);

    bool presente = r.PICC_ReadCardSerial();

    Serial.printf("[%4lu] WUPA=%s  ReadCardSerial=%s",
        ciclo,
        statusStr(wupa),
        presente ? "OK" : "--");

    if (presente) {
        Serial.print("  UID: ");
        for (byte i = 0; i < r.uid.size; i++) {
            if (r.uid.uidByte[i] < 0x10) Serial.print("0");
            Serial.print(r.uid.uidByte[i], HEX);
        }
        Serial.print("  SAK: 0x");
        Serial.print(r.uid.sak, HEX);
        r.PICC_HaltA();
        r.PCD_StopCrypto1();
    }

    // Intento alternativo con REQA por si WUPA falla
    MFRC522::StatusCode reqa = (MFRC522::StatusCode)0xFF;
    if (!presente) {
        byte buf[2]; byte sz = sizeof(buf);
        reqa = r.PICC_RequestA(buf, &sz);
        Serial.printf("  REQA=%s", statusStr(reqa));
    }

    // Nivel de señal (registro de error — si hay overflow, la señal es fuerte)
    byte errReg = r.PCD_ReadRegister(MFRC522::ErrorReg);
    Serial.printf("  ErrReg=0x%02X", errReg);

    Serial.println();
    delay(500);
}

bool versionOk(byte v) {
    return v == 0x91 || v == 0x92 || v == 0x88 || v == 0x82;
}

const char* statusStr(MFRC522::StatusCode s) {
    switch (s) {
        case MFRC522::STATUS_OK:          return "OK     ";
        case MFRC522::STATUS_TIMEOUT:     return "TIMEOUT";
        case MFRC522::STATUS_NO_ROOM:     return "NO_ROOM";
        case MFRC522::STATUS_ERROR:       return "ERROR  ";
        case MFRC522::STATUS_COLLISION:   return "COLLIDE";
        case MFRC522::STATUS_CRC_WRONG:   return "CRC_ERR";
        default:                          return "OTHER  ";
    }
}
