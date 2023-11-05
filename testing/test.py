from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import Select
import os
import requests
import time
import shutil

test_channel_name = "Dom"
test_channel_id = "UC1kvM3pZGg3QaSQBS91Cwzg"

# Define chrome options
chrome_options = Options()
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--headless")
chrome_options.add_argument("--disable-dev-shm-usage")

class Channel():
    def __init__(self, name, status, count) -> None:
        self.name = name
        self.status = status
        self.count = int(count)

def load_channels(webpage_text): # creates channel objects by reading the homepage url
    channels = []
    start = 0
    end = 0
    while end != -1:
        start = webpage_text.find("class=\"channel\"", start)
        start = webpage_text.find(">", start) + 1
        end = webpage_text.find("</", start)
        name = webpage_text[start:end]
        start = webpage_text.find("class=\"status\"", start)
        start = webpage_text.find(">", start) + 1
        end = webpage_text.find("</", start)
        status = webpage_text[start:end]
        start = webpage_text.find("class=\"count\"", start)
        start = webpage_text.find(">", start) + 1
        end = webpage_text.find("</", start)
        count = webpage_text[start:end]
        if count == "":
            break
        channels.append(Channel(name, status, count))
        end = webpage_text.find("class=\"channel\"", start)
    return channels

# Allow a port override in case we're testing on a dev machine
if os.path.exists("/test/port_override.txt"):
    try:
        with open("/test/port_override.txt", "r") as port_file:
            test_port = int(port_file.read())
    except:
        test_port = 4445
else:
    test_port = 4445

homepage_url = f"http://localhost:{test_port}"
admintools_url = f"http://localhost:{test_port}/admintools.php"
script_directory = os.path.dirname(os.path.abspath(__file__))
print(homepage_url)
#Initialize chrome webdriver
web = webdriver.Chrome(options=chrome_options)
# Get our main pages, ensure we can connect properly
web.get(homepage_url)

web.get(admintools_url)

# Add test channel
web.find_element(By.ID, "channel_name").send_keys(test_channel_name)
web.find_element(By.ID, "channel_id").send_keys(test_channel_id)
web.find_element(By.ID, "add_channel_button").click()
alert = WebDriverWait(web, 10).until(EC.alert_is_present())
assert "Channel added" in alert.text
alert.accept()
# Force a scan
web.find_element(By.ID, "force-scan").click()
alert = WebDriverWait(web, 10).until(EC.alert_is_present())
assert "Forcing ASMR Scan." in alert.text
alert.accept()

# Wait for the new channel to appear, and for the video to download
max_retries = 10
refresh_rate = 5
tries = 0
passed = False # To track wether the test passed or failed
while tries < max_retries:
    tries += 1
    web.get(homepage_url)
    channels = load_channels(web.page_source)
    test_channel = None
    for channel in channels:
        if channel.name == test_channel_name:
            test_channel = channel
    if not test_channel == None:
        if test_channel.status == "Archived" and test_channel.count >= 1:
            passed = True
            break
    time.sleep(refresh_rate)
assert passed
            


# Declare proudly that our testing has passed
print("All automated tests have passed.")