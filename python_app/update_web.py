import os
import shutil
from syslog import LOG_ODELAY
from main import Channel, load_channels
import filecmp

print("Updating web files in library...")

# Update player.php files
count = 0
for root, dirs, files in os.walk("/var/ASMRchive"):
    for file in files:
        if file == "player.php":
            path = os.path.join(root, file)
            if not filecmp.cmp(path, "/var/www/html/player.php"): # only update if there are changes
                count += 1
                os.remove(path)
                shutil.copy("/var/www/html/player.php", path)

print("Updated " + str(count) + " out-of-date player.php files.")

count = 0
# Check for old naming convention and update channel index
channels = load_channels("/var/ASMRchive")
for chan in channels:
    path = os.path.join(chan.path, "index.php")
    if os.path.exists(path):
        count += 1
        shutil.move(path, os.path.join(chan.path, "channel.php"))
print("Renamed " + str(count) + " channel index files with old naming convention.")

count = 0
# Update channel.php files
for root, dirs, files in os.walk("/var/ASMRchive"):
    for file in files:
        if file == "channel.php":
            path = os.path.join(root, file)
            if not filecmp.cmp(path, "/var/www/html/channel_index.php"): # only update if there are changes
                count += 1
                os.remove(path)
                shutil.copy("/var/www/html/channel_index.php", path)
print("Updated " + str(count) + " out-of-date channel.php files.")

