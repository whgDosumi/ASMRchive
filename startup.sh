crond
php-fpm
rm -f /var/www/html/ASMR
rm -f /var/www/html/channels
rm -f /var/python_app/channels/channels
ln -s /var/ASMRchive /var/www/html/ASMR
ln -s /var/python_app/channels /var/www/html/channels
python /var/python_app/update_web.py
python /var/python_app/main.py bypass_convert
/usr/sbin/httpd -D FOREGROUND
