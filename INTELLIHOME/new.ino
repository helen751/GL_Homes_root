/*
========================================================
ADVANCED HOME AUTOMATION IoT - NODEMCU ESP8266
NOW SENDING DIRECTLY TO ONLINE PHP SERVER
========================================================
*/

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecure.h>
#include <DHT.h>

// ==================== WIFI + SERVER CONFIG ====================
const char* ssid = "lguard";
const char* password = "12345678";

String apiUrl = "https://glhomesltd.com/INTELLIHOME/api/insert_data.php";
String apiKey = "HA_IOT_SECRET_KEY_2026_CHANGE_THIS";

// ==================== PIN DEFINITIONS ====================
#define MQ2_DIGITAL_PIN D0
#define ULTRASONIC_TRIG D1
#define ULTRASONIC_ECHO D2
#define DHT_PIN D3

#define RELAY_BULB1 D5
#define RELAY_BULB2 D6
#define BUZZER_PIN D7

#define LDR_PIN A0

// ==================== SENSOR SETUP ====================
#define DHT_TYPE DHT11
DHT dht(DHT_PIN, DHT_TYPE);

// ==================== CONFIGURATION ====================
const unsigned long SENSOR_READ_INTERVAL   = 2000;
const unsigned long COMMAND_CHECK_INTERVAL = 500;
const unsigned long EMERGENCY_CLEAR_TIME   = 20000;

const int ULTRASONIC_SAMPLES = 5;
const int SMOKE_DEBOUNCE_CHECKS = 5;
const int SMOKE_DEBOUNCE_DELAY_MS = 5;

const int MOTION_DISTANCE_CM = 50;
const unsigned long MOTION_HOLD_MS = 1000;

const int LDR_DARK_THRESHOLD = 400;

const unsigned long SMOKE_SAMPLE_DURATION = 5000;
const unsigned long SMOKE_ALERT_DURATION  = 5000;

// ==================== GLOBAL STATE ====================
struct SensorData {
  float temperature;
  float humidity;
  long distance;
  int smokeStatus;
  int lightLevel;
};

struct DeviceState {
  bool bulb1;
  bool bulb2;
  bool buzzer;
  bool emergency;
  bool autoMotion;
};

SensorData sensors = {0, 0, 0, 0, 0};
DeviceState devices = {false, false, false, false, true};

// ==================== TIMERS ====================
unsigned long lastSensorRead = 0;
unsigned long lastCommandCheck = 0;
unsigned long motionStartTime = 0;
unsigned long clearStartTime = 0;

bool motionActive = false;
String serialBuffer = "";

enum SmokeCycleState {
  SMOKE_IDLE,
  SMOKE_ALERT
};

SmokeCycleState smokeCycleState = SMOKE_IDLE;
unsigned long smokeCycleStartTime = 0;
unsigned long smokeDetectedTime = 0;

// ==================== FUNCTION DECLARATIONS ====================
void connectToWiFi();
void readAllSensors();
long getFilteredUltrasonic();
bool debounceSmokeSensor();

void autoControlLogic(unsigned long now);
void enterEmergencyMode();
void handleEmergencyMode(unsigned long now);
void exitEmergencyMode();

void setBulb1(bool on);
void setBulb2(bool on);
void setBuzzer(bool on);

