FROM dunglas/frankenphp:1-php8.4-alpine AS base

WORKDIR /app

ENV TZ=Europe/Berlin

RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/Europe/Berlin /etc/localtime \
    && echo "Europe/Berlin" > /etc/timezone \
    && apk del tzdata

RUN install-php-extensions \
    pdo_sqlite \
    sqlite3 \
    intl \
    opcache \
    zip \
    sockets

RUN echo "date.timezone=Europe/Berlin" > /usr/local/etc/php/conf.d/timezone.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production stage
FROM base AS prod

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && composer run-script post-install-cmd --no-interaction \
    && mkdir -p var/tailwind \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile

RUN mkdir -p var/cache var/log var/data \
    && chown -R www-data:www-data var/ public/assets/

# Stage: assets compiled, dev-deps kept, APP_ENV=dev for profiler/debug toolbar
FROM base AS stage

ENV APP_ENV=dev

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction

COPY . .
RUN composer dump-autoload --no-interaction \
    && composer run-script post-install-cmd --no-interaction \
    && mkdir -p var/tailwind \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile

RUN mkdir -p var/cache var/log var/data \
    && chown -R www-data:www-data var/ public/assets/

# Development stage (local, code mounted via volume)
FROM base AS dev

ENV APP_ENV=dev

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction

COPY . .
RUN composer dump-autoload --no-interaction \
    && composer run-script post-install-cmd --no-interaction

RUN mkdir -p var/cache var/log var/data \
    && chown -R www-data:www-data var/
