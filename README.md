# ASMRchive
Python app that watches channels on youtube and selectively downloads the audio of those videos. Webserver allows playback. 

## Setup
```
python -m pip install -r python_app/requirements.txt
python python_app/setup.py
python python_app/add_channels.py # add some channels (Do some small easy ones to prevent a shitload of downloads like Shion or Kanata)
docker build -t asmrchive .
```

`add_channels.py` uses youtube channel ID which might not be always visible. Use https://commentpicker.com/youtube-channel-id.php in that case.

## Docker run command
```
docker run -d \
        -p 4444:80 \
        -v (repo path)/ASMRchive/archive:/var/ASMRchive \
        -v (repo path)/ASMRchive/www:/var/www/html \
        -v (repo path)/ASMRchive/python_app:/var/asmr_python \
        --name asmrchive \
        asmrchive
```
`-p 4444:80` specifies the port. In this example, ASMRchive is hosted on `127.0.0.1:4444`.

## Known Issues and Workarounds
- Archive has incorrect file permissions. Can result in issues leaving comments and adding new ASMR with admintools
  - `find <asmr path> -type d | xargs chmod ugo=rwx`
  - `find <asmr path> -type f | xargs chmod ugo=rw`

  
