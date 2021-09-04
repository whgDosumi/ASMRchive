"""
Plans:
First: make it scan an entire channel and archive all of its ASMR videos using youtube_dl
Then, after it does that, make it scan the RSS and see if there are any new ASMR videos to download periodically.

Eventually, I want it to check for live ASMR and record them until a full archive can be obtained with the original method. That way, if
it's removed, we can still keep the recording. 

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
        return "https://www.youtube.com/channel/" + self.channel_id + "/videos"

    def get_saved_videos(self, output_directory: str):
        saved_videos = []
        if os.path.exists(os.path.join(output_directory, slugify(self.name), "saved_urls.txt")):
            with open(os.path.join(output_directory, slugify(self.name), "saved_urls.txt"), "r") as saved_doc:
                for video in saved_doc.read().splitlines():
                    saved_videos.append(video)
        return saved_videos

class video_downloader():
    def __init__(self, url, ydl_opts) -> None:
        self.url = url
        self.ydl_opts = ydl_opts
        self.error = None
        self.process = Process(target=self.downloader, args=(self,))
        
    def downloader(self, _) -> None:
        try:
            with youtube_dl.YoutubeDL(self.ydl_opts) as ydl:
                ydl.download((self.url,))
        except Exception as e:
            self.error = e

    def start_download(self) -> None:
        self.process.start()
    
    def alive(self) -> bool:
        return self.process.is_alive()
    
    def kill(self):
        try:
            self.process.terminate()
            self.process.join()
            self.process = Process(target=self.downloader, args=(self,))
            self.error = None
            return True
        except:
            self.process = Process(target=self.downloader, args=(self,))
            self.error = None
            return False

    def wait(self):
        self.process.join()

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

def get_video_info(url):
    try:
        ydl_opts = {
            'nocheckcertificate': True,
        }
        with youtube_dl.YoutubeDL(ydl_opts) as ydl: #This one gets the playlists
            meta = ydl.extract_info(url, download=False)
        return [True,meta]
    except Exception as e:
        return [False,e]

def ASMRchive(channels: list, keywords: list, output_directory: str):
    for chan in channels:
        if chan.status == "archived": #we want to check the RSS for new ASMR streams
            rss = chan.get_rss()
            saved = chan.get_saved_videos(output_directory)
            to_download = []
            for video in rss:
                if not video["link"] in saved:
                    for word in keywords:
                        if word.lower() in video["title"].lower():
                            to_download.append(video)
            downloaders = []
            for video in to_download:
                meta = get_video_info(video["link"])
                if meta[0]: #If the video returned proper metadata
                    meta = meta[1]
                    ydl_opts = {
                        'nocheckcertificate': True,
                        'writethumbnail': True,
                        'format': "bestaudio/best",
                        "writedescription": True,
                        "writeinfojson": True,
                        "outtmpl": os.path.join(output_directory, slugify(chan.name), slugify(meta["title"]), '%(title)s.%(ext)s')
                    }
                    downloaders.append(video_downloader(meta["webpage_url"], ydl_opts))
                else:
                    meta = meta[1]
                    meta = str(meta).lower()
                    if "live event" in meta:
                        if "hours" in meta or "days" in meta:
                            pass #we don't really need to take action on a video starting in > 1hr from now
                        else:
                            pass # spawn a recorder!!! This will be late-game for this program.
            for downloader in downloaders:
                downloader.start_download()
            alive = True
            finished = []
            TIMEOUT = 25
            time_spent = 0
            while alive:
                alive = False
                count = 0
                for downloader in downloaders:
                    if downloader.alive():
                        count = count + 1
                        alive = True
                    else:
                        finished.append(downloader)
                    time.sleep(.5)
                    time_spent = time_spent + .5
                    if time_spent >= TIMEOUT + (5 * count):
                        time_spent = 0
                        for downloader in downloaders:
                            if downloader.alive():
                                downloader.kill()
                                time.sleep(2.5)
                                downloader.start_download()
            with open(os.path.join(output_directory, slugify(chan.name), "saved_urls.txt"), "a") as saved_doc:
                for item in finished:
                    saved_doc.write(item.url + "\n")
                    
        elif chan.status == "new": #we want to do a full archive of all videos
            saved = chan.get_saved_videos(output_directory)
            to_download = list()
            downloaded = list()
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
                    if not ("https://www.youtube.com/watch?v=" + video["id"]) in to_download and not ("https://www.youtube.com/watch?v=" + video["id"]) in saved:
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
                    d = video_downloader(video[1], ydl_opts)
                    d.start_download()
                    processes.append(d)
                TIMEOUT = 25
                time_spent = 0
                alive = True
                finished = []
                errored = []
                while alive:
                    alive = False
                    total = 0
                    for p in processes:
                        if p.alive():
                            alive = True
                            total = total + 1
                        else:
                            if p.error == None:
                                finished.append(p.url)
                            else:
                                p.kill()
                                errored.append[p]
                    if alive:
                        time.sleep(.5)
                        time_spent = time_spent + .5
                        if time_spent >= TIMEOUT + (total * 5):
                            time_spent = 0
                            for p in processes:
                                if p.alive():
                                    p.kill()
                            new = []
                            for d in processes:
                                if not d.url in finished:
                                    new.append(d)
                            processes = new
                            time.sleep(5)
                            for d in processes:
                                d.start_download()
                new = []

                for d in errored:
                    d.start_download()
                for d in errored:
                    d.wait()
                for d in errored:
                    if not d.error == None:
                        log("ERROR on " + d.url + " - " + str(d.error))
                    else:
                        finished.append(d.url)

                for item in to_download:
                    if not item in queue:
                        new.append(item)
                    else:
                        if item[1] in finished: #otherwise it's an error
                            downloaded.append(item)
                to_download = new
            if not os.path.exists(os.path.join(output_directory, slugify(chan.name))):
                os.makedirs(os.path.join(output_directory, slugify(chan.name)))
            with open(os.path.join(output_directory, slugify(chan.name), "saved_urls.txt"), "a") as saved_doc:
                for item in downloaded:
                    saved_doc.write(item[1] + "\n")
            chan.status = "archived"
            chan.save()
        else: #reserved for un-statused channels. Not sure what this will be for. Need a 'recording' status later for recording channels
            pass

if __name__ == "__main__":
    testing_new = False
    testing_rss = False
    channels = load_channels()
    if testing_new:
        for chan in channels:
            chan.status = "new"
    if testing_rss:
        for chan in channels:
            chan.status = "archived"
    keywords = load_keywords()
    output_directory = "/mnt/thicc/ASMRchive"
    ASMRchive(channels, keywords, output_directory)