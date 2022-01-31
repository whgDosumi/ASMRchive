import time
from multiprocessing import Process, Pool
import multiprocessing
import notify_run
import yt_dlp
import requests
import os
import feedparser
import unicodedata
import re
import sys
import datetime
import shutil
import json
import subprocess

meta_dict = {}

#Used to convert webm files to mp3s
def convert_library(asmr_directory, threads=4):
    bad_formats = ["webm", "opus", "m4a", "flac", "aac"]
    convert_to = "mp3"

    def convert(root, file, convert_to):
        ff_input = os.path.join(root, file)
        ff_output = os.path.join(root, "asmr." + str(convert_to))
        command = ("ffmpeg -y -i " + ff_input + " " + ff_output)
        os.system(command)


    processes = []
    converted_dirs = []
    activity_log = []
    for root, dirs, files in os.walk(asmr_directory):
        for dir in dirs:
            while len(processes) >= threads:
                new = []
                for p in processes:
                    p.join(timeout=.1)
                    if p.is_alive():
                        new.append(p)
                processes = new
            dir_path = os.path.join(root, dir)
            ldir = os.listdir(dir_path)
            for format in bad_formats:
                if ("asmr." + format in ldir) and not (dir_path in converted_dirs) and not (("asmr." + convert_to) in ldir):
                    converted_dirs.append(dir_path)
                    activity_log.append([dir_path,format,convert_to])
                    p = Process(target=convert, args=(dir_path, "asmr." + format, convert_to))
                    p.start()
                    processes.append(p)

def get_my_folder():
    return os.path.dirname(os.path.realpath(__file__))

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
    global meta_dict
    if url in meta_dict:
        return meta_dict[url]
    try:
        ydl_opts = {
            'nocheckcertificate': True,
            'quiet': True,
            'no_warnings': True,
            'no_progress': True,
        }
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            print("ytdl query getMeta " + url)
            meta = ydl.extract_info(url, download=False)
            meta_dict[url] = meta
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

class History_Entry(): #Want to start keeping record of added videos in history page.
    def __init__(self, path, title):
        channel_path = path[:path.rfind("/")]
        self.channel_path = "ASMR" + channel_path[channel_path.rfind("/"):]
        self.title = title
        self.added_string = datetime.datetime.now().strftime("%m/%d/%Y")
        self.link = self.channel_path + path[path.rfind("/"):] + "/player.php"
        self.json_string = json.dumps([self.added_string, self.title, self.channel_path, self.link])
    
    def save(self, path_to_history_file):
        current_history = ""
        if os.path.exists(path_to_history_file):
            with open(path_to_history_file, "r") as read_file:
                current_history = read_file.read()
        with open(path_to_history_file, "w") as write_file:
            write_file.write(self.json_string + "\n" + current_history)

class History_Entry_Channel():
    def __init__(self, path, channel_name):
        self.channel_path = "ASMR" + path[path.rfind("/"):]
        self.channel_name = channel_name
        self.added_string = datetime.datetime.now().strftime("%m/%d/%Y")
        self.json_string = json.dumps([self.added_string, self.channel_name, self.channel_path, self.channel_path + "/index.php"])
    
    def save(self, path_to_history_file):
        current_history = ""
        if os.path.exists(path_to_history_file):
            with open(path_to_history_file, "r") as read_file:
                current_history = read_file.read()
        with open(path_to_history_file, "w") as write_file:
            write_file.write(self.json_string + "\n" + current_history)

