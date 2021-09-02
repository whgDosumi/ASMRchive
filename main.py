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










def ASMRchive(channels: dict, keywords: list):


if __name__ == "__main__":
    channels = {} #Alias: [channelID, All videos PLID]
    channels["Okayu Ch."] = ["UCvaTdHTWBGv3MKj3KVqJVCw", "UUvaTdHTWBGv3MKj3KVqJVCw"]

    keywords = ["asmr", "binaural", "ku100", "3dio"] #relevant keywords to scan titles for
    ASMRchive(channels, keywords)