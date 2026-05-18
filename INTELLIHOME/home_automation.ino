/*
========================================================
ADVANCED HOME AUTOMATION IoT - NODEMCU ESP8266
========================================================
Hardware Corrections Implemented:
- Ultrasonic Signal Smoothing via Moving Average Window Filter
- Smoke Sensor Digital Debounce Filtering (Prevents Fake Alarms)
- Inverted LDR Voltage Calibration (>900 Dark -> Bulb1 ON)
- Full Safety Standby Retained
- UPGRADED: 5sec smoke sampling → 5sec alert → repeat cycle

Board: ESP8266 NodeMCU
========================================================
*/

#include <DHT.h>

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
const unsigned long SENSOR_READ_INTERVAL   = 2000; // Increased sample frequency for better real-time control
const unsigned long COMMAND_CHECK_INTERVAL = 500;
const unsigned long EMERGENCY_CLEAR_TIME   = 20000;

// Advanced Filtering Parameters
const int ULTRASONIC_SAMPLES = 5;       // Number of samples for smoothing window
const int SMOKE_DEBOUNCE_CHECKS = 5;    // Successive confirms required before alarm
const int SMOKE_DEBOUNCE_DELAY_MS = 5;  // Delay between debounce checks

// Validated Conditions
const int MOTION_DISTANCE_CM = 50;      // Hard boundary: Strictly below 50cm turns Bulb 2 ON
const unsigned long MOTION_HOLD_MS = 1000;

// Corrected Physical LDR Mapping (High reading = Dark environment)
const int LDR_DARK_THRESHOLD = 400;    

// NEW SMOKE CYCLE CONFIGURATION
const unsigned long SMOKE_SAMPLE_DURATION = 5000;  // 5 seconds sampling window
const unsigned long SMOKE_ALERT_DURATION  = 5000;  // 5 seconds alert when smoke detected

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

// NEW SMOKE CYCLE VARIABLES
enum SmokeCycleState {
  SMOKE_IDLE,      // Normal operation, checking for smoke
  SMOKE_ALERT      // Alert active (buzzer on, bulbs forced off)
};
SmokeCycleState smokeCycleState = SMOKE_IDLE;
unsigned long smokeCycleStartTime = 0;
unsigned long smokeDetectedTime = 0;

