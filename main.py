import time
from multiprocessing import Process, Pool
import multiprocessing
import notify_run
import youtube_dl
import yt_dlp
import requests
import os
import feedparser
import unicodedata
import re
import datetime
import shutil
import json
import subprocess
from subprocess import PIPE

youtube_dl = yt_dlp

# adds item to front of list while maintaining len<limiter
def limit_list_append(collection, itemToAdd, limiter):
    newList = list()
    newList.append(itemToAdd)
    for item in collection:
        if len(newList) < limiter:
            newList.append(item)
        else:
            break
    return newList

def read_json(file_path):
    with open(file_path, "r") as file:
        return json.load(file)

def get_meta(url):
    try:
        ydl_opts = {
            'nocheckcertificate': True,
            'quiet': True,
            'no_warnings': True,
            'no_progress': True,
        }
        with youtube_dl.YoutubeDL(ydl_opts) as ydl:
            print("ytdl query getMeta")
            meta = ydl.extract_info(url, download=False)
        return meta
    except Exception as e:
        print("Exception in getMeta: " + str(e))
        return ("Exception: " + str(e))

def is_live(meta):
    if "Exception" in meta:
        return False
    try:
        live = meta["is_live"]
        if live == None:
            return False
        else:
            return live
    except Exception as e:
        print("Exception at isLive: " + str(e))
        return False

def get_length(audio_path):
    command = "ffmpeg -i '" + audio_path + "' 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//"
    time = str(subprocess.check_output(command, shell=True))
    time = time[time.find("\'") + 1: time.find("\\")]
    if "." in time:
        time = time[:time.find(".")]
    return time

def get_pfp(yt_url):
    webpage = requests.get(yt_url).text
    start = webpage.find("\"avatar\":")
    good = False
    while not good:
        init_start = webpage.find("s176", start + 1)
        start = init_start
        end = webpage.find("\"", start)
        start = webpage.rfind("\"", start-200, start) + 1
        if start == end:
            return False
        url = webpage[start:end]
        url = url.replace("s176", "s176")
        try:
            requests.get(url)
            good = True
            return url
        except:
            if init_start == -1:
                return False
                good = True
            start = init_start + 1

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
    def __init__(self, name: str, channel_id: str, status: str, output_directory: str, reqs=[]):
        self.name = name
        self.channel_id = channel_id
        self.status = status
        self.path = os.path.join(output_directory, slugify(self.name))
        self.reqs = reqs
    
    def setup(self):
        if not os.path.exists(self.path):
            os.makedirs(self.path)
        with open(os.path.join(self.path, "name.txt"), "w") as name_file:
            name_file.write(self.name)
        shutil.copy("/var/www/html/channel_index.php", os.path.join(self.path, "index.php"))
        try:
            with open(os.path.join(self.path, "pfp.png"), "wb") as image_file:
                image_file.write(requests.get(get_pfp("https://www.youtube.com/channel/" + self.channel_id), stream=True).raw.data)
        except Exception as e:
            shutil.copyfile("default-pfp.png", os.path.join(self.path, "pfp.png"))
        

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
    def __init__(self, url, ydl_opts, path, id, return_dict, bypass_slowness) -> None:
        self.url = url
        self.ydl_opts = ydl_opts
        self.ydl_opts['progress_hooks'] = [self.my_hook,]
        self.error = None
        self.return_dict = return_dict
        self.id = id
        self.process = Process(target=self.downloader, args=(self.id, self.return_dict))
        self.path = path
        self.bypass_slowness = bypass_slowness

        

    def my_hook(self, d):
        try:
            if (d["speed"] <= 1000000) and (int(d["elapsed"]) >= 5) and (int(d["eta"]) >= 80): 
                if not self.bypass_slowness:
                    raise NameError('slow af')
        except Exception as e:
            if "slow af" in str(e):
                raise e
            pass

    def downloader(self, id, return_dict):
        try:
            with youtube_dl.YoutubeDL(self.ydl_opts) as ydl:
                ydl.download((self.url,))
                data = read_json(os.path.join(self.path, "asmr.info.json"))
                with open(os.path.join(self.path, "upload_date.txt"), "w") as upload_date_file:
                    upload_date_file.write(data["upload_date"])
                with open(os.path.join(self.path, "runtime.txt"), "w") as runtime_file:
                    if os.path.exists(os.path.join(self.path, "asmr.webm")):
                        runtime_file.write(get_length(os.path.join(self.path, "asmr.webm")))
                    else:
                        runtime_file.write(get_length(os.path.join(self.path, "asmr.m4a")))
                shutil.copy("/var/www/html/player.php", os.path.join(self.path, "player.php"))
                return_dict[id] = [self.id, "Finished"]
        except Exception as e:
            cookie_exception_flags = ["inappropriate", "sign in", "age", "member"]
            if "slow af" in str(e):
                return_dict[id] = [self.id, "Ignore"]
            elif "403: Forbidden" in str(e):
                return_dict[id] = [self.id, str(e)]
            elif "unable to rename file" in str(e):
                return_dict[id] = [self.id, str(e)]
            else:
                for flag in cookie_exception_flags:
                    if flag in str(e).lower():
                        return_dict[id] = [self.id, "Cookie Please"]
                        exit()
                return_dict[id] = [self.id, str(e)]
            exit()

    def start_download(self):
        self.process.start()
    
    def alive(self) -> bool:
        return self.process.is_alive()
    
    def kill(self):
        if self.process.is_alive():
            self.process.terminate()
            self.process.join()

    def wait(self):
        if self.process.is_alive():
            self.process.join()