void sendJsonData();
void printReadableData();
void checkSerialCommands();
void processCommand(String cmdStr);
String extractJsonValue(String json, String key);

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("======================================");
  Serial.println("   INDUSTRIAL HOME AUTOMATION READY   ");
  Serial.println("       ONLINE SERVER MODE ACTIVE      ");
  Serial.println("    SMOKE: 5sec SAMPLE -> 5sec ALERT  ");
  Serial.println("======================================");

  pinMode(MQ2_DIGITAL_PIN, INPUT);
  pinMode(ULTRASONIC_TRIG, OUTPUT);
  pinMode(ULTRASONIC_ECHO, INPUT);

  pinMode(RELAY_BULB1, OUTPUT);
  pinMode(RELAY_BULB2, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);

  digitalWrite(RELAY_BULB1, HIGH);
  digitalWrite(RELAY_BULB2, HIGH);
  digitalWrite(BUZZER_PIN, LOW);

  dht.begin();

  connectToWiFi();

  Serial.println("System Core Initialized cleanly.");
  Serial.println();

  smokeCycleState = SMOKE_IDLE;
  smokeCycleStartTime = millis();
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long now = millis();

  if (WiFi.status() != WL_CONNECTED) {
    connectToWiFi();
  }

  if (devices.emergency) {
    handleEmergencyMode(now);
  }

  if (now - lastSensorRead >= SENSOR_READ_INTERVAL) {
    readAllSensors();
    lastSensorRead = now;

    if (!devices.emergency) {
      if (sensors.smokeStatus == HIGH && debounceSmokeSensor()) {
        if (smokeCycleState == SMOKE_IDLE) {
          if (smokeDetectedTime == 0) {
            smokeDetectedTime = now;
            Serial.println("[SMOKE] Smoke detected! Starting 5 second sampling window...");
          }

          if (now - smokeDetectedTime >= SMOKE_SAMPLE_DURATION) {
            enterEmergencyMode();
            smokeCycleState = SMOKE_ALERT;
            smokeDetectedTime = 0;
          }
        }
      } else {
        if (smokeDetectedTime != 0 && smokeCycleState == SMOKE_IDLE) {
          smokeDetectedTime = 0;
          Serial.println("[SMOKE] Smoke cleared. Reset detection counter.");
        }
      }
    }

    if (!devices.emergency) {
      autoControlLogic(now);
    }

    printReadableData();
    sendJsonData();
  }

  if (now - lastCommandCheck >= COMMAND_CHECK_INTERVAL) {
    checkSerialCommands();
    lastCommandCheck = now;
  }

  delay(10);
}

// ==================== WIFI CONNECTION ====================
void connectToWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);

  int retries = 0;

  while (WiFi.status() != WL_CONNECTED && retries < 30) {
    delay(500);
    Serial.print(".");
    retries++;
    yield();
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("WiFi connection failed. Will retry...");
  }
}

// ==================== SENSOR READING ====================
void readAllSensors() {
  sensors.temperature = dht.readTemperature();
  sensors.humidity = dht.readHumidity();

  if (isnan(sensors.temperature)) sensors.temperature = 0;
  if (isnan(sensors.humidity)) sensors.humidity = 0;

  sensors.distance = getFilteredUltrasonic();
  sensors.smokeStatus = digitalRead(MQ2_DIGITAL_PIN);
  sensors.lightLevel = analogRead(LDR_PIN);
}

long getFilteredUltrasonic() {
  long totalDistance = 0;
  int validSamplesCount = 0;

  for (int i = 0; i < ULTRASONIC_SAMPLES; i++) {
    digitalWrite(ULTRASONIC_TRIG, LOW);
    delayMicroseconds(2);
    digitalWrite(ULTRASONIC_TRIG, HIGH);
    delayMicroseconds(10);
    digitalWrite(ULTRASONIC_TRIG, LOW);

    long duration = pulseIn(ULTRASONIC_ECHO, HIGH, 25000);
    long distanceSample = duration * 0.034 / 2;

    if (distanceSample > 0 && distanceSample < 400) {
      totalDistance += distanceSample;
      validSamplesCount++;
    }

    delay(10);
    yield();
  }

  if (validSamplesCount == 0) {
    return 999;
  }

  return totalDistance / validSamplesCount;
}

bool debounceSmokeSensor() {
  for (int i = 0; i < SMOKE_DEBOUNCE_CHECKS; i++) {
    delay(SMOKE_DEBOUNCE_DELAY_MS);
    if (digitalRead(MQ2_DIGITAL_PIN) == LOW) {
      return false;
    }
  }
  return true;
}