// ==================== FUNCTION DECLARATIONS ====================
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
  Serial.println("       HARDWARE FILTERS ACTIVE        ");
  Serial.println("    SMOKE: 5sec SAMPLE → 5sec ALERT   ");
  Serial.println("======================================");

  pinMode(MQ2_DIGITAL_PIN, INPUT); 
  pinMode(ULTRASONIC_TRIG, OUTPUT);
  pinMode(ULTRASONIC_ECHO, INPUT);

  pinMode(RELAY_BULB1, OUTPUT);
  pinMode(RELAY_BULB2, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);

  // Default States (Active Low Relay Safe Defaults)
  digitalWrite(RELAY_BULB1, HIGH);
  digitalWrite(RELAY_BULB2, HIGH);
  digitalWrite(BUZZER_PIN, LOW);

  dht.begin();
  Serial.println("System Core Initialized cleanly.");
  Serial.println();
  
  // Initialize smoke cycle
  smokeCycleState = SMOKE_IDLE;
  smokeCycleStartTime = millis();
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long now = millis();

  // Handle emergency mode (smoke alert)
  if (devices.emergency) {
    handleEmergencyMode(now);
  }

  if (now - lastSensorRead >= SENSOR_READ_INTERVAL) {
    readAllSensors();
    lastSensorRead = now;

    // NEW SMOKE LOGIC: 5 second sampling, then 5 second alert if detected
    if (!devices.emergency) {
      // Check smoke with debounce
      if (sensors.smokeStatus == HIGH && debounceSmokeSensor()) {
        if (smokeCycleState == SMOKE_IDLE) {
          // First detection in this cycle
          if (smokeDetectedTime == 0) {
            smokeDetectedTime = now;
            Serial.println("[SMOKE] Smoke detected! Starting 5 second sampling window...");
          }
          
          // Check if we've been detecting for 5 seconds
          if (now - smokeDetectedTime >= SMOKE_SAMPLE_DURATION) {
            // Smoke persisted for 5 seconds - trigger alert
            enterEmergencyMode();
            smokeCycleState = SMOKE_ALERT;
            smokeDetectedTime = 0;
          }
        }
      } else {
        // No smoke detected - reset detection timer
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

// ==================== HARDWARE FILTERED SENSOR READING ====================
void readAllSensors() {
  // DHT11 Processing
  sensors.temperature = dht.readTemperature();
  sensors.humidity = dht.readHumidity();

  if (isnan(sensors.temperature)) sensors.temperature = 0;
  if (isnan(sensors.humidity))    sensors.humidity = 0;

  // Signal-Smoothed Distance Engine
  sensors.distance = getFilteredUltrasonic();

  // Instantaneous Reading for initial evaluation
  sensors.smokeStatus = digitalRead(MQ2_DIGITAL_PIN);

  // Physical Analog LDR capture
  sensors.lightLevel = analogRead(LDR_PIN);
}

// Advanced Moving Average Filter for Ultrasonic Noise Elimination
long getFilteredUltrasonic() {
  long totalDistance = 0;
  int validSamplesCount = 0;

  for (int i = 0; i < ULTRASONIC_SAMPLES; i++) {
    digitalWrite(ULTRASONIC_TRIG, LOW);
    delayMicroseconds(2);
    digitalWrite(ULTRASONIC_TRIG, HIGH);
    delayMicroseconds(10);
    digitalWrite(ULTRASONIC_TRIG, LOW);

    long duration = pulseIn(ULTRASONIC_ECHO, HIGH, 25000); // 25ms timeout bounds max distance limits
    long distanceSample = duration * 0.034 / 2;

    // Discard anomalies (out-of-range false spikes)
    if (distanceSample > 0 && distanceSample < 400) {
      totalDistance += distanceSample;
      validSamplesCount++;
    }
    delay(10); // Short recovery rest between physical sound bursts
  }

  if (validSamplesCount == 0) {
    return 999; // Explicit indicator for a failed sonic window return
  }

  return (totalDistance / validSamplesCount); // Returns highly accurate mathematical average
}

// Digital Debounce Verifier to counter MQ2 Sensor Power Fluctuations
bool debounceSmokeSensor() {
  for (int i = 0; i < SMOKE_DEBOUNCE_CHECKS; i++) {
    delay(SMOKE_DEBOUNCE_DELAY_MS);
    if (digitalRead(MQ2_DIGITAL_PIN) == LOW) {
      return false; // Errant hardware burst caught; validation dropped
    }
  }
  return true; // Smoke condition verified true across time frame
}

// ==================== AUTO CONTROL CONTROLLERS ====================
void autoControlLogic(unsigned long now) {

  // 1. Precise Inverted LDR Control Engine
  if (sensors.lightLevel > LDR_DARK_THRESHOLD) { // Real-world test: Covering sensor increases value above 900
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

  // 2. High-Precision Ultrasonic Engine
  if (devices.autoMotion) {
    // Condition: Strictly less than 50cm handles activation triggers
    if (sensors.distance > 0 && sensors.distance < MOTION_DISTANCE_CM) {
      if (!motionActive) {
        motionActive = true;
        motionStartTime = now;
        setBulb2(true);
        Serial.println("[SYSTEM-EXEC]: Motion Confirmed (<50cm) -> Bulb2 Activated");
      } else {
        motionStartTime = now; // Continuous presence refreshes hold flag
      }
    }

    // Evaluated timeout engine drop
    if (motionActive && (now - motionStartTime >= MOTION_HOLD_MS)) {
      motionActive = false;
      setBulb2(false);
      Serial.println("[SYSTEM-EXEC]: Distance Clear Threshold Reached -> Bulb2 Deactivated");
    }
  }
}

// ==================== SAFETY SUBSYSTEMS ====================
void enterEmergencyMode() {
  devices.emergency = true;
  clearStartTime = 0;

  setBulb1(false);
  setBulb2(false);
  setBuzzer(true);

  Serial.println("\n====================================");
  Serial.println("!! CRITICAL HARDWARE ALARM TRIGGERED !!");
  Serial.println("     SMOKE CONFIRMED FOR 5 SECONDS    ");
  Serial.println("        FORCED EM-FAILSAFE ACTIVATED   ");
  Serial.println("======================================");
}

void handleEmergencyMode(unsigned long now) {
  setBulb1(false);
  setBulb2(false);

  // NEW: Auto exit after SMOKE_ALERT_DURATION (5 seconds)
  if (clearStartTime == 0) {
    clearStartTime = now;
    Serial.println("[Failsafe]: Alert active. Will auto-exit after 5 seconds...");
  }
  else if (now - clearStartTime >= SMOKE_ALERT_DURATION) {
    exitEmergencyMode();
  }
  
  // Also check if smoke cleared early - if so, still wait for timer to reset cycle
  if (digitalRead(MQ2_DIGITAL_PIN) == LOW) {
    // Smoke is gone, but we still complete the 5 second alert period
    // This is handled by the timer above
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
  digitalWrite(RELAY_BULB1, on ? LOW : HIGH); // Active Low Relays
}

void setBulb2(bool on) {
  devices.bulb2 = on;
  digitalWrite(RELAY_BULB2, on ? LOW : HIGH); // Active Low Relays
}

void setBuzzer(bool on) {
  devices.buzzer = on;
  digitalWrite(BUZZER_PIN, on ? HIGH : LOW);   // Active High Buzzer
}

// ==================== METRIC LOGGING ====================
void printReadableData() {
  Serial.println("================ METRICS ================");
  Serial.print("Temp/Humid:   "); Serial.print(sensors.temperature, 1); Serial.print(" C / "); Serial.print(sensors.humidity, 1); Serial.println(" %");
  Serial.print("Ultrasonic:   "); Serial.print(sensors.distance); Serial.println(" cm");
  Serial.print("LDR Value:    "); Serial.print(sensors.lightLevel); Serial.println(sensors.lightLevel > LDR_DARK_THRESHOLD ? " (DARK ENVIRONMENT)" : " (BRIGHT ENVIRONMENT)");
  Serial.print("Smoke Status: "); Serial.println(sensors.smokeStatus == HIGH ? "DANGER SIGNAL" : "STABLE");
  
  // Show smoke cycle info
  if (smokeDetectedTime > 0 && !devices.emergency) {
    unsigned long detectingFor = (millis() - smokeDetectedTime) / 1000;
    Serial.print("Smoke Timer:  Detecting for "); Serial.print(detectingFor); Serial.println(" seconds (need 5 to alert)");
  } else if (devices.emergency) {
    unsigned long alertRemaining = (SMOKE_ALERT_DURATION - (millis() - clearStartTime)) / 1000;
    Serial.print("Alert Timer:  "); Serial.print(alertRemaining); Serial.println(" seconds remaining");
  } else {
    Serial.println("Smoke Timer:  IDLE (no detection)");
  }
  
  Serial.print("Bulb 1 Stat:  "); Serial.println(devices.bulb1 ? "ON" : "OFF");
  Serial.print("Bulb 2 Stat:  "); Serial.println(devices.bulb2 ? "ON" : "OFF");
  Serial.print("Emergency:    "); Serial.println(devices.emergency ? "CRITICAL LOCK" : "IDLE");
  Serial.println("========================================\n");
}

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
  Serial.println(json);
}

// ==================== INTERFACING COMMAND PROCESSORS ====================
void checkSerialCommands() {
  while (Serial.available()) {
    char c = Serial.read();
    if (c == '\n') {
      processCommand(serialBuffer);
      serialBuffer = "";
    }
    else if (c != '\r') {
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
  }
  else if (type == "bulb2") {
    setBulb2(isOn);
  }
  else if (type == "buzzer") {
    setBuzzer(isOn);
  }
  else if (type == "acknowledge") {
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