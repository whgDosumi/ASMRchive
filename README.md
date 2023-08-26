# ASMRchive
Python app that watches channels on youtube and selectively downloads the audio of those videos. Webserver allows playback. 

## Setup
```
python -m pip install -r python_app/requirements.txt
python python_app/setup.py
python python_app/add_channels.py # add some channels
docker build -t asmrchive .
```

`add_channels.py` uses youtube channel ID which might not be always visible. Use https://commentpicker.com/youtube-channel-id.php in that case.

## Docker run command
```
docker run -d \
        -p 4444:80 \
        -v <Archive location on host>:/var/ASMRchive \
        --name asmrchive \
        asmrchive
```
`-p 4444:80` specifies the port. In this example, ASMRchive is hosted on `127.0.0.1:4444`.

## Known Issues and Workarounds
- Archive has incorrect file permissions. Can result in issues leaving comments and adding new ASMR with admintools
  - `find <asmr path> -type d | xargs -d "\n" chmod ugo=rwx`
  - `find <asmr path> -type f | xargs -d "\n" chmod ugo=rw`

- Videos aren't downloading, yt-dlp errors in the logs.
  - Try fully rebuilding the container with no cache.  Include --pull and --no-cache in your docker/podman build command. This will update everything to the latest version, which fixes many issues.
