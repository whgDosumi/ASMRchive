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
from multiprocessing import Process, Pool
import notify_run
import youtube_dl
import requests
import os
import feedparser
import unicodedata
import re
import datetime

def log(message: str):
    with open("log.txt", "a", encoding="UTF-8") as logfile:
        logfile.write("<" + datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S") + "> " + message + "\n")
    print(message)

def slugify(value: str, allow_unicode=True): #used to sanitize string for filesystem
    value = str(value)
    if allow_unicode:
        value = unicodedata.normalize('NFKC', value)
    else:
        value = unicodedata.normalize('NFKD', value).encode('ascii', 'ignore').decode('ascii')
    value = re.sub(r'[^\w\s-]', '', value)
    return re.sub(r'[-\s]+', '-', value).strip('-_')

class channel():
    def __init__(self, name: str, channel_id: str, status: str):
        self.name = name #alias for the channel. Eg. Okayu Ch.
        self.channel_id = channel_id #Channel ID. Eg
        self.status = status
    
    def save(self):
        if not os.path.isdir("channels"):
            os.makedirs("channels")
        with open(os.path.join("channels", slugify(self.name) + ".channel"), "w") as outfile:
            outfile.write(self.name + "\n" + self.channel_id + "\n" + self.status)

    def get_rss(self): #returns channel's RSS feed as a list of entries
        return feedparser.parse(requests.get("https://www.youtube.com/feeds/videos.xml?channel_id=" + self.channel_id).text).entries

    def get_all_plist(self):
        return "https://www.youtube.com/channel/" + self.channel_id

def load_channels():
    channels = list()
    for data_file in os.listdir("channels"):
        with open(os.path.join("channels", data_file), "r") as file:
            lines = file.read().splitlines()
            channels.append(channel(lines[0], lines[1], lines[2]))
    return channels

def load_keywords():
    keywords = list()
    with open("keywords.txt", "r", encoding="UTF-8") as key_file:
        for line in key_file.read().splitlines():
            keywords.append(line)
    return keywords

def get_video(url, ydl_opts):
    try:
        with youtube_dl.YoutubeDL(ydl_opts) as ydl:
            ydl.download((url,))
            return True
    except Exception as e:
        log(url + " - " + str(e))
        return False
        

def ASMRchive(channels: list, keywords: list, output_directory: str):
    for chan in channels:
        if chan.status == "new": #we want to do a full archive of all videos
            to_download = list()
            ydl_opts = {
                'nocheckcertificate': True,
                'ignoreerrors': True,
                'extract_flat': "in_playlist",
            }
            with youtube_dl.YoutubeDL(ydl_opts) as ydl: #This one gets the playlists
                meta = ydl.extract_info(chan.get_all_plist(), download=False)
            ydl_opts = {
                'nocheckcertificate': True,
                'ignoreerrors': True,
                'extract_flat': True,
            }
            for item in meta["entries"]:
                if item["title"] == "Uploads":
                    with youtube_dl.YoutubeDL(ydl_opts) as ydl: #This one gets the videos from the 'uploads' playlist (all uploads hopefully.)
                        meta = ydl.extract_info(item["url"], download=False)
                        break
            for video in meta["entries"]:
                for word in keywords:
                    if not ("https://www.youtube.com/watch?v=" + video["id"]) in to_download:
                        if word.lower() in video["title"].lower():
                            to_download.append([video["title"] ,"https://www.youtube.com/watch?v=" + video["id"]])
                            break
            ydl_opts = {
                'nocheckcertificate': True,
                'writethumbnail': True,
                'format': "bestaudio/best",
                "writedescription": True,
                "writeinfojson": True,
            }
            processes = []
            while len(to_download) >= 1:
                queue = to_download[0:10]
                for video in queue:
                    if not os.path.exists(os.path.join(output_directory, slugify(chan.name), slugify(video[0]))):
                        os.makedirs(os.path.join(output_directory, slugify(chan.name), slugify(video[0])))
                    ydl_opts['outtmpl'] = os.path.join(output_directory, slugify(chan.name), slugify(video[0]), '%(title)s.%(ext)s')
                    p = Process(target=get_video, args=(video[1], ydl_opts))
                    p.start()
                    processes.append(p)
                TIMEOUT = 20
                time_spent = 0
                alive = True
                while alive: #forgive my handling of these processes... (youtube throttles sometimes, this is to 'refresh' that throttling)
                    alive = False
                    total = 0
                    for p in processes:
                        if p.is_alive():
                            alive = True
                            total = total + 1
                    if alive:
                        time.sleep(.5)
                        time_spent = time_spent + .5
                        if time_spent >= TIMEOUT + (total * 5):
                            time_spent = 0
                            for p in processes:
                                if p.is_alive():
                                    p.terminate()
                                    p.join()
                            processes = []
                            time.sleep(10)
                            for video in queue:
                                if not os.path.exists(os.path.join(output_directory, slugify(chan.name), slugify(video[0]))):
                                    os.makedirs(os.path.join(output_directory, slugify(chan.name), slugify(video[0])))
                                ydl_opts['outtmpl'] = os.path.join(output_directory, slugify(chan.name), slugify(video[0]), '%(title)s.%(ext)s')
                                p = Process(target=get_video, args=(video[1], ydl_opts))
                                p.start()
                                processes.append(p)
                for video in queue:
                    if not os.path.exists(os.path.join(output_directory, slugify(chan.name), slugify(video[0]))):
                        os.makedirs(os.path.join(output_directory, slugify(chan.name), slugify(video[0])))
                    ydl_opts['outtmpl'] = os.path.join(output_directory, slugify(chan.name), slugify(video[0]), '%(title)s.%(ext)s')
                    p = Process(target=get_video, args=(video[1], ydl_opts))
                    p.start()
                    processes.append(p)
                    new = []
                    for item in to_download:
                        if not item in queue:
                            new.append(item)
                    to_download = new
            chan.status = "archived"
            #chan.save()
        elif chan.status == "archived": #we want to check the RSS for new ASMR streams
            pass
        else: #reserved for un-statused channels. Not sure what this will be for.
            pass

if __name__ == "__main__":
    channels = load_channels()
    keywords = load_keywords()
    #output_directory = "E:\Youtube"
    output_directory = "/mnt/thicc/youtube"
    ASMRchive(channels, keywords, output_directory)