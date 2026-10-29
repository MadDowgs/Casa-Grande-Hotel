# syntax=docker/dockerfile:1

# ---- Stage 1: PHP dependencies ------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---- Stage 2: runtime (Apache + PHP 8.3) --------------------------------------
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);'
ENTRYPOINT ["entrypoint.sh"]