def load_channels(output_directory: str):
    channels = list()
    for data_file in os.listdir("channels"):
        with open(os.path.join("channels", data_file), "r") as file:
            lines = file.read().splitlines()
            if len(lines) > 3:
                reqs = lines[3:len(lines)]
                channels.append(channel(lines[0], lines[1], lines[2], output_directory, reqs=reqs))
            else:
                channels.append(channel(lines[0], lines[1], lines[2], output_directory))
        if len(lines) > 3:
            with open(os.path.join("channels", data_file), "w") as file:
                index = 0
                while index < 3:
                    if not index == 2:
                        file.write(lines[index] + "\n")
                    else:
                        file.write(lines[index])
                    index += 1
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

def get_vid(url):
    return url[url.find("?v=") + 3:]

def get_my_folder():
    return os.path.dirname(os.path.realpath(__file__))

def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1):
    errored = {}
    error_count = {}
    function_output = {}
    function_output["successes"] = []
    function_output["failures"] = []
    queue_manager = multiprocessing.Manager()
    ydl_opts["cookiefile"] = None
    cookies = None
    cookie_queue = {} #key is url and value is list of tried cookies.
    slow_queue = {}
    while len(to_download) >= 1:
        queue = to_download[0:limit]
        id = -1
        return_dict = queue_manager.dict()
        processes = []
        bypass_slowness = False
        for video in queue:
            if video[1] in cookie_queue:
                for cookiefile in cookies:
                    if not cookiefile in cookie_queue[video[1]]:
                        cookie_queue[video[1]].append(cookiefile)
                        ydl_opts["cookiefile"] = cookiefile
                        break
                if ydl_opts["cookiefile"] == None:
                    print("Cookies attempted but failed. len: " + len(cookie_queue[video[1]]))
                    purge[p.url] = "failure"

            if video[1] in slow_queue:
                if slow_queue[video[1]] > 5:
                    print("BYPASS SLOWNESS IS TRUE")
                    bypass_slowness = True
            id = id + 1
            path = os.path.join(channel_path, slugify(video[0]) + "-" + get_vid(video[1]))
            if not os.path.exists(path):
                os.makedirs(path)
            else: #if it exists, and we need the video, we need to purge any 'bad data' here.
                shutil.rmtree(path)
                os.makedirs(path) #and then re-create the directory fresh and clean.
            ydl_opts['outtmpl'] = os.path.join(path, 'asmr.%(ext)s')
            d = video_downloader(video[1], ydl_opts, path, id, return_dict, bypass_slowness)
            d.start_download()
            with open(os.path.join(path, "title.txt"), "w", encoding="UTF-8") as title:
                title.write(video[0]) #this may be replaced later (hopefully)
            processes.append(d)
        finished = []
        for p in processes:
            p.wait()

        returns = return_dict.values()
        index = -1
        sorted = []
        while len(sorted) < len(returns): #this sorts the returns for simplicity
            index += 1
            for i in returns:
                if i[0] == index:
                    sorted.append(i)
                    break
        returns = sorted
        sorted = None
        purge = {}
        for p in processes:
            status = returns[p.id][1]
            if status == "Finished":
                purge[p.url] = "success"
            elif status == "Ignore":
                if video[1] in slow_queue:
                    slow_queue[video[1]] += 1
                else:
                    slow_queue[video[1]] = 1
                time.sleep(2)
                pass #Doing nothing puts it in the next queue for re-download
            elif status == "Cookie Please":
                if cookies == None:
                    cookies = []
                    for file in os.listdir(os.path.join(get_my_folder(), "cookies")):
                        if "cookie" in file.lower():
                            cookies.append(os.path.join(get_my_folder(), "cookies", file))
                if not p.url in cookie_queue:
                    cookie_queue[p.url] = []
            elif p.url in errored:
                error_count[p.url] += 1
                if error_count[p.url] > max_retries:
                    purge[p.url] = "failure"
            else: #we got an error
                errored[p.url] = status
                if max_retries > 0:
                    error_count[p.url] = 1
                else:
                    purge[p.url] = "failure"
        purge_urls = [i for i in purge]
        new = []
        for d in to_download:
            if not d[1] in purge_urls:
                new.append(d)
        to_download = new
        for p in purge:
            if purge[p] == "success":
                function_output["successes"].append(p)
            else:
                function_output["failures"].append(p)
                
    queue_manager.shutdown()
    return function_output

