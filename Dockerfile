ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

RUN mkdir /tmp/php-src \
    && tar -xf /usr/src/php.tar.xz -C /tmp/php-src --strip-components=1 \
    && apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && cd /tmp/php-src \
    && ./configure --prefix=/opt/php --disable-all --disable-cgi --disable-phpdbg \
    && make -j"$(nproc)" \
    && make install \
    && rm -rf /tmp/php-src /var/lib/apt/lists/*

ENV PATH=/opt/php/bin:$PATH
WORKDIR /app
