#!/bin/bash

# Runs twice, because this is being ran every 1m by crontab and I wanted to run it every 30s
for i in {1..12}; do
    if [ -f "/var/www/html/flags/scan_flag.txt" ]; then
        rm -f /var/www/html/flags/scan_flag.txt
        python /var/python_app/main.py
    fi
    sleep 5
done