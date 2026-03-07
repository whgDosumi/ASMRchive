FROM fedora:43

# Install rpmfusion for ffmpeg
RUN dnf install -y https://mirrors.rpmfusion.org/free/fedora/rpmfusion-free-release-$(rpm -E %fedora).noarch.rpm https://mirrors.rpmfusion.org/nonfree/fedora/rpmfusion-nonfree-release-$(rpm -E %fedora).noarch.rpm

# Install webserver, php, cron, python, and ffmpeg, findutils for perm mods later on.
RUN dnf update -y && dnf -y install \
    httpd php cronie python pip ffmpeg findutils unzip \
    && dnf clean all

# Install uv
COPY --from=ghcr.io/astral-sh/uv:latest /uv /uvx /usr/local/bin/

# Install DENO for yt-dlp js challenges
RUN curl -fsSL https://deno.land/install.sh | sh -s -- -y \
    && ln -s /root/.deno/bin/deno /usr/local/bin/deno


# Set timezone to EST
RUN ln -sf /usr/share/zoneinfo/America/New_York /etc/localtime

# Set up crontab to run the python app and maintenance scripts
RUN { \
      echo '*/15 * * * * cd /var/python && /usr/local/bin/uv run main.py >> "/var/ASMRchive/.appdata/logs/python/main-$(date +\%Y-\%m-\%d)-asmr.log" 2>&1'; \
      echo '* * * * * /var/python/flag_check.sh >> "/var/ASMRchive/.appdata/logs/flag_check.log" 2>&1'; \
      echo '0 * * * * cd /var/python && /usr/local/bin/uv run check_dlp.py >> "/var/ASMRchive/.appdata/logs/check_dlp.log" 2>&1'; \
    } | crontab -

# Add php config (required for uploads)
COPY php.ini /etc/php.ini
COPY www.conf /etc/php-fpm.d/www.conf

# Add httpd config
COPY httpd.conf /etc/httpd/conf/httpd.conf

# Set up directory structure
# The php-fpm directory is required for php to communicate with Apache
# Soft link provides webserver access to the ASMRchive directory.
RUN mkdir -p /run/php-fpm/ \
    && mkdir /var/ASMRchive \
    && ln -s /var/ASMRchive /var/www/html/ASMR


# Copy over startup.sh and make it executable.
COPY startup.sh /var/startup.sh
RUN chmod 770 /var/startup.sh

# Copy over webserver.
COPY www /var/www/html

# Copy over python app.
COPY python /var/python

# Setup python env
WORKDIR /var/python
RUN uv sync --upgrade-package yt-dlp

# Make force_scan.sh executable
RUN chmod 770 /var/python/flag_check.sh

# Copy in version for webserver.
COPY version.txt /var/www/html/version.txt

# Expose httpd.
EXPOSE 80

# Write build date
RUN python3 -c "from datetime import datetime; print(datetime.today().strftime('%Y-%m-%d'))" >> /var/www/html/version.txt

CMD /var/startup.sh >> startup_log.txt
