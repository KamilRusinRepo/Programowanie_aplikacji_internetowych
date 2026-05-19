FROM php:8.3-fpm-alpine

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del .build-deps

WORKDIR /var/www/html
