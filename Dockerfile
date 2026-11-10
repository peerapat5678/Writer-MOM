FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
COPY . /var/www/html/
RUN rm -rf /var/www/html/.git /var/www/html/api/config.php
