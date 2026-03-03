## 1.13.3 - 2026-03-03
ci: add step to update yt-dlp to pipeline (#166)

Sometimes, due to layer caching yt-dlp is not updated during the build
process. This adds a step to explicity update it before the unit tests.

## 1.13.2 - 2026-03-03
fix: force IPv4 for yt-dlp to resolve container DNS errors (#165)

Added 'source_address': '0.0.0.0' to all ydl_opts definitions in main.py.
This forces yt-dlp to bind to an IPv4 socket, preventing intermittent
IPv6 resolution timeouts and routing hangs that were causing download
failures during integration tests in the container environment.

## 1.13.1 - 2026-03-02
fix: Permission and Download Logic Bug Fixes (#164)

* test: unit tests for get_vid function.

* fix: correct video ID extraction for YouTube Shorts

The get_vid function was incorrectly truncating video IDs from Shorts URLs,
returning only the first character. This change replaces the manual index
offset with a robust regex-based extraction that handles standard, Shorts,
and short-link YouTube formats correctly.

A unit test now exists to make sure the function works as expected.

* refactor: remove slow-queue logic

Remove old code from slow queue, which was a workaround utilized
before migrating to yt-dlp. There has been no need for this code for
years now, so removed it to clean up the program.
diff --git a/python/main.py b/python/main.py
index bc9e68a..523e63e 100644
--- a/python/main.py
+++ b/python/main.py
@@ -268,26 +268,14 @@ class Channel():
         return saved_videos

 class video_downloader():
-    def __init__(self, url, ydl_opts, path, id, return_dict, bypass_slowness) -> None:
+    def __init__(self, url, ydl_opts, path, id, return_dict) -> None:
         self.url = url
         self.ydl_opts = ydl_opts
-        #self.ydl_opts['progress_hooks'] = [self.my_hook,]
         self.error = None
         self.return_dict = return_dict
         self.id = id
         self.process = Process(target=self.downloader, args=(self.id, self.return_dict))
         self.path = path
-        self.bypass_slowness = bypass_slowness
-
-    def my_hook(self, d):
-        try:
-            if (d["speed"] <= 100000) and (int(d["elapsed"]) >= 5) and (int(d["eta"]) >= 80):
-                if not self.bypass_slowness:
-                    raise NameError('slow af')
-        except Exception as e:
-            if "slow af" in str(e):
-                raise e
-            pass

     def downloader(self, id, return_dict):
         try:
@@ -317,9 +305,7 @@ class video_downloader():
                     return_dict[id] = [self.id, "Finished", history_entry]
         except Exception as e:
             cookie_exception_flags = ["inappropriate", "sign in", "age", "member"]
-            if "slow af" in str(e):
-                return_dict[id] = [self.id, "Ignore"]
-            elif "403: Forbidden" in str(e):
+            if "403: Forbidden" in str(e):
                 return_dict[id] = [self.id, str(e)]
             elif "unable to rename file" in str(e):
                 return_dict[id] = [self.id, str(e)]
@@ -461,13 +447,11 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
     ydl_opts["cookiefile"] = None
     cookies = None
     cookie_queue = {} #key is url and value is list of tried cookies.
-    slow_queue = {}
     while len(to_download) >= 1:
         queue = to_download[0:limit]
         id = -1
         return_dict = queue_manager.dict()
         processes = []
-        bypass_slowness = False
         purge = {}
         for video in queue:
             ydl_opts["cookiefile"] = None
@@ -482,10 +466,6 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
                         print("Cookies attempted but failed. len: " + str(len(cookie_queue[video[1]])))
                         purge[video[1]] = "Missing Cookies"
                         continue
-                if video[1] in slow_queue:
-                    if slow_queue[video[1]] > 5:
-                        print("BYPASS SLOWNESS IS TRUE")
-                        bypass_slowness = True
                 id = id + 1
                 path = os.path.join(channel_path, get_vid(video[1]))
                 if not os.path.exists(path):
@@ -515,7 +495,7 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
                         shutil.rmtree(path)
                         os.makedirs(path)
                 ydl_opts['outtmpl'] = os.path.join(path, 'asmr.%(ext)s')
-                d = video_downloader(video[1], ydl_opts, path, id, return_dict, bypass_slowness)
+                d = video_downloader(video[1], ydl_opts, path, id, return_dict)
                 d.start_download()
                 with open(os.path.join(path, "title.txt"), "w", encoding="UTF-8") as title:
                     title.write(video[0])
@@ -553,12 +533,6 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
                 purge[p.url] = "success"
                 if save_history:
                     returns[p.id][2].save(history_path) #saves history to history json.
-            elif status == "Ignore":
-                if video[1] in slow_queue:
-                    slow_queue[video[1]] += 1
-                else:
-                    slow_queue[video[1]] = 1
-                pass #Doing nothing puts it in the next queue for re-download
             elif status == "Cookie Please":
                 if cookies == None:
                     cookies = []

* fix: Fix file permissions

Fixes file permissions, allowing chmod calls to fail silently
and makes .channel files 664 instead of 644.

## 1.13.0 - 2026-03-01
feat: process explicit requests for inactive and new channels (#163)

- Refactor ASMRchive loop to separate request processing from RSS checks.
- Enable video requests (chan.reqs) for inactive and new channel statuses.
- Ensure original channel status is restored after processing requests.
- Prevent duplicate downloads by checking to_download before scraping new channels.

diff --git a/python/main.py b/python/main.py
index 405ae87..f116a70 100644
--- a/python/main.py
+++ b/python/main.py
@@ -626,10 +626,11 @@ def get_meta_cookie(link, cookie_dir=(os.path.join("/var/ASMRchive/.appdata", "c
     return "Exception: No cookies in cookie directory"
 def ASMRchive(channels: list, keywords: list, output_directory: str):
     for chan in channels:
-        if chan.status == "archived": #we want to check the RSS for new ASMR streams
-            rss = chan.get_rss()
-            saved = chan.get_saved_videos(output_directory)
-            to_download = []
+        to_download = []
+        original_status = chan.status
+
+        # Process explicit requests for archived, inactive, and new channels
+        if chan.status in ["archived", "inactive", "new"]:
             for video in chan.reqs:
                 if video in chan.failures and chan.failures[video].get("attempts", 0) >= 5:
                     continue
@@ -662,6 +663,11 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
                 elif is_live(meta):
                     pass
                     #run_shell(["python", os.path.join(os.path.dirname(os.path.realpath(__file__)), "live.py"), video, "record", "\"" + chan.name + "\""])
+
+        # Check RSS feed ONLY for archived channels
+        if chan.status == "archived":
+            rss = chan.get_rss()
+            saved = chan.get_saved_videos(output_directory)
             for video in rss:
                 if not video["link"] in saved:
                     if video["link"] in chan.failures and chan.failures[video["link"]].get("attempts", 0) >= 5:
@@ -683,6 +689,9 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
                             else:
                                 to_download.append([video["title"], video["link"]])
                                 break
+
+        # Execute downloads if we found anything in reqs or RSS
+        if chan.status in ["archived", "inactive"]:
             ydl_opts = {
                 'nocheckcertificate': True,
                 'writethumbnail': True,
@@ -714,13 +723,12 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
                 with open(os.path.join(output_directory, slugify(chan.name), "saved_urls.txt"), "a") as saved_doc:
                     for item in downloaded:
                         saved_doc.write(item + "\n")
-            chan.status = "archived"
-            chan.save()
+                chan.status = original_status # Ensure inactive channels get set back properly!
+                chan.save()
         elif chan.status == "new": #we want to do a full archive of all videos
             chan.status = "downloading"
             chan.save()
             saved = chan.get_saved_videos(output_directory)
-            to_download = list()
             downloaded = list()
             chan.setup()
             ydl_opts = {
@@ -736,24 +744,28 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
                 'extract_flat': True,
             }
             def search_channel(metadata): # Recursively checks through a channels videos for ASMR and queues them for download
-                to_download = []
+                found_downloads = []
                 if metadata["_type"] == "playlist":
                     for entry in metadata["entries"]:
                         data = (search_channel(entry))
                         for item in data:
-                            to_download.append(item)
+                            found_downloads.append(item)
                 else:
                     for word in keywords:
                         vid_url = "https://www.youtube.com/watch?v=" + metadata["id"]
-                        if not vid_url in to_download and not vid_url in saved:
+                        if not vid_url in [x[1] for x in found_downloads] and not vid_url in saved:
                             if vid_url in chan.failures and chan.failures[vid_url].get("attempts", 0) >= 5:
                                 continue
                             if word.lower() in metadata["title"].lower():
-                                to_download.append([metadata["title"] ,vid_url])
+                                found_downloads.append([metadata["title"] ,vid_url])
                                 break
-                return to_download
+                return found_downloads
             if not meta == None:
-                to_download = search_channel(meta)
+                found_videos = search_channel(meta)
+                existing_urls = [vid[1] for vid in to_download]
+                for item in found_videos:
+                    if item[1] not in existing_urls:
+                        to_download.append(item)
             ydl_opts = {
                 'nocheckcertificate': True,
                 'writethumbnail': True,

## 1.12.1 - 2026-02-28
refactor: Rename python_app to python

- Rename python_app folder to python.

## 1.12.0 - 2026-02-28
fix: Handle failed downloads (#161)

* fix: startup script ouputs to wrong log file.

- Fixes an issue where the startup.sh script would push the logs to
the incorrect location.

* test: Update yt-dlp update testing logic

- Mashes the update button instead of hitting it only once.
- Adds more robust logging to check_dlp.py and flag_check.sh

* ci: Move wait logic

- Removes wait logic inside the Jenkins pipeline
- Replaces it by adding it as a python step in the unit tests.

* ci: reorganize tests

- Removed the non-standard build of ASMRchive with wrong version of yt-dlp.
- Re-added the unit test that verifies yt-dlp is up to date
- Added step to pipeline to manually downgrade yt-dlp for integration tests.

* ci: Fix broken stage formatting

* ci: Tell webserver yt-dlp has been downgraded

* fix: Fix potential infinite loop in unit tests.

* fix: handle missing cookies in download loop

Gracefully handles failures when no cookies are present.
diff --git a/python_app/main.py b/python_app/main.py
index b2445c5..dc4bd49 100644
--- a/python_app/main.py
+++ b/python_app/main.py
@@ -453,17 +453,20 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
         return_dict = queue_manager.dict()
         processes = []
         bypass_slowness = False
+        purge = {}
         for video in queue:
+            ydl_opts["cookiefile"] = None
             if not is_live(get_meta(video[1])):
                 if video[1] in cookie_queue:
-                    for cookiefile in cookies:
+                    for cookiefile in (cookies or []):
                         if not cookiefile in cookie_queue[video[1]]:
                             cookie_queue[video[1]].append(cookiefile)
                             ydl_opts["cookiefile"] = cookiefile
                             break
                     if ydl_opts["cookiefile"] == None:
                         print("Cookies attempted but failed. len: " + str(len(cookie_queue[video[1]])))
-                        purge[p.url] = "failure"
+                        purge[video[1]] = "failure"
+                        continue
                 if video[1] in slow_queue:
                     if slow_queue[video[1]] > 5:
                         print("BYPASS SLOWNESS IS TRUE")
@@ -505,7 +508,6 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
                     break
         returns = sorted
         sorted = None
-        purge = {}
         for p in processes:
             status = returns[p.id][1]
             if status == "Finished":
@@ -521,9 +523,11 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
             elif status == "Cookie Please":
                 if cookies == None:
                     cookies = []
-                    for file in os.listdir(os.path.join("/var/ASMRchive/.appdata", "cookies")):
-                        if "cookie" in file.lower():
-                            cookies.append(os.path.join("/var/ASMRchive/.appdata", "cookies", file))
+                    cookie_dir = os.path.join("/var/ASMRchive/.appdata", "cookies")
+                    if os.path.exists(cookie_dir):
+                        for file in os.listdir(cookie_dir):
+                            if "cookie" in file.lower():
+                                cookies.append(os.path.join(cookie_dir, file))
                 if not p.url in cookie_queue:
                     cookie_queue[p.url] = []
             elif p.url in errored:

* feat: Handle download failures

- Add `failures` property to `Channel` class and create `load_failures` and `save_failures` methods.
- Update `download_batch` to return detailed error strings in a dictionary instead of a list of URLs.
- Implement pre-filtering in `ASMRchive` queue loops to skip videos with 5 or more failed attempts.
- Update `failures.json` dynamically by removing successful retries and tracking new/repeated failures with timestamps, error context, and attempt counts.
diff --git a/python_app/main.py b/python_app/main.py
index dc4bd49..b5deeb3 100644
--- a/python_app/main.py
+++ b/python_app/main.py
@@ -203,6 +203,7 @@ class Channel():
         self.status = status
         self.last_updated = int(last_updated)
         self.path = os.path.join(output_directory, slugify(self.name))
+        self.failures = self.load_failures()
         self.reqs = []
         for video in reqs:
             if is_live(get_meta(video)):
@@ -212,6 +213,20 @@ class Channel():
         self.active_recordings = []
         self.url = f"https://www.youtube.com/channel/{self.channel_id}"

+    def load_failures(self):
+        failures_path = os.path.join(self.path, "failures.json")
+        if os.path.exists(failures_path):
+            try:
+                return read_json(failures_path)
+            except:
+                pass
+        return {}
+
+    def save_failures(self):
+        failures_path = os.path.join(self.path, "failures.json")
+        with open(failures_path, "w") as write_file:
+            json.dump(self.failures, write_file, indent=4)
+
     def setup(self):
         if not os.path.exists(self.path):
             os.makedirs(self.path)
@@ -440,7 +455,7 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
     error_count = {}
     function_output = {}
     function_output["successes"] = []
-    function_output["failures"] = []
+    function_output["failures"] = {}
     function_output["bots"] = []
     queue_manager = multiprocessing.Manager()
     ydl_opts["cookiefile"] = None
@@ -465,7 +480,7 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
                             break
                     if ydl_opts["cookiefile"] == None:
                         print("Cookies attempted but failed. len: " + str(len(cookie_queue[video[1]])))
-                        purge[video[1]] = "failure"
+                        purge[video[1]] = "Missing Cookies"
                         continue
                 if video[1] in slow_queue:
                     if slow_queue[video[1]] > 5:
@@ -537,13 +552,13 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
                     if "Sign in to confirm you’re not a bot. This helps protect our community." in returns[p.id][1]:
                         purge[p.url] = "Bot Error"
                     else:
-                        purge[p.url] = "failure"
+                        purge[p.url] = status
             else: #we got an error
                 errored[p.url] = status
                 if max_retries > 0:
                     error_count[p.url] = 1
                 else:
-                    purge[p.url] = "failure"
+                    purge[p.url] = status
         purge_urls = [i for i in purge]
         new = []
         for d in to_download:
@@ -557,7 +572,7 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
             elif purge[p] == "Bot Error":
                 function_output["bots"].append(p)
             else:
-                function_output["failures"].append(p)
+                function_output["failures"][p] = purge[p]
     queue_manager.shutdown()
     return function_output

@@ -582,7 +597,6 @@ def get_meta_cookie(link, cookie_dir=(os.path.join("/var/ASMRchive/.appdata", "c
         except Exception as e:
             pass
     return "Exception: No cookies in cookie directory"
-
 def ASMRchive(channels: list, keywords: list, output_directory: str):
     for chan in channels:
         if chan.status == "archived": #we want to check the RSS for new ASMR streams
@@ -590,6 +604,8 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
             saved = chan.get_saved_videos(output_directory)
             to_download = []
             for video in chan.reqs:
+                if video in chan.failures and chan.failures[video].get("attempts", 0) >= 5:
+                    continue
                 meta = get_meta(video)
                 if not "Exception" in meta and not is_live(meta):
                     to_download.append([meta["title"], video])
@@ -610,6 +626,8 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
                     #run_shell(["python", os.path.join(os.path.dirname(os.path.realpath(__file__)), "live.py"), video, "record", "\"" + chan.name + "\""])
             for video in rss:
                 if not video["link"] in saved:
+                    if video["link"] in chan.failures and chan.failures[video["link"]].get("attempts", 0) >= 5:
+                        continue
                     for word in keywords:
                         if word.lower() in video["title"].lower():
                             meta = get_meta(video["link"])
@@ -641,10 +659,20 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
                 downloaded = []
                 for success in download_results["successes"]:
                     downloaded.append(success)
+                    if success in chan.failures:
+                        del chan.failures[success]
                 if len(downloaded) > 0:
                     chan.last_updated = int(time.time())
                 for id in download_results["bots"]:
                     chan.carried_reqs.append(id)
+                for failure, error_msg in download_results["failures"].items():
+                    if failure in chan.failures:
+                        chan.failures[failure]["attempts"] += 1
+                        chan.failures[failure]["error"] = error_msg
+                        chan.failures[failure]["timestamp"] = int(time.time())
+                    else:
+                        chan.failures[failure] = {"attempts": 1, "error": error_msg, "timestamp": int(time.time())}
+                chan.save_failures()
                 with open(os.path.join(output_directory, slugify(chan.name), "saved_urls.txt"), "a") as saved_doc:
                     for item in downloaded:
                         saved_doc.write(item + "\n")
@@ -678,9 +706,12 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
                             to_download.append(item)
                 else:
                     for word in keywords:
-                        if not ("https://www.youtube.com/watch?v=" + metadata["id"]) in to_download and not ("https://www.youtube.com/watch?v=" + metadata["id"]) in saved:
+                        vid_url = "https://www.youtube.com/watch?v=" + metadata["id"]
+                        if not vid_url in to_download and not vid_url in saved:
+                            if vid_url in chan.failures and chan.failures[vid_url].get("attempts", 0) >= 5:
+                                continue
                             if word.lower() in metadata["title"].lower():
-                                to_download.append([metadata["title"] ,"https://www.youtube.com/watch?v=" + metadata["id"]])
+                                to_download.append([metadata["title"] ,vid_url])
                                 break
                 return to_download
             if not meta == None:
@@ -697,10 +728,19 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
                 download_results = download_batch(to_download, ydl_opts, os.path.join(output_directory, slugify(chan.name)), max_retries=3, save_history=False)
                 for success in download_results["successes"]:
                     downloaded.append(success)
+                    if success in chan.failures:
+                        del chan.failures[success]
                 if len(downloaded) > 0:
                     chan.last_updated = int(time.time())
-                for failure in download_results["failures"]:
-                    log("Failure: " + str(failure))
+                for failure, error_msg in download_results["failures"].items():
+                    log("Failure " + failure + ": " + str(error_msg))
+                    if failure in chan.failures:
+                        chan.failures[failure]["attempts"] += 1
+                        chan.failures[failure]["error"] = error_msg
+                        chan.failures[failure]["timestamp"] = int(time.time())
+                    else:
+                        chan.failures[failure] = {"attempts": 1, "error": error_msg, "timestamp": int(time.time())}
+                chan.save_failures()
             with open(os.path.join(output_directory, slugify(chan.name), "saved_urls.txt"), "a") as saved_doc:
                 for item in downloaded:
                     saved_doc.write(item + "\n")

* fix: Clean up after failures

Now that failures are cleaned up after, we can clean up files left behind.
Improves ASMRchive look. No more broken videos. Will need to mass-migrate prod.

* fix: Fix mishandled bad downloads

Currently when a download fails, it leaves its 'trash' behind and litters
the disk with folders and files that have no actual ASMR.
This writes failures to a failures.json file in each channel directory,
and then deletes any created folder for the invalid ASMR.

Deletes are sensitive to archived ASMR and creates backups in case.
diff --git a/python_app/main.py b/python_app/main.py
index 4527b8c..405ae87 100644
--- a/python_app/main.py
+++ b/python_app/main.py
@@ -206,6 +206,9 @@ class Channel():
         self.failures = self.load_failures()
         self.reqs = []
         for video in reqs:
+            if video in self.failures:
+                del self.failures[video]
+                self.save_failures()
             if is_live(get_meta(video)):
                 self.carried_reqs.append(video)
             else:
@@ -384,10 +387,10 @@ def load_channels(output_directory: str):
             reqs = temp
             new = []
             for i in reqs:
-                if (re.search(r"youtube\.com/watch", i) or re.search(r"youtube\.com/shorts", i)): #if it's a standard youtube url
-                    new.append(i[-11:])
-                elif re.match(r"^.{11}$", i): #if the string is 11 characters long
-                    new.append(i)
+                # Match standard 11-character ID, or extract it from various youtube URL formats
+                match = re.search(r"(?:v=|\/|shorts\/|^)([0-9A-Za-z_-]{11})(?:\?|&|$)", i)
+                if match:
+                    new.append(match.group(1))
             reqs = new
             chan = Channel(lines[0], lines[1], lines[2], output_directory, last_updated=last_updated, reqs=reqs)
             channels.append(chan)
@@ -490,9 +493,30 @@ def download_batch(to_download, ydl_opts, channel_path, limit=10, max_retries=1,
                 path = os.path.join(channel_path, get_vid(video[1]))
                 if not os.path.exists(path):
                     os.makedirs(path)
-                else: #if it exists, and we need the video, we need to purge any 'bad data' here.
-                    shutil.rmtree(path)
-                    os.makedirs(path) #and then re-create the directory fresh and clean.
+                else:
+                    valid_audio_formats = ["webm", "opus", "flac", "aac", "wav", "mp3", "m4a", "ogg"]
+                    found_audio = []
+                    for fmt in valid_audio_formats:
+                        audio_file = f"asmr.{fmt}"
+                        if os.path.exists(os.path.join(path, audio_file)):
+                            found_audio.append(audio_file)
+
+                    if found_audio:
+                        backup_dir = os.path.join(path, "backups")
+                        if not os.path.exists(backup_dir):
+                            os.makedirs(backup_dir)
+                        backup_count = 1
+                        while True:
+                            if any(os.path.exists(os.path.join(backup_dir, f"backup-{backup_count}.{fmt}")) for fmt in valid_audio_formats):
+                                backup_count += 1
+                            else:
+                                break
+                        for audio_file in found_audio:
+                            ext = audio_file.split(".")[-1]
+                            shutil.move(os.path.join(path, audio_file), os.path.join(backup_dir, f"backup-{backup_count}.{ext}"))
+                    else:
+                        shutil.rmtree(path)
+                        os.makedirs(path)
                 ydl_opts['outtmpl'] = os.path.join(path, 'asmr.%(ext)s')
                 d = video_downloader(video[1], ydl_opts, path, id, return_dict, bypass_slowness)
                 d.start_download()
@@ -609,21 +633,32 @@ def ASMRchive(channels: list, keywords: list, output_directory: str):
             for video in chan.reqs:
                 if video in chan.failures and chan.failures[video].get("attempts", 0) >= 5:
                     continue
+
+                # Reconstruct full URL so failures log correctly
+                full_url = f"https://www.youtube.com/watch?v={video}" if len(video) == 11 else video
+
                 meta = get_meta(video)
                 if not "Exception" in meta and not is_live(meta):
-                    to_download.append([meta["title"], video])
-                if "Exception" in meta:
+                    to_download.append([meta["title"], full_url])
+                elif "Exception" in meta:
                     cookie_exception_flags = ["inappropriate", "sign in", "age", "member"]
+                    handled = False
                     for flag in cookie_exception_flags:
                         if flag in meta and not "live event" in meta:
                             meta = get_meta_cookie(video)
                             if not "Exception" in meta:
-                                to_download.append([meta["title"], video])
+                                to_download.append([meta["title"], full_url])
+                                handled = True
+                            break
                     if "Exception: No cookies" in meta:
-                        continue # Might want to inform the user that their request failed here.
-                    if "live event" in meta: # A live event, we want to start the recorder.
-                        pass
+                        pass # Continue, let it fall through and be queued
+                    elif "live event" in meta: # A live event, we want to start the recorder.
+                        handled = True
                         #run_shell(["python", os.path.join(os.path.dirname(os.path.realpath(__file__)), "live.py"), video, "record", "\"" + chan.name + "\""])
+
+                    if not handled:
+                        # Append anyway to guarantee folder cleanup/backup logic executes and failure is logged
+                        to_download.append(["Unknown Title", full_url])
                 elif is_live(meta):
                     pass
                     #run_shell(["python", os.path.join(os.path.dirname(os.path.realpath(__file__)), "live.py"), video, "record", "\"" + chan.name + "\""])

## 1.11.3 - 2026-02-28
Fix messed up CI (#160)

* fix: startup script ouputs to wrong log file.

- Fixes an issue where the startup.sh script would push the logs to
the incorrect location.

* test: Update yt-dlp update testing logic

- Mashes the update button instead of hitting it only once.
- Adds more robust logging to check_dlp.py and flag_check.sh

* ci: Move wait logic

- Removes wait logic inside the Jenkins pipeline
- Replaces it by adding it as a python step in the unit tests.

* ci: reorganize tests

- Removed the non-standard build of ASMRchive with wrong version of yt-dlp.
- Re-added the unit test that verifies yt-dlp is up to date
- Added step to pipeline to manually downgrade yt-dlp for integration tests.

* ci: Fix broken stage formatting

* ci: Tell webserver yt-dlp has been downgraded

* fix: Fix potential infinite loop in unit tests.

## 1.11.2 - 2026-02-28
Ci/optimize dlp tests (#159)

* ci: Remove dlp update integration test

- Test functionality is now built in directly to the main test.
- Unit tests no longer verify yt-dlp is up to date.

* test: Increase max wait on dlp update test

* test: Improve assertion messages.

* test: Add verbose success messages for assertions

* ci: Eliminate race condition

There was a race condition resulting in a small percentage of builds
failing. Because the container was re-installing yt-dlp at the start,
it resulted in the unit tests failing by trying to import a package
as it was being reinstalled. Waiting for the container to be up
and online should completely fix the issue.

* test: Add more tries to pfp unit tests

## 1.11.1 - 2026-02-28
ci: Remove dlp update integration test (#158)

- Test functionality is now built in directly to the main test.
- Unit tests no longer verify yt-dlp is up to date.

## 1.11.0 - 2026-02-26
Automatically translate channel URL's to IDs  (#157)

* feat: admintools accepts channel url's

- Channels can now be added via channel id like before, or by URL
- Sanitized input should only accept a youtube channel url as input

* ci: Update reference id

## 1.10.1 - 2026-02-25
fix: Permissions overhaul and php error fixes

* fix: Reduce filesystem permissions

Reduces use of overly-passive 777 file permissions through ASMRchive
Increases security

* refactor: tighten file permissions on php files

- Add `create_dir` and `write_file` helper functions to `library.php`.
- Remove insecure `umask(0)` calls, eliminating 777 permissions.
- Audit and refactor `admintools.php` to use new helpers for system flags, channel metadata, and ASMR assets.
- Update `player.php` to use helpers for comment storage and directory management.
- Ensure consistent permissions

* fix: remove undefined $base_url from form actions

The `$base_url` variable was undefined when used within admintools for forms.
Because the default behavior already aligned with running the . page,
no visible issue was happening, but it was generating error logs.

## 1.10.0 - 2026-02-21
Column Rework (#154)

* feat: Remove status column from index.php

Here we add a new argument for display_row in channel "show_status"
which when true, shows the status. I removed this from index.php
but kept it in admintools since channel status is more of an admin thing

* ci: Fix "label too long" error

There was an error within the integration tests stage, where the network
name of the containers grows too long with a long branch name. This
caused the step to fail. This fixes that by defining a network alias
for the container. This is fine since builds are isolated in podman
networks, so we can just use the same name every time.

* ci: Fix error in Integration Tests

There was an error in Integration tests that our previous commit
was causing. This is because the test expects the status to be on the
homepage. Since we removed it, it caused issues. Using admintools
from now on.

* ci: Use beautifulsoup for parsing webpages

Change from my string parsing method to using beautifulsoup.
This is much cleaner, easier to read, and more resilient to changes
in the HTML.

* feat: Add client-side sorting to web interface

- Implement pure JavaScript table sorting in `www/sort.js`
- Enable sorting on Channel Index, Video Lists, and admintools
- Update `library.php` to inject `data-sort-value` for accurate sorting of dates, times, and counts
- Add CSS styles for sortable headers and direction indicators

* feat: Add "Updated" column

- Implement tracking for when an ASMR was last added to a channel.
- Update .channel metadata format to include a timestamp on line 4.
- Add auto-migration to backfill timestamps
- Ensure manual uploads and new channel additions refresh the "Updated" timestamp.

* fix: Fix a colspan issue in admintools

## 1.9.1 - 2026-02-17
CI Optimizations - Concurrent Builds (#153)

* ci: Enable concurrent builds

- Use EXECUTOR_NUMBER for port allocation (4445-4449)
- Sanitize BUILD_TAG for all resource names (images, containers, networks, volumes)
- Create dedicated podman network and volume per build
- Replace --network=host with inter-container hostname communication
- Remove throttle property and tidy-up stage
- Implement comprehensive post-block cleanup (retain last 5 images/volumes)

* ci: enforce lowercase for all Podman resource names

Image names must be lowercase for Podman compatibility.
Adding .toLowerCase() to BUILD_TAG_CLEAN ensures all derived names
(images, containers, networks, volumes) are automatically valid.

* test: resolve Podman container hostname for Chrome

Chrome in headless containers cannot use Podman's internal network DNS.
Resolve hostname to IP using socket.gethostbyname() and inject via
--host-resolver-rules so Chrome can reach the app container.

* ci: Fix build cleanup logic

Made cleanup process more readable.
The old process was counting image tags and not unique images,
causing a duplication error that inflated the list. This resulted
in images being actively used getting deleted. Fixed by clearing
duplicates in the output so the logic works correctly.

* ci: Switch to executor-specific DNS for build URLs

Move BUILD_PORT calculation to Initialization stage and route all build links through
executor-specific DNS hostnames (jenkins-1.wronghood.net, etc.) instead of lan.wronghood.net
with port numbers. The reverse proxy handles hostname-based routing.

* ci: Use HTTPS in executor links

Update links to use HTTPS since we support it.

## 1.9.0 - 2026-02-15
feat: Cookie upload via admintools (#152)

Adds a form to admintools that accepts Netscape-format cookies with a
configurable TTL (15/30/60/120 min). Cookies are saved with expiration
timestamps in the filename and automatically cleaned up by flag_check.sh.

- Cookie files are write-only (0200) to prevent Apache from reading them
- Cookies directory permissions updated so Apache can write but not list
- Fixed PHP timezone (was UTC, now matches container EST)

## 1.8.5 - 2026-02-14
ci: Pipeline Optimizations (#151)

* ci: Remove unnecessary reverse proxy test.

* ci: Remove unused Build ID parameter

This was defined at some point but never implemented in the pipeline.
Removed to reduce confusion, and clean up the parameter list. May re-add later.

* ci: Add intelligent image caching based on job.

PR Builds will be forced to fully rebuild.
Branch Builds will use caching for speed!
Dangling images are now cleaned up when doing fresh builds.

Should significantly reduce build times during branch updates for faster
testing, while ensuring PR builds get clean dependency updates.

* ci: Add optional pause functionality.

With the previously unused pause parameter, it's now implemented
so, when enabled, allows stepping through each stage of the pipeline
for deeper troubleshooting capabilities. Also updated the container URL.

* ci: Fix extra curly brace

* build: Optimize image layers

- Reduced image size by adding dnf clean all
- Combined run layers to speed up build and reduce total layers (size).
- Moved deno install to start of build process, optimize layer cache.

* test: Fix testing pipeline

## 1.8.4 - 2026-02-11
fix: Adds deno to path. Required for yt-dlp to use it. (#150)

## 1.8.3 - 2026-02-11
fix: Several bug fixes, upgrade major os edition.

* chore: update base image to Fedora 43

* fix: Fixes inability to solve js challenges

* fix: Fixes a problem where members-only videos will download themselves 10 times every time.

## 1.8.2 - 2025-07-18
test: Allows unit test to try a few times before failing, allowing time for server to come up.

## 1.8.1 - 2025-07-17
ci: Fix url in unit test

## 1.8.0 - 2025-07-11
feat: Apple touch icon (#141)

* feat: Add touch icon.

* feat: Host touch icon within node.

* test: Write unit test

* fix: Put env variable in string

* fix: Fixes syntax for env variables

* fix: Add comma to test

* fix: Fix port with wrong context

* fix: Remove port?

## 1.7.1 - 2025-06-14
fix: Fix support for shorts (#140)

## 1.7.0 - 2025-06-14
feat: Yt dlp version check

* fix: Expand flag system

* feat: Add yt-dlp Version Check

* fix: Set default values in case dlp has no check file

* feat: Add check button

* fix: Adjust alert text

* fix: Move dlp check down on admintools

* chore: Adjust text on scan now button

* ci: Add dlp-override for integration testing the new feature.

* ci: Add pipeline for testing yt-dlp update feature.

* ci: Fix testing error on .lower of nonetype.

* ci: Fix indentation issue, add dlponly flag to speed up Jenkins

* ci: fix Jenkinsfile positional argument in test

* ci: Wait for webpage to load properly before starting.

* ci: Increase timeout

* ci: Test to figure out why this is failing

* ci: Test to ensure the page is giving 200 before trying the next step...

* ci: Try to catch the exception

* ci: Add progress print statements.

## 1.6.0 - 2024-11-06
feat: Add build date (#134)

* feat: Add build date

* feat: Adjust build date

## 1.5.1 - 2024-11-06
fix: Update yt-dlp explicitly (#132)

## 1.5.0 - 2024-10-19
feat: Add metadata to ASMRchive player

* feat: Add metadata to ASMRchive player

* fix: Handle escaping double quotes

## 1.4.2 - 2024-10-19
chore: Update to Fedora 40 (#129)

## 1.4.1 - 2024-08-27
fix: Fix member playlist for no entries channels (#126)

## 1.4.0 - 2024-08-26
feat: Add members playlist link to admintools (#124)

## 1.3.0 - 2024-08-21
feat: Add version to admintools (#122)

## 1.2.4 - 2024-08-21
fix: Handles youtube block with re-queue (#120)

* fix: Handles youtube block with re-queue

* fix: remove nooverwrites rule which shouldn't have been left in.

## 1.2.3 - 2024-07-31
fix: Fix queue when newline in channel file

* fix: Fix website queue

* fix: Filter blank lines in python end

## 1.2.2 - 2024-07-23
fix: Update yt image

* fix: Change yt image

* fix: Fix broken url check function

* fix: Fix broken images in player when using default thumbnail.

## 1.2.1 - 2024-07-23
fix: Change yt image (#115)

## 1.2.0 - 2024-07-23
feat: YouTube Button

* feat: Add youtube image

* feat: Add button

* feat: Add functionality to youtube button

* test: Add test for youtube button

## 1.1.0 - 2024-06-26
Download Button (#112)

* feat: Add download button

* test: Add download integration test

* test: Move default download location

* test: fix logic

## 1.0.1 - 2024-06-25
ci: Versioning (#111)

* ci: Add versioning logic