class Channel():
    def __init__(self, name: str, channel_id: str, status: str, output_directory: str, reqs=[]):
        self.name = name
        self.carried_reqs = []
        self.channel_id = channel_id
        self.status = status
        self.path = os.path.join(output_directory, slugify(self.name))
        self.reqs = []
        for video in reqs:
            if is_live(get_meta(video)):
                self.carried_reqs.append(video)
            else:
                self.reqs.append(video)
        self.active_recordings = []
    
    def setup(self):
        if not os.path.exists(self.path):
            os.makedirs(self.path)
            os.chmod(self.path, 0o777)
        with open(os.path.join(self.path, "name.txt"), "w") as name_file:
            name_file.write(self.name)
        shutil.copy("/var/www/html/channel_index.php", os.path.join(self.path, "index.php"))
        try:
            with open(os.path.join(self.path, "pfp.png"), "wb") as image_file:
                image_file.write(requests.get(get_pfp("https://www.youtube.com/channel/" + self.channel_id), stream=True).raw.data)
        except Exception as e:
            shutil.copyfile("default-pfp.png", os.path.join(self.path, "pfp.png"))
        

    def save(self):
        if not os.path.isdir(os.path.join(get_my_folder(), "channels")):
            os.makedirs(os.path.join(get_my_folder(), "channels"))
        append = ""
        for video in self.carried_reqs:
            append = append + "\n" + video
        with open(os.path.join(get_my_folder(), "channels", slugify(self.name) + ".channel"), "w") as outfile:
            outfile.write(self.name + "\n" + self.channel_id + "\n" + self.status + append)
            
    def get_rss(self): #returns channel's RSS feed as a list of entries
        return feedparser.parse(requests.get("https://www.youtube.com/feeds/videos.xml?channel_id=" + self.channel_id).text).entries

    def get_all_plist(self):
        return "https://www.youtube.com/channel/" + self.channel_id + "/videos"

    def get_channel_url(self):
        return "https://www.youtube.com/channel/" + self.channel_id

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
            if (d["speed"] <= 100000) and (int(d["elapsed"]) >= 5) and (int(d["eta"]) >= 80): 
                if not self.bypass_slowness:
                    raise NameError('slow af')
        except Exception as e:
            if "slow af" in str(e):
                raise e
            pass

    def downloader(self, id, return_dict):
        try:
            with yt_dlp.YoutubeDL(self.ydl_opts) as ydl:
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
                history_entry = History_Entry(self.path, data["title"])
                return_dict[id] = [self.id, "Finished", history_entry]
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
    channel_dict = os.path.join(get_my_folder(), "channels")
    for data_file in os.listdir(channel_dict):
        with open(os.path.join(channel_dict, data_file), "r") as file:
            lines = file.read().splitlines()
            if len(lines) > 3:
                reqs = lines[3:len(lines)]
                new = []
                for i in reqs:
                    if re.search(r"youtube\.com/watch", i): #if it's a standard youtube url
                        new.append(i[-11:])
                    elif re.match(r"^.{11}$", i): #if the string is 11 characters long
                        new.append(i)
                reqs = new
                chan = Channel(lines[0], lines[1], lines[2], output_directory, reqs=reqs)
                channels.append(chan)
                chan.save()
            else:
                channels.append(Channel(lines[0], lines[1], lines[2], output_directory))
    return channels

def load_keywords():
    keywords = list()
    with open(os.path.join(get_my_folder(), "keywords.txt"), "r", encoding="UTF-8") as key_file:
        for line in key_file.read().splitlines():
            keywords.append(line)
    return keywords

def get_video(url, ydl_opts):
    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
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
        with yt_dlp.YoutubeDL(ydl_opts) as ydl: #This one gets the playlists
            meta = ydl.extract_info(url, download=False)
        return [True,meta]
    except Exception as e:
        return [False,e]

def get_vid(url):
    return url[url.find("?v=") + 3:]



#The download_batch function is used to download many videos at the same time

#to_download is a list, the first element is the desired title and the second is the url
#[string title, string url] basically

#ydl_opts is the settings passed to yt_dlp, check out their documentation on this:
#https://github.com/yt-dlp/yt-dlp/blob/master/yt_dlp/YoutubeDL.py#L189-L487

#channel_path is the real path to the channel directory, basically where we're saving the files

#Optional limit is how many will download at the same time.

#Optional max_retries is how many times it will try again in the event of an error. 

#Optional save_history determines whether it saves the history objects for each video. True by default

def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1, save_history=True):
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
            if not is_live(get_meta(video[1])):
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
                path = os.path.join(channel_path, get_vid(video[1]))
                if not os.path.exists(path):
                    os.makedirs(path)
                else: #if it exists, and we need the video, we need to purge any 'bad data' here.
                    shutil.rmtree(path)
                    os.makedirs(path) #and then re-create the directory fresh and clean.
                ydl_opts['outtmpl'] = os.path.join(path, 'asmr.%(ext)s')
                d = video_downloader(video[1], ydl_opts, path, id, return_dict, bypass_slowness)
                d.start_download()
                with open(os.path.join(path, "title.txt"), "w", encoding="UTF-8") as title:
                    title.write(video[0])
                processes.append(d)
        finished = []
        for p in processes:
            p.wait()
        for p in processes:
            for root, dirs, files in os.walk(p.path):
                for dir in dirs:
                    os.chmod(os.path.join(root, dir), 0o777)
                for file in files:
                    os.chmod(os.path.join(root, file), 0o666)
            os.chmod(p.path, 0o777)
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
                if save_history:
                    returns[p.id][2].save(history_path) #saves history to history json. 
            elif status == "Ignore":
                if video[1] in slow_queue:
                    slow_queue[video[1]] += 1
                else:
                    slow_queue[video[1]] = 1
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
                if not is_live(get_meta(d[1])):
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
    pass
    #subprocess.Popen(args_list, shell=False, stdin=None, stdout=None, stderr=None)

