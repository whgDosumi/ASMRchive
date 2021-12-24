FROM fedora:33

# Install rpmfusion for ffmpeg
RUN dnf install -y https://mirrors.rpmfusion.org/free/fedora/rpmfusion-free-release-$(rpm -E %fedora).noarch.rpm https://mirrors.rpmfusion.org/nonfree/fedora/rpmfusion-nonfree-release-$(rpm -E %fedora).noarch.rpm

# Install webserver, php, cron, python, and ffmpeg, findutils for perm mods later on.
RUN dnf update -y && dnf -y install \
    httpd php.x86_64 cronie python ffmpeg findutils

# Expose httpd.
EXPOSE 80

# Copy over webserver.
ADD www /var/www/html

# mkdir for ASMRchive directory.
RUN mkdir /var/ASMRchive
# and make link in web
RUN ln -s /var/ASMRchive /var/www/html/ASMR

# Copy over startup.sh and make it executable.
ADD startup.sh /var/startup.sh
RUN chmod 770 /var/startup.sh

# This is required to make php work.
RUN mkdir -p /run/php-fpm/

# Copy over python app.
ADD python_app /var/asmr_python
# Setup log folder.
RUN mkdir /var/asmr_python/log
# Give webserver link to channels directory.
RUN ln -s /var/asmr_python/channels /var/www/html/channels

# pip in the requirements.
RUN python -m pip install -r /var/asmr_python/requirements.txt

# Set perms on the media directory.
RUN chmod -R 777 /var/ASMRchive
RUN chmod g+s /var/ASMRchive
RUN groupadd asmr_enjoyer
RUN usermod -aG asmr_enjoyer apache
RUN chgrp -R asmr_enjoyer /var/ASMRchive

RUN python /var/asmr_python/main.py
# Set up crontab to run the python app every 15 minutes.
RUN (echo "*/15 * * * * /usr/bin/python /var/asmr_python/main.py >> \"/var/asmr_python/log/\$(date +\%Y-\%m-\%d)-asmr.log\" 2>&1") | crontab -

CMD /var/startup.sh