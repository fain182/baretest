ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-alpine

# PHP 8.1 passes musl's fopencookie a seek function of the wrong type, an error since GCC 14.
RUN mkdir /tmp/php-src \
    && tar -xf /usr/src/php.tar.xz -C /tmp/php-src --strip-components=1 \
    && apk add --no-cache $PHPIZE_DEPS \
    && cd /tmp/php-src \
    && CFLAGS='-Wno-error=incompatible-pointer-types' ./configure --prefix=/opt/php --disable-all --disable-cgi --disable-phpdbg \
    && make -j"$(nproc)" \
    && make install \
    && rm -rf /tmp/php-src

ENV PATH=/opt/php/bin:$PATH
WORKDIR /app
