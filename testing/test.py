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
import random
import sys
import argparse

supported_formats = [".wav", ".webm", ".flac", ".opus", ".m4a", ".mp3"]

test_channel_name = "Dom"
test_channel_id = "UC1kvM3pZGg3QaSQBS91Cwzg"


default_download_directory = "/test/downloads"

# Define chrome options
chrome_options = Options()
chrome_options.add_argument("--no-sandbox")
chrome_options.add_argument("--headless")
chrome_options.add_argument("--disable-dev-shm-usage")
chrome_options.add_experimental_option("prefs", {
    "download.default_directory": default_download_directory,
    "download.prompt_for_download": False,
    "download.directory_upgrade": True,
})


class Video():
    def __init__(self, url, thumbnail, title, upload_date, runtime, comments) -> None:
        self.url = url
        self.thumbnail = thumbnail
        self.title = title
        self.upload_date = upload_date
        self.runtime = runtime
        self.comments = comments
        self.valid = self.is_valid()
    
    def is_valid(self):
        if self.upload_date == "1600-01-01" and self.runtime == "" and "default_thumbnail.png" in self.thumbnail:
            return False
        return True


class Channel():
    def __init__(self, name, status, count, url) -> None:
        self.name = name
        self.status = status
        self.count = int(count)
        self.url = url
        self.videos = self.load_videos()
    def load_videos(self):
        web.get(self.url)
        webpage_text = web.page_source
        videos = []
        start = webpage_text.find("<tbody>")
        end = 0
        while end != -1 and start != -1:
            start = webpage_text.find("<tr onclick=\"document.location", start) + 34
            end = webpage_text.find("\'", start)
            url = self.url.replace("channel.php", webpage_text[start:end])
            start = webpage_text.find("src=", start) + 5
            end = webpage_text.find("\"", start)
            thumbnail = webpage_text[start:end]
            start = webpage_text.find("class=\"title\"", start) + 14
            end = webpage_text.find("</p>", start)
            title = webpage_text[start:end]
            start = webpage_text.find("class=\"date\"", start) + 13
            end = webpage_text.find("<", start)
            upload_date = webpage_text[start:end]
            start = webpage_text.find("class=\"date\"", start) + 13
            end = webpage_text.find("<", start)
            runtime = webpage_text[start:end]
            start = webpage_text.find("class=\"count\"", start) + 14
            end = webpage_text.find("<", start)
            comments = webpage_text[start:end]
            videos.append(Video(url, thumbnail, title, upload_date, runtime, comments))
            start = webpage_text.find("<tr onclick=\"document.location", start)
        return videos

def load_channels(): # creates channel objects by reading the homepage url
    web.get(homepage_url)
    webpage_text = web.page_source
    channels = []
    start = webpage_text.find("<tbody>")
    end = 0
    while end != -1:
        start = webpage_text.find("<tr onclick=\"window.location=", start) + 30
        end = webpage_text.find("\'\">", start)
        url = homepage_url + "/" + webpage_text[start:end]
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
        channels.append(Channel(name, status, count, url))
        end = webpage_text.find("class=\"channel\"", start)
        start = webpage_text.find("<tr onclick=\"window.location=", start)
        if start == -1:
            break
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

# Get args

p = argparse.ArgumentParser()
p.add_argument("--url")
p.add_argument("--test", default="")
args = p.parse_args()


if args.url:
    # Allow passing a different url for when it's not running on the same host (or to test a reverse proxy)
    homepage_url = args.url
    admintools_url = homepage_url + "/admintools.php"
else:
    homepage_url = f"http://localhost:{test_port}"
    admintools_url = f"http://localhost:{test_port}/admintools.php"


print(f"Using {homepage_url} as the homepage url")
script_directory = os.path.dirname(os.path.abspath(__file__))
print(f"Testing on: {homepage_url}")
#Initialize chrome webdriver
web = webdriver.Chrome(options=chrome_options)
# Get our main pages, ensure we can connect properly
timeout = 60
web.get(homepage_url)
WebDriverWait(web, timeout).until(
    EC.presence_of_element_located((By.ID, "main"))
)
web.get(admintools_url)
WebDriverWait(web, timeout).until(
    EC.presence_of_element_located((By.ID, "main"))
)

