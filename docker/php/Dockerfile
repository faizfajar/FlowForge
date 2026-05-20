FROM composer:2 AS builder

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        postgresql-dev \
        linux-headers \
        $PHPIZE_DEPS \
        zip \
        libzip-dev \
    && docker-php-ext-install pdo_pgsql pcntl bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && addgroup -g 1000 www \
    && adduser -D -G www -u 1000 www \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html

COPY --chown=www:www app ./app
COPY --chown=www:www bootstrap ./bootstrap
COPY --chown=www:www config ./config
COPY --chown=www:www database ./database
COPY --chown=www:www public ./public
COPY --chown=www:www resources ./resources
COPY --chown=www:www routes ./routes
COPY --chown=www:www storage ./storage
COPY --chown=www:www artisan composer.json composer.lock package.json vite.config.ts tsconfig.json .env.example ./
COPY --from=builder --chown=www:www /app/vendor ./vendor

RUN mkdir -p storage bootstrap/cache \
    && chown -R www:www storage bootstrap/cache

USER www

EXPOSE 9000
CMD ["php-fpm"]
