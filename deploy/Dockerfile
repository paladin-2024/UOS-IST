# Shared PHP-FPM image for both apps (Projet_E-Gestion and projet_website).
# Build from the repo root with --build-arg APP_DIR=Projet_E-Gestion (or projet_website).
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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ARG APP_DIR
COPY ${APP_DIR}/ /var/www/html/

RUN if [ -f composer.json ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction; \
    fi \
    && mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
