from main import Channel, video_downloader, is_live, load_channels, get_meta, limit_list_append, get_vid, get_my_folder
import time
from multiprocessing import Process, Pool
import threading
import requests
import os
import atexit
import urllib
import sys

def get_sequence_id(link):
    start = link.find("m3u8/sq/") + 8
    end = link.find("/", start + 1)
    return int(link[start:end])

def download_batch(links, segID, recording_directory):
    with open(os.path.join(recording_directory, "Segment_" + str(segID) + ".ts"), "wb") as outfile:
        for link in links:
            try:
                with urllib.request.urlopen(link) as response:
                    outfile.write(response.read())
            except Exception as e:
                print(str(e))
                return False
    return True


def download_manifest(recording_directory, threads=10):
    man_path = os.path.join(recording_directory, "recording.manifest")
    with open(man_path, "r") as manifest_file:
        lines = manifest_file.read().splitlines()
    segID = 0
    section_length = int(len(lines) / (threads-1)) #minus 1 accounts for any potential rounding
    section_end = section_length
    index = 0
    sections = {}
    while index < len(lines):
        if not segID in sections:
            sections[segID] = []
        sections[segID].append(lines[index])
        index += 1
        if index > section_end:
            segID += 1
            section_end = section_end + section_length
    thread_list = []
    for sectionID in sections:
        thread_list.append(threading.Thread(target=download_batch, args=(sections[sectionID], sectionID, recording_directory)))
    for thread in thread_list:
        thread.start()
    for thread in thread_list:
        thread.join()
    index = 0
    with open(os.path.join(recording_directory, "final.ts"), "wb") as outfile:
        while index < len(thread_list):
            with open(os.path.join(recording_directory, "Segment_" + str(index) + ".ts"), "rb") as segment_file:
                outfile.write(segment_file.read())
            index += 1
    for file in os.listdir(recording_directory):
        if "Segment_" in file:
            os.remove(os.path.join(recording_directory, file))


def get_manifest(man_url):
    return requests.get(man_url).text

def get_stream(manifest):
    resolutions = {}
    capture = False
    resolution = -1
    for line in manifest.splitlines():
        if "RESOLUTION" in line:
            start = line.find("RESOLUTION=") + 11
            end = line.find("x", start)
            resolution = int(line[start:end])
            capture = True
        elif capture:
            capture = False
            resolutions[resolution] = line
    max = 0
    for resolution in resolutions:
        if resolution > max:
            max = resolution
    return resolutions[max]

def get_links_from_manifest(manifest):
    links = []
    for line in manifest.splitlines():
        if "http" in line:
            links.append(line)
    return links


def refresh_stream_url(video_url):
    meta = get_meta(video_url)
    if "Exception" in meta:
        return False
    if not "manifest_url" in meta:
        return False
    manifest = get_manifest(meta["manifest_url"])
    return get_stream(manifest)

def get_target_duration(streamtext):
    start = streamtext.find("TARGETDURATION:") + 15
    end = streamtext.find("\n", start)
    return int(streamtext[start:end])

def record_live(video_url, stream_life=100):
    stream_url = refresh_stream_url(video_url)
    if stream_url == False:
        return False #the video isn't live
    segment_duration = get_target_duration(get_manifest(stream_url))
    last_links = []
    segment_urls = []
    no_link_count = 0
    loops = 0
    while True:
        loops += 1
        if loops > stream_life:
            loops = 0
            stream_url = refresh_stream_url(video_url)
        links = get_links_from_manifest(get_manifest(stream_url))
        added = False
        for link in links:
            if not link in last_links:
                added = True
                last_links = limit_list_append(last_links, link, 20)
                segment_urls.append(link)
        if not added:
            no_link_count += 1
        else:
            no_link_count = 0
            time.sleep(segment_duration)
        if no_link_count > 1:
            if is_live(get_meta(video_url)):
                stream_url = refresh_stream_url(video_url)
                if stream_url == False:
                    break
                loops = 0
            else:
                break
    return segment_urls
    
    
