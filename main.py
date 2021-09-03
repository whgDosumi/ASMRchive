"""
Plans:
First: make it scan an entire channel and archive all of its ASMR videos using youtube_dl
Then, after it does that, make it scan the RSS and see if there are any new ASMR videos to download periodically.

Eventually, I want it to check for live ASMR and record them until a full archive can be obtained with the original method. That way, if
it's removed, we can still keep the recording. 

"""

"""
NOTES:
RSS = https://www.youtube.com/feeds/videos.xml?channel_id= + channel_id
"""

import time
from multiprocessing import Process
import notify_run
import youtube_dl
import requests
import os
import feedparser
import unicodedata
import re



def slugify(value: str, allow_unicode=True): #used to sanitize string for filesystem
    value = str(value)
    if allow_unicode:
        value = unicodedata.normalize('NFKC', value)
    else:
        value = unicodedata.normalize('NFKD', value).encode('ascii', 'ignore').decode('ascii')
    value = re.sub(r'[^\w\s-]', '', value)
    return re.sub(r'[-\s]+', '-', value).strip('-_')

class channel():
    def __init__(self, name: str, channel_id: str, plid: str, status: str):
        self.name = name #alias for the channel. Eg. Okayu Ch.
        self.channel_id = channel_id #Channel ID. Eg
        self.plid = plid
        self.status = status
    
    def save(self):
        if not os.path.isdir("channels"):
            os.makedirs("channels")
        with open(os.path.join("channels", slugify(self.name) + ".channel"), "w") as outfile:
            outfile.write(self.name + "\n" + self.channel_id + "\n" + self.plid + "\n" + self.status)

    def getRSS(self): #returns channel's RSS feed as a list of entries
        return feedparser.parse(requests.get("https://www.youtube.com/feeds/videos.xml?channel_id=" + self.channel_id).text).entries

def load_channels():
    channels = list()
    for data_file in os.listdir("channels"):
        with open(os.path.join("channels", data_file), "r") as file:
            lines = file.read().splitlines()
            channels.append(channel(lines[0], lines[1], lines[2], lines[3]))
    return channels

def load_keywords():
    keywords = list()
    with open("keywords.txt", "r", encoding="UTF-8") as key_file:
        for line in key_file.read().splitlines():
            keywords.append(line)
    return keywords

def ASMRchive(channels: list, keywords: list):
    pass

if __name__ == "__main__":
    channels = load_channels()
    keywords = load_keywords()
    ASMRchive(channels, keywords)