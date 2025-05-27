#!/bin/bash

crond
php-fpm

if [ ! -d "/var/ASMRchive/.appdata" ] # If appdata doesn't already exist, initialize it
then
    mkdir "/var/ASMRchive/.appdata"
    mkdir /var/ASMRchive/.appdata/channels
    mv /var/python_app/cookies /var/ASMRchive/.appdata/

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
    chown apache /var/ASMRchive/.appdata/flags
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

# Set desired file permissions
chmod o-r /var/ASMRchive/.appdata/cookies 
chmod o-r /var/ASMRchive/.appdata/logs 
chmod o+w /var/ASMRchive/.appdata/channels
# Give apache ownership of the channel files
chown apache /var/ASMRchive/.appdata/channels/*
chgrp apache /var/ASMRchive/.appdata/channels/*

python /var/python_app/check_dlp.py
python /var/python_app/clear_downloads.py
python /var/python_app/update_web.py
python /var/python_app/main.py bypass_convert >> \"/var/ASMRchive/.appdata/logs/python/main-`date +\%Y-\%m-\%d`-asmr.log\" 2>&1
/usr/sbin/httpd -D FOREGROUND