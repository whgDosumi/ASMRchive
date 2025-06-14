import subprocess
import re
import json


# Returns a dict with bool up_to_date and the two version strings.
def check_version():
    p = subprocess.run(["/usr/local/bin/yt-dlp", "-U"], capture_output = True, text = True)
    stdout = p.stdout.splitlines()
    if "yt-dlp is up to date" in p.stdout:
        up_to_date = True
        match = re.search(r"stable@\d{4}\.\d{2}\.\d{2}", stdout[0])
        current_version = match.group()
        latest_version = current_version
    else:
        up_to_date = False
        current_version = re.search(r"stable@\d{4}\.\d{2}\.\d{2}", stdout[0]).group()
        latest_version = re.search(r"stable@\d{4}\.\d{2}\.\d{2}", stdout[1]).group()
    stdout = p.stdout.splitlines()
    output = {}
    output["up_to_date"] = up_to_date
    output["current_version"] = current_version
    output["latest_version"] = latest_version
    return output
    



if __name__ == "__main__":
    result = check_version()
    with open("/var/ASMRchive/.appdata/yt_dlp_info.json", "w") as f:
        json.dump(result, f)