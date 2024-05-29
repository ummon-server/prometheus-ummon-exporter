FROM php:8.2-apache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install --no-install-recommends -y \
        libpng-dev \
        libzip-dev \
        libicu-dev \
    && docker-php-ext-install zip
# RUN docker-php-ext-install zip

COPY --chown=www-data:www-data . /var/www/html
RUN composer install --optimize-autoloader --no-dev  --prefer-dist \
 && apt-get clean \
 && rm -rf /tmp/* /var/lib/apt/lists/*

WORKDIR /var/www/html