FROM richarvey/nginx-php-fpm:latest

# Copy seluruh file project
COPY . /var/www/html

# Konfigurasi Nginx & PHP untuk Laravel
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1
ENV COMPOSER_ALLOW_SUPERUSER 1

# Script otomatis untuk cache & migrasi database saat deploy
RUN mkdir -p /var/www/html/scripts
RUN printf "#!/bin/sh\nphp artisan config:cache\nphp artisan route:cache\nphp artisan view:cache\nphp artisan migrate --force\n" > /var/www/html/scripts/00-laravel-deploy.sh
RUN chmod +x /var/www/html/scripts/00-laravel-deploy.sh