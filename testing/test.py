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

# my channel id: UC1kvM3pZGg3QaSQBS91Cwzg

# Define chrome options
chrome_options = Options()
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--headless")
chrome_options.add_argument("--disable-dev-shm-usage")
# Allow a port override in case we're testing on a dev machine
if os.path.exists("/test/port_override.txt"):
    try:
        with open("/test/port_override.txt", "r") as port_file:
            test_port = int(port_file.read())
    except:
        test_port = 4445
else:
    test_port = 4445

script_directory = os.path.dirname(os.path.abspath(__file__))

homepage_url = f"http://localhost:{test_port}"
admintools_url = f"http://localhost:{test_port}/admintools.php"
print(homepage_url)
#Initialize chrome webdriver
web = webdriver.Chrome(options=chrome_options)
# Get our main pages, ensure we can connect properly
web.get(homepage_url)
web.get(admintools_url)