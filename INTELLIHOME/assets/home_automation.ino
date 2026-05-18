/*
  ================================================================
  HOME AUTOMATION IoT - ARDUINO + ESP8266 WiFi (Standalone)
  ================================================================
  No PC required! Arduino sends data directly to your hosted server.

  Hardware:
    - Arduino Uno/Nano
    - ESP8266 WiFi Module (ESP-01 or NodeMCU as WiFi shield)
    - HC-SR04, LDR, DHT11, MQ-2, Relay Module, Buzzer

  Wiring ESP8266 to Arduino:
    ESP8266 VCC  -> 3.3V (NOT 5V!)
    ESP8266 GND  -> GND
    ESP8266 TX   -> Arduino D10 (via voltage divider 3.3V)
    ESP8266 RX   -> Arduino D11 (via voltage divider 3.3V)
    ESP8266 CH_PD -> 3.3V

  NOTE: ESP8266 uses 3.3V logic. Use a voltage divider on TX/RX
        or use a logic level converter to avoid damaging the ESP8266.

  Alternative: Use NodeMCU (ESP8266 board) instead of Arduino + ESP-01
  ================================================================
*/

#include <DHT.h>
#include <SoftwareSerial.h>

// ==================== WiFi CONFIGURATION ====================
// EDIT THESE WITH YOUR WiFi DETAILS
const char* WIFI_SSID     = "YOUR_WIFI_NAME";        // <-- CHANGE THIS
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";    // <-- CHANGE THIS

// Server Configuration
const char* SERVER_HOST   = "your-domain.com";       // <-- CHANGE THIS (no http://)
const int   SERVER_PORT   = 80;                      // 80 for HTTP, 443 for HTTPS
const char* API_KEY       = "HA_IOT_SECRET_KEY_2026_CHANGE_THIS"; // <-- CHANGE THIS
const char* DEVICE_ID     = "home_unit_01";

// ==================== PIN DEFINITIONS ====================
#define ULTRASONIC_TRIG     2
#define ULTRASONIC_ECHO     3
#define DHT_PIN             4
#define RELAY_BULB1         5
#define RELAY_BULB2         6
#define BUZZER_PIN          7
#define ESP_RX              10    // ESP8266 TX -> Arduino RX
#define ESP_TX              11    // ESP8266 RX -> Arduino TX
#define LDR_PIN             A0
#define MQ2_PIN             A1

// ==================== SENSOR SETUP ====================
#define DHT_TYPE            DHT11
DHT dht(DHT_PIN, DHT_TYPE);

SoftwareSerial esp8266(ESP_RX, ESP_TX);

// ==================== CONFIGURATION ====================
const unsigned long SENSOR_READ_INTERVAL    = 5000;
const unsigned long COMMAND_CHECK_INTERVAL  = 500;
const unsigned long EMERGENCY_CLEAR_TIME    = 30000;
const unsigned long WIFI_RETRY_INTERVAL     = 10000;

const int SMOKE_DANGER_THRESHOLD  = 400;
const int SMOKE_SAFE_THRESHOLD    = 200;
const int LIGHT_DARK_THRESHOLD    = 300;
const int LIGHT_BRIGHT_THRESHOLD  = 700;
const int MOTION_DISTANCE_CM      = 50;
const unsigned long MOTION_HOLD_MS = 10000;

// ==================== GLOBAL STATE ====================
struct SensorData {
  float temperature;
  float humidity;
  long  distance;
  int   lightLevel;
  int   smokeLevel;
};

struct DeviceState {
  bool bulb1;
  bool bulb2;
  bool buzzer;
  bool emergency;
  bool autoLight;
  bool autoMotion;
};

SensorData sensors = {0, 0, 0, 0, 0};
DeviceState devices = {false, false, false, false, true, true};

unsigned long lastSensorRead   = 0;
unsigned long lastCommandCheck = 0;
unsigned long motionStartTime  = 0;
unsigned long clearStartTime   = 0;
unsigned long lastWifiCheck    = 0;
bool motionActive              = false;
bool wifiConnected             = false;

