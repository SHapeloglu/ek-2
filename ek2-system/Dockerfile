FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php:8.4-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev libjpeg-turbo-dev libpng-dev && \
    docker-php-ext-configure gd --with-jpeg && \
    docker-php-ext-install pdo_mysql intl mbstring zip opcache exif gd
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private/ek2 storage/ocr_aktarim bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]
