FROM php:8.5-cli-alpine

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
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps icu-dev libzip-dev postgresql-dev

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