def get_meta_cookie(link, cookie_dir=(os.path.join(get_my_folder(), "cookies"))):
    for cookie_file in os.listdir(cookie_dir):
        try:
            ydl_opts = {
                'nocheckcertificate': True,
                'quiet': True,
                'no_warnings': True,
                'no_progress': True,
                'cookiefile': os.path.join(cookie_dir, cookie_file)
            }
            with yt_dlp.YoutubeDL(ydl_opts) as ydl:
                print("ytdl query getMeta w/ cookie.")
                meta = ydl.extract_info(link, download=False)
            return meta
        except Exception as e:
            pass
    return "Exception: No cookies in cookie directory"

def ASMRchive(channels: list, keywords: list, output_directory: str):
    for chan in channels:
        if chan.status == "archived": #we want to check the RSS for new ASMR streams
            chan.status = "downloading"
            chan.save()
            rss = chan.get_rss()
            saved = chan.get_saved_videos(output_directory)
            to_download = []
            for video in chan.reqs:
                meta = get_meta(video)
                if not "Exception" in meta and not is_live(meta):
                    to_download.append([meta["title"], video])
                if "Exception" in meta:
                    cookie_exception_flags = ["inappropriate", "sign in", "age", "member"]
                    for flag in cookie_exception_flags:
                        if flag in meta and not "live event" in meta:
                            meta = get_meta_cookie(video)
                            if not "Exception" in meta:
                                to_download.append([meta["title"], video])
                    if "Exception: No cookies" in meta:
                        continue # Might want to inform the user that their request failed here. 
                    if "live event" in meta: # A live event, we want to start the recorder. 
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
            chan.status = "archived"
            chan.save()
        elif chan.status == "new": #we want to do a full archive of all videos
            chan.status = "downloading"
            chan.save()
            saved = chan.get_saved_videos(output_directory)
            to_download = list()
            downloaded = list()
            chan.setup()
            ydl_opts = {
                'nocheckcertificate': True,
                'ignoreerrors': True,
                'extract_flat': "in_playlist",
            }
            with yt_dlp.YoutubeDL(ydl_opts) as ydl: #This one gets the playlists
                meta = ydl.extract_info(chan.get_all_plist(), download=False)
            ydl_opts = {
                'nocheckcertificate': True,
                'ignoreerrors': True,
                'extract_flat': True,
            }
            for item in meta["entries"]:
                if item["title"] == "Uploads":
                    with yt_dlp.YoutubeDL(ydl_opts) as ydl: #This one gets the videos from the 'uploads' playlist (all uploads hopefully.)
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
            History_Entry_Channel(os.path.join(output_directory, slugify(chan.name)), chan.name).save(history_path)
            if len(to_download) >= 1:
                download_results = download_batch(to_download, ydl_opts, os.path.join(output_directory, slugify(chan.name)), max_retries=3, save_history=False)
                for success in download_results["successes"]:
                    downloaded.append(success)
                for failure in download_results["failures"]:
                    log("Failure: " + str(failure))
            if not os.path.exists(os.path.join(output_directory, slugify(chan.name))):
                os.makedirs(os.path.join(output_directory, slugify(chan.name)))
                os.chmod(os.path.join(output_directory, slugify(chan.name))) #0o777
            with open(os.path.join(output_directory, slugify(chan.name), "saved_urls.txt"), "a") as saved_doc:
                for item in downloaded:
                    saved_doc.write(item + "\n")
            chan.status = "archived"
            chan.save()
        elif chan.status == "errored":
            chan.status = "archived" #I mean, for now I guess this is fine.
            chan.save()
        elif chan.status == "recording":
            pass #don't really need to do anything about it here. Leave it be.
        elif chan.status == "recorded":
            
            pass #TODO - try to pull video the normal way. If we can't get the video to download properly within say 3 hours of the stream ending, download the recording and use that.
        else: #reserved for un-statused channels. Not sure what this will be for. Need a 'recording' status later for recording channels
            pass



if __name__ == "__main__":
    output_directory = "/var/ASMRchive"
    args = sys.argv
    global history_path
    history_path = os.path.join(output_directory, "history.json")
    testing_new = False
    testing_rss = False
    rewrite_dates = False
    channels = load_channels(output_directory)
    if rewrite_dates:
        for chan in channels:
            for root, dirs, files in os.walk(chan.path):
                for file in files:
                    if "asmr.info.json" in file:
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
    if not "bypass_convert" in args:
        convert_library(output_directory)
