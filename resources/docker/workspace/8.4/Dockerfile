FROM php:8.4-cli-alpine

ARG PUID=1000
ARG PGID=1000

RUN apk add --no-cache --virtual .build-deps \
    # for extensions
    $PHPIZE_DEPS \
    # for soap
    libxml2-dev \
    # for xdebug \
    linux-headers \
    && \
    apk add --no-cache \
    bash \
    # for soap
    libxml2 \
    # for composer
    unzip \
    && \
    docker-php-ext-install soap \
    && \
    pecl install \
    # pcov for coverage runs
    pcov && docker-php-ext-enable pcov \
    && \
    # for debugging
    pecl install xdebug-3.4.0 && docker-php-ext-enable xdebug \
    && \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Add a non-root user to prevent files being created with root permissions on host machine.
RUN addgroup -g ${PGID} user && \
    adduser -u ${PUID} -G user -D user

USER user
