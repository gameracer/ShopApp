# ---- Frontend assets (Vite + Tailwind) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---- PHP dependencies (production only) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---- Runtime: PHP 8.3 + Apache ----
FROM php:8.3-apache

# pdo_sqlite ships with the base image; pdo_mysql only if you switch DB_CONNECTION=mysql.
RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && { \
        echo 'opcache.validate_timestamps=0'; \
        echo 'expose_php=0'; \
    } > /usr/local/etc/php/conf.d/app.ini \
    && rm -rf /var/lib/apt/lists/* /usr/src/php*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN mkdir -p /data storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite

VOLUME ["/data"]
EXPOSE 80

# On start: create APP_KEY + SQLite DB if missing, migrate, seed on first run, cache, then run Apache.
CMD ["sh", "-c", "\
    if [ -z \"$APP_KEY\" ]; then \
        [ -f /data/app.key ] || php -r 'echo \"base64:\".base64_encode(random_bytes(32));' > /data/app.key; \
        export APP_KEY=$(cat /data/app.key); \
    fi; \
    FRESH=0; [ -f \"$DB_DATABASE\" ] || { touch \"$DB_DATABASE\"; FRESH=1; }; \
    php artisan migrate --force && \
    { [ $FRESH = 0 ] || php artisan db:seed --force; } && \
    php artisan optimize && \
    chown -R www-data:www-data /data storage bootstrap/cache && \
    exec apache2-foreground"]