def check_urls(url_list):
    segments = {}
    start_sequence = 0
    end_sequence = 0
    missing_segments = list()
    for link in url_list:
        sequence_id = get_sequence_id(link)
        if start_sequence == 0:
            start_sequence = sequence_id
        if sequence_id < start_sequence:
            start_sequence = sequence_id
        if not sequence_id in segments:
            segments[sequence_id] = link
        if sequence_id > end_sequence:
            end_sequence = sequence_id
    output = list()
    index = start_sequence
    while index <= end_sequence:
        if index in segments:
            output.append(segments[index])
        else:
            missing_segments.append[index]
        index += 1
    return output, missing_segments

def get_time_from_exception(exception):
    if "minutes" in exception:
        multiplier = 60
        end = exception.find("minutes")
    elif "hours" in exception:
        multiplier = 60 * 60
        end = exception.find("hours")
    else:
        return False
    start = exception.find("begin in") + 8
    return int(exception[start:end]) * multiplier
    
def setup_recording(url, output_directory):
    vid = get_vid(url)
    output_directory = os.path.join(output_directory, vid)
    if not os.path.isdir(output_directory):
        os.makedirs(output_directory)
    meta = get_meta(url)
    while not is_live(meta) and "live event" in meta:
        if (("live event" in meta or "Premieres" in meta) and not "a few moments" in meta):
            wait_time = get_time_from_exception(meta)
            time.sleep(wait_time - 30)
        elif "live event" in meta and "a few moments" in meta:
            time.sleep(5)
        else:
            with open("./playlog.txt", "w") as playlog:
                playlog.write(str(meta))
        meta = get_meta(url)
    #once we're live, grab the goods!
    if not is_live(meta):
        return False
    with open(os.path.join(output_directory, "asmr.description"), "w") as outfile:
        outfile.write(meta["description"])
    with open(os.path.join(output_directory, "title.txt"), "w") as outfile:
        outfile.write(meta["title"])
    with open(os.path.join(output_directory, "upload_date.txt"), "w") as outfile:
        outfile.write(meta["upload_date"])
    return True


def closing(recording_channel):
    if recording_channel != None:
        if "waiting" in recording_channel.status:
            recording_channel.status = "errored"
        if "recording" in recording_channel.status:
            recording_channel.status = "errored"

def set_channel_status(chan, status):
    if not chan == None:
        chan.status = status
        chan.save()

if __name__ == "__main__": #while we test the above funciton
    output_directory = os.path.join(get_my_folder(), "recordings")
    args = sys.argv
    url = args[1]
    method = args[2]
    channels = load_channels(output_directory)
    recording_channel = None
    try:
        channel_name = args[3]
    except:
        channel_name = None
    if not channel_name == None:
        for chan in channels:
            if chan.name == channel_name:
                recording_channel = chan
                break
    if recording_channel == None:
        breaking = False
        for chan in channels: 
            rss = chan.get_rss()
            for video in rss:
                if url in video["link"]:
                    recording_channel = chan
                    breaking = True
                    break
            if breaking:
                break
    atexit.register(closing, recording_channel)
    if method == "record" or method == "both":
        set_channel_status(recording_channel, "waiting")
        if setup_recording(url, output_directory):
            set_channel_status(recording_channel, "recording")
            links = record_live(url)
            links, missing_segments = check_urls(links)
            with open(os.path.join(output_directory, get_vid(url), "recording.manifest"), "w") as outfile:
                for link in links:
                    outfile.write(link + "\n")
            set_channel_status(recording_channel, "recorded")
        else:
            set_channel_status(recording_channel, "archived")
    if method == "save" or method == "both":
        try:
            download_manifest(os.path.join(output_directory, get_vid(url)))
            set_channel_status(recording_channel, "saved")
        except:
            if recording_channel != None:
                set_channel_status(recording_channel, "errored")
