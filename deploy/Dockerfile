# Runtime-only image: PHP-FPM + the extensions both apps need. App code is
# bind-mounted at container start (see docker-compose.yml), not baked in here
# -- so vendor/ must already exist on the host checkout (run `composer
# install` there once, see deploy/README.md) before the container starts.
FROM php:8.4-fpm-alpine

# Runtime shared libraries the compiled extensions link against -- installed
# as their own explicit layer (not just pulled in as -dev deps) so `apk del`
# below, which only removes the -dev/build packages, doesn't cascade-remove
# these too (Alpine's apk drops a dependency once nothing explicit needs it
# anymore, which is exactly what happened here the first time: deleting
# postgresql-dev/libpng-dev/etc. silently took libpq.so.5/libpng16.so.16/etc.
# with them, and every pgsql/gd/zip/intl-dependent request 500'd at runtime
# with "could not find driver" / "Unable to load dynamic library").
RUN apk add --no-cache \
        libpq \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        icu-libs \
        oniguruma \
        icu-data-full

RUN apk add --no-cache --virtual .build-deps \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd zip mbstring intl opcache \
    && apk del --no-cache .build-deps

EXPOSE 9000
CMD ["php-fpm"]