String serialBuffer = "";
String espBuffer    = "";

// ==================== SETUP ====================
void setup() {
  Serial.begin(9600);
  esp8266.begin(9600);

  pinMode(ULTRASONIC_TRIG, OUTPUT);
  pinMode(ULTRASONIC_ECHO, INPUT);
  pinMode(RELAY_BULB1, OUTPUT);
  pinMode(RELAY_BULB2, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);

  digitalWrite(RELAY_BULB1, HIGH);
  digitalWrite(RELAY_BULB2, HIGH);
  digitalWrite(BUZZER_PIN, LOW);

  dht.begin();

  Serial.println(F("\n========================================"));
  Serial.println(F("  HOME AUTOMATION IoT - WiFi Standalone"));
  Serial.println(F("========================================"));

  delay(2000); // Let ESP8266 boot

  // Initialize WiFi
  if (initWiFi()) {
    Serial.println(F("WiFi READY - System operational"));
  } else {
    Serial.println(F("WiFi FAILED - Running in local mode only"));
  }
}

// ==================== WiFi FUNCTIONS ====================
bool initWiFi() {
  Serial.print(F("Connecting to WiFi: "));
  Serial.println(WIFI_SSID);

  // Send AT commands to ESP8266 to connect
  sendCommand("AT+RST", 2000);           // Reset module
  sendCommand("AT+CWMODE=1", 1000);      // Set station mode

  // Connect to WiFi
  String cmd = "AT+CWJAP=\"";
  cmd += WIFI_SSID;
  cmd += "\",\"";
  cmd += WIFI_PASSWORD;
  cmd += "\"";

  if (sendCommand(cmd, 10000)) {
    if (sendCommand("AT+CIFSR", 1000)) {  // Get IP address
      wifiConnected = true;
      return true;
    }
  }
  return false;
}

bool checkWiFiConnection() {
  if (millis() - lastWifiCheck < WIFI_RETRY_INTERVAL) return wifiConnected;
  lastWifiCheck = millis();

  String response = sendCommandWithResponse("AT+CWJAP?", 2000);
  if (response.indexOf("No AP") != -1 || response.indexOf("ERROR") != -1) {
    wifiConnected = false;
    Serial.println(F("WiFi disconnected! Retrying..."));
    initWiFi();
  } else {
    wifiConnected = true;
  }
  return wifiConnected;
}

String sendCommandWithResponse(const char* cmd, unsigned long timeout) {
  esp8266.println(cmd);
  String response = "";
  unsigned long start = millis();
  while (millis() - start < timeout) {
    while (esp8266.available()) {
      char c = esp8266.read();
      response += c;
    }
  }
  return response;
}

bool sendCommand(const char* cmd, unsigned long timeout) {
  String response = sendCommandWithResponse(cmd, timeout);
  if (response.indexOf("OK") != -1 || response.indexOf("ready") != -1) {
    return true;
  }
  Serial.print(F("Command failed: "));
  Serial.println(response);
  return false;
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long now = millis();

  // Check WiFi status periodically
  if (now - lastWifiCheck >= WIFI_RETRY_INTERVAL) {
    checkWiFiConnection();
  }

  // Emergency mode logic
  if (devices.emergency) {
    handleEmergencyMode(now);
  }

  // Read sensors
  if (now - lastSensorRead >= SENSOR_READ_INTERVAL) {
    readAllSensors();
    lastSensorRead = now;

    if (sensors.smokeLevel > SMOKE_DANGER_THRESHOLD && !devices.emergency) {
      enterEmergencyMode();
    }

    if (!devices.emergency) {
      autoControlLogic(now);
    }

    // Send data via WiFi if connected, otherwise via Serial
    if (wifiConnected) {
      sendDataViaWiFi();
    } else {
      sendJsonToSerial();  // Fallback to USB for Python Bridge
    }
  }

  // Check commands
  if (now - lastCommandCheck >= COMMAND_CHECK_INTERVAL) {
    checkSerialCommands();
    if (wifiConnected) {
      checkWiFiCommands();
    }
    lastCommandCheck = now;
  }

  delay(10);
}