// ==================== AUTO CONTROL ====================
void autoControlLogic(unsigned long now) {
  if (sensors.lightLevel > LDR_DARK_THRESHOLD) {
    if (!devices.bulb1) {
      setBulb1(true);
      Serial.println("[SYSTEM-EXEC]: Darkness Detected -> Bulb1 Activated");
    }
  } else {
    if (devices.bulb1) {
      setBulb1(false);
      Serial.println("[SYSTEM-EXEC]: Ambient Light Sufficient -> Bulb1 Deactivated");
    }
  }

  if (devices.autoMotion) {
    if (sensors.distance > 0 && sensors.distance < MOTION_DISTANCE_CM) {
      if (!motionActive) {
        motionActive = true;
        motionStartTime = now;
        setBulb2(true);
        Serial.println("[SYSTEM-EXEC]: Motion Confirmed (<50cm) -> Bulb2 Activated");
      } else {
        motionStartTime = now;
      }
    }

    if (motionActive && (now - motionStartTime >= MOTION_HOLD_MS)) {
      motionActive = false;
      setBulb2(false);
      Serial.println("[SYSTEM-EXEC]: Distance Clear Threshold Reached -> Bulb2 Deactivated");
    }
  }
}

// ==================== SAFETY ====================
void enterEmergencyMode() {
  devices.emergency = true;
  clearStartTime = 0;

  setBulb1(false);
  setBulb2(false);
  setBuzzer(true);

  Serial.println("\n====================================");
  Serial.println("!! CRITICAL HARDWARE ALARM TRIGGERED !!");
  Serial.println("     SMOKE CONFIRMED FOR 5 SECONDS    ");
  Serial.println("        FORCED EM-FAILSAFE ACTIVATED  ");
  Serial.println("======================================");
}

void handleEmergencyMode(unsigned long now) {
  setBulb1(false);
  setBulb2(false);

  if (clearStartTime == 0) {
    clearStartTime = now;
    Serial.println("[Failsafe]: Alert active. Will auto-exit after 5 seconds...");
  } else if (now - clearStartTime >= SMOKE_ALERT_DURATION) {
    exitEmergencyMode();
  }
}

void exitEmergencyMode() {
  devices.emergency = false;
  smokeCycleState = SMOKE_IDLE;
  smokeDetectedTime = 0;
  clearStartTime = 0;
  setBuzzer(false);

  Serial.println("\n[Failsafe]: 5 second alert completed. System operational again.");
  Serial.println("[Failsafe]: Starting new 5 second sampling cycle.\n");
}

// ==================== HARDWARE WRITERS ====================
void setBulb1(bool on) {
  devices.bulb1 = on;
  digitalWrite(RELAY_BULB1, on ? LOW : HIGH);
}

void setBulb2(bool on) {
  devices.bulb2 = on;
  digitalWrite(RELAY_BULB2, on ? LOW : HIGH);
}

void setBuzzer(bool on) {
  devices.buzzer = on;
  digitalWrite(BUZZER_PIN, on ? HIGH : LOW);
}

// ==================== LOGGING ====================
void printReadableData() {
  Serial.println("================ METRICS ================");
  Serial.print("Temp/Humid:   ");
  Serial.print(sensors.temperature, 1);
  Serial.print(" C / ");
  Serial.print(sensors.humidity, 1);
  Serial.println(" %");

  Serial.print("Ultrasonic:   ");
  Serial.print(sensors.distance);
  Serial.println(" cm");

  Serial.print("LDR Value:    ");
  Serial.print(sensors.lightLevel);
  Serial.println(sensors.lightLevel > LDR_DARK_THRESHOLD ? " (DARK ENVIRONMENT)" : " (BRIGHT ENVIRONMENT)");

  Serial.print("Smoke Status: ");
  Serial.println(sensors.smokeStatus == HIGH ? "DANGER SIGNAL" : "STABLE");

  if (smokeDetectedTime > 0 && !devices.emergency) {
    unsigned long detectingFor = (millis() - smokeDetectedTime) / 1000;
    Serial.print("Smoke Timer:  Detecting for ");
    Serial.print(detectingFor);
    Serial.println(" seconds (need 5 to alert)");
  } else if (devices.emergency) {
    unsigned long alertRemaining = (SMOKE_ALERT_DURATION - (millis() - clearStartTime)) / 1000;
    Serial.print("Alert Timer:  ");
    Serial.print(alertRemaining);
    Serial.println(" seconds remaining");
  } else {
    Serial.println("Smoke Timer:  IDLE (no detection)");
  }

  Serial.print("Bulb 1 Stat:  ");
  Serial.println(devices.bulb1 ? "ON" : "OFF");

  Serial.print("Bulb 2 Stat:  ");
  Serial.println(devices.bulb2 ? "ON" : "OFF");

  Serial.print("Emergency:    ");
  Serial.println(devices.emergency ? "CRITICAL LOCK" : "IDLE");

  Serial.println("========================================\n");
}

