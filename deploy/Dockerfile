# Runtime-only image: PHP-FPM + the extensions both apps need. App code is
# bind-mounted at container start (see docker-compose.yml), not baked in here
# -- so vendor/ must already exist on the host checkout (run `composer
# install` there once, see deploy/README.md) before the container starts.
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        icu-data-full \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd zip mbstring intl opcache \
    && apk del --no-cache postgresql-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev icu-dev oniguruma-dev

EXPOSE 9000
CMD ["php-fpm"]
