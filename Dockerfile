FROM fedora:40

# Install rpmfusion for ffmpeg
RUN dnf install -y https://mirrors.rpmfusion.org/free/fedora/rpmfusion-free-release-$(rpm -E %fedora).noarch.rpm https://mirrors.rpmfusion.org/nonfree/fedora/rpmfusion-nonfree-release-$(rpm -E %fedora).noarch.rpm

# Install webserver, php, cron, python, and ffmpeg, findutils for perm mods later on.
RUN dnf update -y && dnf -y install \
    httpd php.x86_64 cronie python pip ffmpeg findutils

# Upgrade pip
RUN python -m pip install --upgrade pip

# Set up crontab to run the python app every 15 minutes.
RUN (echo -e "*/15 * * * * /usr/bin/python3 /var/python_app/main.py >> \"/var/ASMRchive/.appdata/logs/python/main-\$(date +\%Y-\%m-\%d)-asmr.log\" 2>&1\n* * * * * /var/python_app/flag_check.sh\n0 * * * * /usr/bin/python3 /var/python_app/check_dlp.py") | crontab -

# install python requirements
COPY python_app/requirements.txt /var/python_requirements.txt
RUN python -m pip install -r /var/python_requirements.txt
# Make sure we update yt-dlp
RUN python3 -m pip install -U "yt-dlp[default]"
RUN rm /var/python_requirements.txt

# Add php config (required for uploads)
COPY php.ini /etc/php.ini
COPY www.conf /etc/php-fpm.d/www.conf

# Add httpd config
COPY httpd.conf /etc/httpd/conf/httpd.conf

# This is required to make php work.
RUN mkdir -p /run/php-fpm/

# mkdir for ASMRchive directory.
RUN mkdir /var/ASMRchive
# and make link in web
RUN ln -s /var/ASMRchive /var/www/html/ASMR

# Copy over startup.sh and make it executable.
COPY startup.sh /var/startup.sh
RUN chmod 770 /var/startup.sh

# Copy over webserver.
ADD www /var/www/html

# Copy over python app.
COPY python_app /var/python_app

# Make force_scan.sh executable
RUN chmod 770 /var/python_app/flag_check.sh

# Copy in version for webserver.
ADD version.txt /var/www/html/version.txt

# Expose httpd.
EXPOSE 80

# Set timezone to EST
RUN ln -sf /usr/share/zoneinfo/America/New_York /etc/localtime

# Write build date
RUN python3 -c "from datetime import datetime; print(datetime.today().strftime('%Y-%m-%d'))" >> /var/www/html/version.txt

CMD /var/startup.sh >> startup_log.txt
