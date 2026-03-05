#!/bin/bash

crond
php-fpm

# Override for yt-dlp version
if [ -z "${DLP_VER+x}" ]; then
    echo "DLP-VER not overridden."
else
    python -m pip uninstall -y yt-dlp
    python -m pip install -U yt-dlp==$DLP_VER
fi

if [ ! -d "/var/ASMRchive/.appdata" ] # If appdata doesn't already exist, initialize it
then
    mkdir "/var/ASMRchive/.appdata"
    mkdir /var/ASMRchive/.appdata/channels
    mkdir /var/ASMRchive/.appdata/cookies

    # Set up log locations
    mkdir "/var/ASMRchive/.appdata/logs"
    mkdir "/var/ASMRchive/.appdata/logs/python"
fi

# Ensure channels directory exists.
if [ ! -d "/var/ASMRchive/.appdata/channels" ]
then
    mkdir "/var/ASMRchive/.appdata/channels"
fi

# Make a directory for the scan flag
if [ ! -d "/var/ASMRchive/.appdata/flags" ]
then
    mkdir "/var/ASMRchive/.appdata/flags"
    chgrp apache /var/ASMRchive/.appdata/flags
    chmod 770 /var/ASMRchive/.appdata/flags
fi

# Create relevant log locations in case we're updating from an older version of ASMRchive
if [ ! -d "/var/ASMRchive/.appdata/logs" ]
then
    mkdir "/var/ASMRchive/.appdata/logs"
fi
if [ ! -d "/var/ASMRchive/.appdata/logs/python" ]
then
    mkdir "/var/ASMRchive/.appdata/logs/python"
fi
if [ ! -L "/var/www/html/channels" ]
then
ln -s /var/ASMRchive/.appdata/channels /var/www/html/channels # Give webserver access to channels. 
fi
if [ ! -d "/var/ASMRchive/.appdata/cookies" ]
then
    mkdir "/var/ASMRchive/.appdata/cookies"
fi

# Set desired file permissions
chgrp apache /var/ASMRchive/.appdata
chmod 775 /var/ASMRchive/.appdata
chgrp apache /var/ASMRchive/.appdata/cookies
chmod 730 /var/ASMRchive/.appdata/cookies
chmod 770 /var/ASMRchive/.appdata/logs 
chmod 775 /var/ASMRchive/.appdata/channels
chmod 770 /var/ASMRchive/.appdata/flags
# Set apache group for necessary files.
chgrp -R apache /var/ASMRchive/.appdata/channels

python /var/python/check_dlp.py
python /var/python/clear_downloads.py
python /var/python/update_web.py
python /var/python/main.py bypass_convert >> "/var/ASMRchive/.appdata/logs/python/main-$(date +%Y-%m-%d)-asmr.log" 2>&1
/usr/sbin/httpd -D FOREGROUND