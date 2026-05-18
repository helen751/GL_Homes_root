import serial
import time
import requests
import json
import threading

# Configuration
SERIAL_PORT = 'COM8'  # Change to your port (e.g., '/dev/ttyUSB0' on Linux)
BAUD_RATE = 115200
API_BASE_URL = "http://localhost/home_automation"  # Change to your web server path
API_KEY = "HA_IOT_SECRET_KEY_2026_CHANGE_THIS"

headers = {'Content-Type': 'application/json', 'X-API-Key': API_KEY}

print("=" * 50)
print("HOME AUTOMATION IoT - Bridge")
print(f"Serial Port: {SERIAL_PORT}")
print(f"API URL: {API_BASE_URL}")
print("=" * 50)

# Global flag for running
running = True

def fetch_and_send_commands(ser):
    """Fetch pending commands from the database and send to ESP"""
    while running:
        try:
            # Fetch pending commands
            response = requests.get(f"{API_BASE_URL}/api.php?action=get_commands", timeout=5)
            if response.status_code == 200:
                data = response.json()
                if data.get('success') and data.get('commands'):
                    commands = data['commands']
                    for cmd in commands:
                        # Format command for ESP
                        if cmd['command_type'] == 'bulb1':
                            command_str = f"BULB1:{cmd['command_value']}"
                        elif cmd['command_type'] == 'bulb2':
                            command_str = f"BULB2:{cmd['command_value']}"
                        elif cmd['command_type'] == 'buzzer':
                            command_str = f"BUZZER:{cmd['command_value']}"
                        elif cmd['command_type'] == 'reset_emergency':
                            command_str = "RESET_EMERGENCY"
                        elif cmd['command_type'] == 'update_threshold':
                            command_str = f"THRESHOLD:{cmd['command_value']}"
                        else:
                            continue
                        
                        # Send to ESP
                        print(f"[CMD] Sending: {command_str}")
                        ser.write(f"{command_str}\n".encode())
                        time.sleep(0.5)
                        
                        # Mark as executed
                        mark_url = f"{API_BASE_URL}/api.php?action=command_executed"
                        requests.post(mark_url, data={'command_id': cmd['id']}, timeout=5)
                        print(f"[CMD] Command {cmd['id']} executed and marked")
            time.sleep(2)  # Check for commands every 2 seconds
        except Exception as e:
            print(f"[ERROR] Command fetch error: {e}")
            time.sleep(5)

def main():
    global running
    
    try:
        # Connect to ESP/Arduino
        ser = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=1)
        time.sleep(2)
        print(f"[OK] Connected to {SERIAL_PORT}")
        
        # Start command fetching thread
        cmd_thread = threading.Thread(target=fetch_and_send_commands, args=(ser,), daemon=True)
        cmd_thread.start()
        
        print("[OK] Bridge active. Waiting for sensor data...")
        print("[INFO] Press Ctrl+C to stop\n")
        
        while running:
            try:
                if ser.in_waiting:
                    line = ser.readline().decode('utf-8', errors='ignore').strip()
                    
                    if line:
                        print(f"[ESP] {line}")
                        
                        # If it's JSON data, send to web server
                        if line.startswith("{") and line.endswith("}"):
                            try:
                                data = json.loads(line)
                                
                                # Send to insert_data.php
                                response = requests.post(
                                    f"{API_BASE_URL}/insert_data.php",
                                    json=data,
                                    headers=headers,
                                    timeout=5
                                )
                                print(f"[WEB] Response: {response.text[:100]}")
                            except json.JSONDecodeError:
                                print(f"[WARN] Invalid JSON received")
                            except Exception as e:
                                print(f"[ERROR] Web send error: {e}")
                
                time.sleep(0.1)
                
            except serial.SerialException as e:
                print(f"[ERROR] Serial error: {e}")
                time.sleep(5)
                
    except serial.SerialException as e:
        print(f"[ERROR] Could not open {SERIAL_PORT}: {e}")
        print("[INFO] Make sure the ESP is connected and the port is correct")
    except KeyboardInterrupt:
        print("\n[INFO] Stopping bridge...")
        running = False
    finally:
        try:
            ser.close()
        except:
            pass
        print("[OK] Bridge stopped")

if __name__ == "__main__":
    main()