def run_shell(args_list):
    succ = subprocess.Popen(args_list, shell=False, stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

def ASMRchive(channels: list, keywords: list, output_directory: str):
    for chan in channels:
        if chan.status == "archived": #we want to check the RSS for new ASMR streams
            rss = chan.get_rss()
            saved = chan.get_saved_videos(output_directory)
            to_download = []
            for video in chan.reqs:
                meta = get_meta(video)
                if not "Exception" in meta and not is_live(meta):
                    to_download.append([meta["title"], video])
                if "Exception" in meta:
                    if "live event" in meta:
                        run_shell(["python", os.path.join(os.path.dirname(os.path.realpath(__file__)), "live.py"), video, "record", "\"" + chan.name + "\""])
                elif is_live(meta):
                    run_shell(["python", os.path.join(os.path.dirname(os.path.realpath(__file__)), "live.py"), video, "record", "\"" + chan.name + "\""])
            for video in rss:
                if not video["link"] in saved:
                    for word in keywords:
                        if word.lower() in video["title"].lower():
                            meta = get_meta(video["link"])
                            if "Exception" in meta:
                                if "live event" in meta:
                                    run_shell(["python", os.path.join(os.path.dirname(os.path.realpath(__file__)), "live.py"), video["link"], "record", "\"" + chan.name + "\""])
                                    break
                                else:
                                    if not is_live(meta):
                                        to_download.append([video["title"], video["link"]])
                                        break
                                    else:
                                        run_shell(["python", os.path.join(os.path.dirname(os.path.realpath(__file__)), "live.py"), video["link"], "record", "\"" + chan.name + "\""])
                                        break
                            else:
                                to_download.append([video["title"], video["link"]])
                                break
            ydl_opts = {
                'nocheckcertificate': True,
                'writethumbnail': True,
                'format': "bestaudio/best",
                "writedescription": True,
                "writeinfojson": True,
            }
            if len(to_download) >=1:
                download_results = download_batch(to_download, ydl_opts, os.path.join(output_directory, slugify(chan.name)), max_retries=3)
                downloaded = []
                for success in download_results["successes"]:
                    downloaded.append(success)
                if not os.path.exists(os.path.join(output_directory, slugify(chan.name))):
                    os.makedirs(os.path.join(output_directory, slugify(chan.name)))
                with open(os.path.join(output_directory, slugify(chan.name), "saved_urls.txt"), "a") as saved_doc:
                    for item in downloaded:
                        saved_doc.write(item + "\n")
        elif chan.status == "new": #we want to do a full archive of all videos
            saved = chan.get_saved_videos(output_directory)
            to_download = list()
            downloaded = list()
            chan.setup()
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
            if len(to_download) >= 1:
                download_results = download_batch(to_download, ydl_opts, os.path.join(output_directory, slugify(chan.name)), max_retries=3)
                for success in download_results["successes"]:
                    downloaded.append(success)
                for failure in download_results["failures"]:
                    log("Failure: " + str(failure))
            if not os.path.exists(os.path.join(output_directory, slugify(chan.name))):
                os.makedirs(os.path.join(output_directory, slugify(chan.name)))
            with open(os.path.join(output_directory, slugify(chan.name), "saved_urls.txt"), "a") as saved_doc:
                for item in downloaded:
                    saved_doc.write(item + "\n")
            chan.status = "archived"
            chan.save()
        else: #reserved for un-statused channels. Not sure what this will be for. Need a 'recording' status later for recording channels
            pass

if __name__ == "__main__":
    output_directory = "/mnt/thicc/ASMRchive"
    testing_new = False
    testing_rss = False
    rewrite_dates = False
    channels = load_channels(output_directory)
    if rewrite_dates:
        for chan in channels:
            for root, dirs, files in os.walk(chan.path):
                for file in files:
                    if ".json" in file:
                        data = read_json(os.path.join(root, file))
                        with open(os.path.join(root, "upload_date.txt"), "w") as upload_date_file:
                            upload_date_file.write(data["upload_date"])
    if testing_new:
        for chan in channels:
            chan.status = "new"
    if testing_rss:
        for chan in channels:
            chan.status = "archived"
    keywords = load_keywords()
    ASMRchive(channels, keywords, output_directory)
