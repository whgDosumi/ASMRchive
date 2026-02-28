from main import *


# This is ran at container restart to clear any channels stuck in downloading status. 
if __name__ == "__main__":
    log_location = "/var/ASMRchive/.appdata/logs" # Directory name, log will be stored as clear_log.txt
    updated = []
    output_directory = "/var/ASMRchive"
    channels = load_channels(output_directory)
    for channel in channels:
        if channel.status == "downloading":
            updated.append(channel.name)
            channel.status = "archived"
            channel.save()
    if len(updated) >= 1:
        if not os.path.isdir(log_location):
            os.makedirs(log_location)
        with open(os.path.join(log_location, "clear_log.txt"), "a") as logfile:
            for i in updated:
                logfile.write(f"Changed status of channel \"{i}\" from downloading to archived.\n")
                print(f"Changed status of channel \"{i}\" from downloading to archived.")