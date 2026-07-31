FROM composer:2.10.2 AS composer

FROM php:8.3.32-cli-bookworm

ARG XDEBUG_VERSION=3.5.3

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        default-mysql-client \
        file \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libsqlite3-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        poppler-utils \
        procps \
        rsync \
        unzip \
        weasyprint \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        pdo_mysql \
        sockets \
        zip \
    && pecl install "xdebug-${XDEBUG_VERSION}" \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/assestme-development.ini
COPY --chmod=0755 entrypoint.sh /usr/local/bin/assestme-entrypoint

WORKDIR /workspace

ENTRYPOINT ["/usr/local/bin/assestme-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
