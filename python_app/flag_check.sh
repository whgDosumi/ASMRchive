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
    if [ -f "/var/ASMRchive/.appdata/flags/check_dlp_flag.txt" ]; then
        rm -f "/var/ASMRchive/.appdata/flags/check_dlp_flag.txt"
        python /var/python_app/check_dlp.py
    fi

    # Clean up expired cookie files
    # Filenames look like: cookie-20260215-0457-a3f2.txt
    # The expiry timestamp is the 2nd and 3rd sections (YYYYMMDD-HHMM)
    cookie_dir="/var/ASMRchive/.appdata/cookies"
    now=$(date +%Y%m%d-%H%M)

    for cookie_file in "$cookie_dir"/cookie-*.txt; do
        if [ ! -f "$cookie_file" ]; then
            continue
        fi

        filename=$(basename "$cookie_file")
        expiry=$(echo "$filename" | cut -d'-' -f2-3)

        if [ "$now" \> "$expiry" ] || [ "$now" = "$expiry" ]; then
            rm -f "$cookie_file"
        fi
    done
}

check
sleep 30
check