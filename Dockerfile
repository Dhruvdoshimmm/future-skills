# Production image for Railway (or any Docker host). Apache + PHP 8.3 + MySQL driver.
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && printf 'ServerName localhost\n<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\nServerTokens Prod\nServerSignature Off\n' \
      > /etc/apache2/conf-available/app.conf \
 && a2enconf app \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHP needs exactly one Apache MPM (prefork). Remove any others.
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
 && a2enmod mpm_prefork

COPY . /var/www/html/
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh \
 && mkdir -p /var/www/html/data \
 && chown -R www-data:www-data /var/www/html/data

# Railway injects $PORT; start.sh points Apache at it.
CMD ["start.sh"]
