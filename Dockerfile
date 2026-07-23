FROM php:8.4-cli-alpine

RUN apk add --no-cache \
        icu-libs \
        icu-dev \
        libzip \
        libzip-dev \
        libpq \
        postgresql-dev \
        docker-cli \
    && docker-php-ext-install \
        intl \
        pdo_pgsql \
        zip \
        opcache \
    && apk del icu-dev libzip-dev postgresql-dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
