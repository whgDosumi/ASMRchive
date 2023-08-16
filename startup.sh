#!/bin/bash

crond
php-fpm

if [ ! -d "/var/ASMRchive/.appdata" ] # If initial appdata isn't imported, import it. 
then
    mkdir "/var/ASMRchive/.appdata"
    mv /var/python_app/channels /var/ASMRchive/.appdata/
    mv /var/python_app/cookies /var/ASMRchive/.appdata/
    chmod o-r /var/ASMRchive/.appdata/cookies # This is to prevent the webserver from having access to the cookie files.
fi
if [ ! -L "/var/www/html/channels" ]
then
ln -s /var/ASMRchive/.appdata/channels /var/www/html/channels # Give webserver access to channels. 
fi
python /var/python_app/clear_downloads.py
python /var/python_app/update_web.py
python /var/python_app/main.py bypass_convert
/usr/sbin/httpd -D FOREGROUND