FROM php:8.4-cli-alpine

RUN apk add --no-cache sqlite-dev git unzip \
    && docker-php-ext-install pdo_sqlite \
    && apk del sqlite-dev \
    && echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && git config --global --add safe.directory /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
