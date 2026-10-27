# Production image (docs/DEPLOYMENT.md, ADR 0012). Build from the repository root:
#
#   docker build -f docker/Dockerfile --target app -t schooldesk-app .
#   docker build -f docker/Dockerfile --target web -t schooldesk-web .
#
# `app` runs PHP-FPM, Horizon or the scheduler (compose.prod.yaml picks the command); `web` is
# Nginx with the public files. Neither contains dev dependencies, tests or node_modules.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=24
ARG NGINX_VERSION=1.28

# PHP with the extensions the app needs: bcmath (grade maths), intl (money and dates), gd and
# zip (PhpSpreadsheet, dompdf images), pcntl (Horizon), MySQL and PostgreSQL drivers, phpredis.
FROM php:${PHP_VERSION}-fpm-alpine AS php-base

ARG PHPREDIS_VERSION=6.2.0

RUN apk add --no-cache fcgi freetype icu-libs libjpeg-turbo libpng libpq libwebp libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS freetype-dev icu-dev libjpeg-turbo-dev \
        libpng-dev libpq-dev libwebp-dev libzip-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl opcache pcntl pdo_mysql pdo_pgsql zip \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/zz-schooldesk.ini"
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-schooldesk.conf

WORKDIR /var/www/html

# Composer dependencies without dev packages (Scribe, Sail, Faker, Pest ...).
FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist

# Vite build. Ziggy's Vue plugin is imported from vendor/, and the translations from lang/.
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
COPY lang ./lang
COPY public ./public
COPY --from=vendor /var/www/html/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
RUN npm run build

FROM php-base AS app

COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=vendor /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=assets /app/public/build ./public/build

# The storage tree is excluded from the build context; recreate it (storage/app is a volume).
RUN mkdir -p storage/app/private storage/app/public storage/fonts storage/logs \
        storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && rm /usr/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/schooldesk-entrypoint

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
    CMD SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["schooldesk-entrypoint"]
CMD ["php-fpm"]

# Nginx serves the built assets, fonts and API docs itself and passes everything else to PHP-FPM.
FROM nginx:${NGINX_VERSION}-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
    CMD wget -qO /dev/null http://127.0.0.1/nginx-health || exit 1
