import serial
import time
import requests
import json

# Connect to your NodeMCU (Change 'COM8' if your port changes)
arduino = serial.Serial('COM8', 115200, timeout=1)
time.sleep(2)

# The exact URL where your data saving logic resides
TARGET_URL = "http://localhost/lassss/api/insert_data.php"
API_KEY = "HA_IOT_SECRET_KEY_2026_CHANGE_THIS"
headers = {'Content-Type': 'application/json', 'X-API-Key': API_KEY}

print("IoT Bridge Active... (Ctrl+C to stop)")

while True:
    try:
        # 1. Read the line from the serial port
        line = arduino.readline().decode('utf-8', errors='ignore').strip()
        
        if line:
            # 2. Show it on the serial monitor of this bridge file instantly
            print(f"[MCU]: {line}")
            
            # 3. If it's the JSON data payload, send it to the URL
            if line.startswith("{") and line.endswith("}"):
                data = json.loads(line)
                data['api_key'] = API_KEY  # Add the secret key to the data
                
                # Push straight to your web file
                response = requests.post(TARGET_URL, json=data, headers=headers, timeout=5)
                print(f" -> Sent to web! Response: {response.text.strip()}")

    except KeyboardInterrupt:
        print("\nStopping bridge...")
        break
    except Exception as e:
        print(f"Error: {e}")
        time.sleep(1)

arduino.close()