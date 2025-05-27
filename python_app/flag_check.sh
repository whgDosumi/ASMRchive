#!/bin/bash

# Runs twice, because this is being ran every 1m by crontab and I wanted to run it every 30s

check() {
    if [ -f "/var/ASMRchive/.appdata/flags/scan_flag.txt" ]; then
        rm -f /var/ASMRchive/.appdata/flags/scan_flag.txt
        python /var/python_app/main.py >> "/var/ASMRchive/.appdata/logs/python/main-$(date +\%Y-\%m-\%d)-asmr.log" 2>&1
    fi
    if [ -f "/var/ASMRchive/.appdata/flags/update_dlp_flag.txt" ]; then
        rm -f "/var/ASMRchive/.appdata/flags/update_dlp_flag.txt"
        python3 -m pip install -U "yt-dlp[default]"
        python /var/python_app/check_dlp.py
    fi
}

check
sleep 30
check