# Do the DLP test if requested.
if args.test.lower() == "dlp" or args.test.lower() == "dlponly":
    web.get(admintools_url)
    # Verify the version is wrong
    assert "stable@2024.12.06" in web.page_source
    # Click the update button
    web.find_element(By.ID, "dlp_update").click()
    alert = WebDriverWait(web, 10).until(EC.alert_is_present())
    alert.accept()
    # Wait for the version to update
    max_retries = 15
    refresh_rate = 5
    tries = 0
    passed = False
    while tries < max_retries:
        tries += 1
        web.get(admintools_url)
        web.find_element(By.ID, "dlp_check").click()
        alert = WebDriverWait(web, 10).until(EC.alert_is_present())
        alert.accept()
        try:
            time.sleep(refresh_rate)
            web.get(admintools_url)
            web.find_element(By.ID, "dlp_update")
        except:
            passed = True
            break
    assert passed
    if args.test.lower() == "dlponly":
        exit()

# Add test channel
web.get(admintools_url)
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
max_retries = 15
refresh_rate = 5
tries = 0
passed = False # To track wether the test passed or failed
while tries < max_retries:
    tries += 1
    web.get(homepage_url)
    channels = load_channels()
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

# Test comments on the videos
test_comment_name = "Dominic Toretto"
test_comment_text = "01:23 is my favorite part."
tests_per_channel = 3 # How many tests should be ran on a channel.

channels = load_channels()
passes = 0
tests_ran = 0
invalids = 0
for channel in channels:
    tests = 0
    while tests < tests_per_channel:
        tests += 1
        tests_ran += 1
        video = random.choice(channel.videos)
        if not video.valid:
            invalids += 1
        else:
            web.get(video.url)
            # Test the download button
            before = os.listdir(default_download_directory);
            web.find_element(By.ID, "downloadbutton").click()
            success = False
            attempts = 0
            while not success and attempts < 5:
                # Give it some time to download
                time.sleep(5)
                after = os.listdir(default_download_directory)
                for format in supported_formats:
                    for file in after:
                        if format in file and not file in before:
                            if os.path.getsize(os.path.join(default_download_directory, file)) > 100:
                                success = True
                                os.remove(os.path.join(default_download_directory, file))
                    if success:
                        break
                attempts += 1
            assert success
            # Confirm yt button is working
            assert web.find_element(By.ID, "ytlink").get_attribute("href") != ""

            # Add a comment
            web.implicitly_wait(5)
            web.find_element(By.ID, "name_box").send_keys(test_comment_name)
            web.find_element(By.ID, "message_box").send_keys(test_comment_text)
            web.find_element(By.ID, "post_button").click()
            # The page should reload
            web.implicitly_wait(5)
            web.find_element(By.CLASS_NAME, "delete_button")
            # Make sure the comment exists
            web.get(video.url)
            assert (test_comment_name in web.page_source)

            # Test the timestamp
            audio_element = web.find_element(By.ID, "asmr")
            max_duration = web.execute_script("return arguments[0].duration;", audio_element)
            max_duration = int(max_duration)
            comment_stamps = web.find_elements(By.CLASS_NAME, "comment_timestamp")
            for stamp in comment_stamps:
                tstamp = web.execute_script("return arguments[0].innerHTML;", stamp)
                if tstamp == "01:23":
                    break
            stamp.click()
            current_time = web.execute_script("return arguments[0].currentTime;", audio_element)
            assert current_time > 80 or int(current_time) == max_duration

            # Determine the latest comment and delete it.
            comments = web.find_elements(By.CLASS_NAME, "delete_button")
            latest_comment = comments[0]
            for comment in comments:
                if comment.location["y"] < latest_comment.location["y"]: 
                    latest_comment = comment
            latest_comment.click()
            alert = WebDriverWait(web, 10).until(EC.alert_is_present())
            assert test_comment_name in alert.text
            alert.accept()
            # Confirm the comment is deleted
            assertion_attempts = 0
            while True:
                assertion_attempts += 1
                try:
                    web.get(video.url)
                    assert (not test_comment_name in web.page_source)
                    break
                except Exception as e:
                    if assertion_attempts >= 5:
                        print("Test comment not deleted.")
                        print(f"channel: {channel.name}")
                        print(f"video url: {video.url}")
                        print(f"video title: {video.title}")
                        raise # if we've tried 5 times it's not gonna work the 6th. 
                    time.sleep(1)
            passes += 1
# Make sure we passed some tests, and also didn't have any failures other than for invalids. 
assert (passes == tests_ran - invalids and passes > 0)
web.quit()
# Declare proudly that our testing has passed
print("All automated tests have passed.")
