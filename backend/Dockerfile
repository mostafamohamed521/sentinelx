# syntax=docker/dockerfile:1

# SentinelX backend (Laravel 13 / PHP 8.3) - production image for Back4app Containers.
#
# Runtime: PHP built-in CLI web server (php artisan serve), matching the
# project's existing Procfile. No Nginx/php-fpm/supervisor process manager is
# introduced here so the container remains a single, simple process.

FROM php:8.3-cli-alpine

WORKDIR /app

# --- System packages & PHP extensions ------------------------------------
# Extensions installed reflect the app's actual production dependencies:
#   - pdo_pgsql / pgsql : DB_CONNECTION=pgsql (config/database.php, .env.example)
#   - mbstring, ctype, dom, fileinfo, filter, session, tokenizer, xml,
#     openssl, hash        : required by laravel/framework (composer.lock)
#   - sodium                : required by lcobucci/jwt (php-open-source-saver/jwt-auth dependency)
#   - curl                  : outbound HTTP to the ML service (MLClient / ML_SERVICE_URL)
#   - redis (PECL)          : SESSION_DRIVER=redis, CACHE_STORE=redis, REDIS_CLIENT=phpredis
#   - opcache, pcntl        : PHP performance / signal handling in production
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        oniguruma-dev \
        libsodium-dev \
        curl-dev \
        libxml2-dev \
        linux-headers; \
    apk add --no-cache \
        postgresql-libs \
        libsodium \
        libcurl; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        dom \
        xml \
        sodium \
        curl \
        opcache \
        pcntl \
        bcmath; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps; \
    rm -rf /tmp/pear /var/cache/apk/*

# Production-tuned PHP/OPcache settings.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.jit_buffer_size=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-production.ini; \
    { \
        echo 'memory_limit=256M'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/production.ini

# --- Composer --------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Application dependencies (cached layer keyed on lock files) -----------
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

# --- Application source ------------------------------------------------
# Copied explicitly (rather than `COPY . .`) so build-context-only files
# such as .env, .git, tests/, and docs/ never end up baked into the image.
COPY artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY storage ./storage

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative; \
    composer run-script post-autoload-dump --no-interaction

# Laravel needs these writable at runtime; ensure they exist and are owned
# by the user the built-in server runs as (www-data ships in this image).
RUN mkdir -p storage/framework/{cache,cache/data,sessions,testing,views} storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

USER www-data

# Back4app Containers routes traffic to the port the container listens on;
# it is provided at runtime via the PORT env var (falls back to 8080 to
# match the project's existing Procfile).
ENV PORT=8080
EXPOSE 8080

# Note: routes/web.php's default "/" route uses a Closure, which Laravel
# cannot serialize via `route:cache`, so only config is cached here.
CMD ["sh", "-c", "php artisan config:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