// ==================== SEND ONLINE JSON DATA ====================
void sendJsonData() {
  String json = "{";
  json += "\"temperature\":" + String(sensors.temperature, 1) + ",";
  json += "\"humidity\":" + String(sensors.humidity, 1) + ",";
  json += "\"distance\":" + String(sensors.distance) + ",";
  json += "\"smoke_status\":" + String(sensors.smokeStatus) + ",";
  json += "\"light_level\":" + String(sensors.lightLevel) + ",";
  json += "\"bulb1_status\":" + String(devices.bulb1 ? 1 : 0) + ",";
  json += "\"bulb2_status\":" + String(devices.bulb2 ? 1 : 0) + ",";
  json += "\"buzzer_status\":" + String(devices.buzzer ? 1 : 0) + ",";
  json += "\"emergency_flag\":" + String(devices.emergency ? 1 : 0) + ",";
  json += "\"device_id\":\"home_unit_01\"";
  json += "}";

  Serial.println("[ONLINE] Sending JSON to server...");
  Serial.println(json);

  if (WiFi.status() == WL_CONNECTED) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;

    http.begin(client, apiUrl);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-Key", apiKey);

    int httpCode = http.POST(json);

    Serial.print("[ONLINE] HTTP Code: ");
    Serial.println(httpCode);

    String response = http.getString();
    Serial.print("[ONLINE] Server Response: ");
    Serial.println(response);

    http.end();
  } else {
    Serial.println("[ONLINE] WiFi not connected. Data not sent.");
  }
}

// ==================== SERIAL COMMAND PROCESSING ====================
void checkSerialCommands() {
  while (Serial.available()) {
    char c = Serial.read();

    if (c == '\n') {
      processCommand(serialBuffer);
      serialBuffer = "";
    } else if (c != '\r') {
      serialBuffer += c;
    }
  }
}

void processCommand(String cmdStr) {
  cmdStr.trim();

  if (cmdStr.length() == 0) return;

  String type = extractJsonValue(cmdStr, "command_type");
  String value = extractJsonValue(cmdStr, "command_value");

  if (type.length() == 0) return;

  bool isOn = (value == "ON" || value == "1" || value == "true");

  if (type == "bulb1") {
    setBulb1(isOn);
  } else if (type == "bulb2") {
    setBulb2(isOn);
  } else if (type == "buzzer") {
    setBuzzer(isOn);
  } else if (type == "acknowledge") {
    setBuzzer(false);
    Serial.println("[CMD-ACK]: Remote Operator overrides Buzzer state.");
  }
}

String extractJsonValue(String json, String key) {
  String search = "\"" + key + "\"";
  int start = json.indexOf(search);

  if (start == -1) return "";

  int colon = json.indexOf(':', start);

  if (colon == -1) return "";

  int quote1 = json.indexOf('"', colon + 1);

  if (quote1 != -1) {
    int quote2 = json.indexOf('"', quote1 + 1);

    if (quote2 != -1) {
      return json.substring(quote1 + 1, quote2);
    }
  }

  return "";
}