// ==================== SENSOR READING ====================
void readAllSensors() {
  sensors.temperature = dht.readTemperature();
  sensors.humidity = dht.readHumidity();
  if (isnan(sensors.temperature)) sensors.temperature = 0;
  if (isnan(sensors.humidity)) sensors.humidity = 0;

  sensors.distance = readUltrasonic();
  sensors.lightLevel = analogRead(LDR_PIN);
  sensors.smokeLevel = analogRead(MQ2_PIN);
}

long readUltrasonic() {
  digitalWrite(ULTRASONIC_TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(ULTRASONIC_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(ULTRASONIC_TRIG, LOW);

  long duration = pulseIn(ULTRASONIC_ECHO, HIGH, 30000);
  if (duration == 0) return 999;
  long distance = duration * 0.034 / 2;
  if (distance > 400) return 400;
  return distance;
}

// ==================== AUTO CONTROL ====================
void autoControlLogic(unsigned long now) {
  if (devices.autoLight) {
    if (sensors.lightLevel < LIGHT_DARK_THRESHOLD && !devices.bulb1) {
      setBulb1(true);
    } else if (sensors.lightLevel > LIGHT_BRIGHT_THRESHOLD && devices.bulb1) {
      setBulb1(false);
    }
  }

  if (devices.autoMotion) {
    if (sensors.distance > 0 && sensors.distance < MOTION_DISTANCE_CM) {
      if (!motionActive) {
        motionActive = true;
        motionStartTime = now;
        setBulb2(true);
      }
    }
    if (motionActive && (now - motionStartTime >= MOTION_HOLD_MS)) {
      motionActive = false;
      setBulb2(false);
    }
  }
}

// ==================== EMERGENCY MODE ====================
void enterEmergencyMode() {
  devices.emergency = true;
  clearStartTime = 0;
  setBulb1(false);
  setBulb2(false);
  setBuzzer(true);
  devices.autoLight = false;
  devices.autoMotion = false;
  Serial.println(F("{\"status\":\"emergency_triggered\"}"));
}

void handleEmergencyMode(unsigned long now) {
  setBulb1(false);
  setBulb2(false);

  if (sensors.smokeLevel < SMOKE_SAFE_THRESHOLD) {
    if (clearStartTime == 0) {
      clearStartTime = now;
    } else if (now - clearStartTime >= EMERGENCY_CLEAR_TIME) {
      exitEmergencyMode();
    }
  } else {
    clearStartTime = 0;
  }
}

void exitEmergencyMode() {
  devices.emergency = false;
  clearStartTime = 0;
  setBuzzer(false);
  devices.autoLight = true;
  devices.autoMotion = true;
  Serial.println(F("{\"status\":\"emergency_cleared\"}"));
}

// ==================== DEVICE SETTERS ====================
void setBulb1(bool on) { devices.bulb1 = on; digitalWrite(RELAY_BULB1, on ? LOW : HIGH); }
void setBulb2(bool on) { devices.bulb2 = on; digitalWrite(RELAY_BULB2, on ? LOW : HIGH); }
void setBuzzer(bool on) { devices.buzzer = on; digitalWrite(BUZZER_PIN, on ? HIGH : LOW); }

// ==================== DATA TRANSMISSION ====================
void sendDataViaWiFi() {
  // Build JSON
  String json = buildJsonPayload();

  // Build HTTP POST request
  String httpRequest = "POST /api/insert_data.php HTTP/1.1\r\n";
  httpRequest += "Host: ";
  httpRequest += SERVER_HOST;
  httpRequest += "\r\n";
  httpRequest += "Content-Type: application/json\r\n";
  httpRequest += "X-API-Key: ";
  httpRequest += API_KEY;
  httpRequest += "\r\n";
  httpRequest += "Content-Length: ";
  httpRequest += String(json.length());
  httpRequest += "\r\n";
  httpRequest += "Connection: close\r\n\r\n";
  httpRequest += json;

  // Send via ESP8266 TCP connection
  String cmd = "AT+CIPSTART=\"TCP\",\"";
  cmd += SERVER_HOST;
  cmd += "\",";
  cmd += String(SERVER_PORT);

  if (sendCommand(cmd.c_str(), 5000)) {
    String sendCmd = "AT+CIPSEND=";
    sendCmd += String(httpRequest.length());

    if (sendCommand(sendCmd.c_str(), 2000)) {
      esp8266.print(httpRequest);
      delay(1000); // Wait for response

      // Read server response
      while (esp8266.available()) {
        char c = esp8266.read();
        Serial.write(c);
      }

      sendCommand("AT+CIPCLOSE", 1000); // Close connection
    }
  }
}

void sendJsonToSerial() {
  // Fallback: send to USB Serial for Python Bridge
  String json = buildJsonPayload();
  Serial.println(json);
}

String buildJsonPayload() {
  String json = "{";
  json += "\"temperature\":" + String(sensors.temperature, 1) + ",";
  json += "\"humidity\":" + String(sensors.humidity, 1) + ",";
  json += "\"distance\":" + String(sensors.distance) + ",";
  json += "\"light_level\":" + String(sensors.lightLevel) + ",";
  json += "\"smoke_level\":" + String(sensors.smokeLevel) + ",";
  json += "\"bulb1_status\":" + String(devices.bulb1 ? 1 : 0) + ",";
  json += "\"bulb2_status\":" + String(devices.bulb2 ? 1 : 0) + ",";
  json += "\"buzzer_status\":" + String(devices.buzzer ? 1 : 0) + ",";
  json += "\"emergency_flag\":" + String(devices.emergency ? 1 : 0) + ",";
  json += "\"device_id\":\"" + String(DEVICE_ID) + "\"";
  json += "}";
  return json;
}

// ==================== COMMAND HANDLING ====================
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

void checkWiFiCommands() {
  // Check if server sent any commands back
  while (esp8266.available()) {
    char c = esp8266.read();
    if (c == '\n') {
      // Parse server response for embedded commands
      if (espBuffer.indexOf("command_type") != -1) {
        processCommand(espBuffer);
      }
      espBuffer = "";
    } else if (c != '\r') {
      espBuffer += c;
    }
  }
}

void processCommand(String cmdStr) {
  cmdStr.trim();
  if (cmdStr.length() == 0) return;

  String type = extractJsonValue(cmdStr, "command_type");
  String value = extractJsonValue(cmdStr, "command_value");

  if (type.length() == 0) return;

  if (type == "acknowledge") {
    setBuzzer(false);
    return;
  }

  if (devices.emergency && type != "reset_emergency") {
    return;
  }

  bool isOn = (value == "ON" || value == "1" || value == "true");

  if (type == "bulb1") { devices.autoLight = false; setBulb1(isOn); }
  else if (type == "bulb2") { devices.autoMotion = false; setBulb2(isOn); }
  else if (type == "buzzer") { setBuzzer(isOn); }
  else if (type == "reset_emergency") {
    if (devices.emergency && sensors.smokeLevel < SMOKE_SAFE_THRESHOLD) {
      exitEmergencyMode();
    }
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
    if (quote2 != -1) return json.substring(quote1 + 1, quote2);
  }

  int valStart = colon + 1;
  while (valStart < json.length() && (json[valStart] == ' ' || json[valStart] == '\t')) valStart++;
  int valEnd = valStart;
  while (valEnd < json.length() && json[valEnd] != ',' && json[valEnd] != '}') valEnd++;
  return json.substring(valStart, valEnd);
}