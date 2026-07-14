# ============================================================
# Stage 1: Install PHP production vendor
# Must run BEFORE frontend because app.css imports CSS files
# from vendor/ (flux.css) and uses @source to scan vendor
# blade templates for Tailwind class detection.
# wikimedia/composer-merge-plugin runs as a Composer plugin
# and requires Modules/ to merge each module's composer.json.
# --no-scripts skips post-autoload-dump hooks that need a
# full Laravel bootstrap.
# ============================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY Modules/ Modules/

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --optimize-autoloader \
        --prefer-dist \
        --ignore-platform-reqs


# ============================================================
# Stage 2: Build frontend assets (Vite 8 + Tailwind CSS 4)
# node:20-slim (Debian/glibc) matches the -linux-x64-gnu
# native binaries declared in package-lock.json optionalDeps.
# vendor/ is copied from stage 1 because app.css references:
#   @import '../../vendor/livewire/flux/dist/flux.css'
#   @source '../../vendor/livewire/flux/stubs/...'
#   @source '../../vendor/laravel/framework/...'
# ============================================================
FROM node:20-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=vendor /app/vendor vendor/

COPY vite.config.js vite-module-loader.js modules_statuses.json ./
COPY resources/ resources/
COPY Modules/ Modules/

RUN npm run build


# ============================================================
# Stage 3: Production PHP-FPM runtime
# ============================================================
FROM php:8.4-fpm-alpine AS production

# Align www-data uid/gid with Ubuntu host (uid=33, gid=33).
# The bind-mounted ./storage is owned tic-gcb:www-data (gid=33)
# with g+w, so php-fpm workers must run as uid=33 to write files.
# Alpine's default www-data is uid=82 — we replace it here.
RUN deluser www-data 2>/dev/null || true \
    && delgroup www-data 2>/dev/null || true \
    && addgroup -g 33 -S www-data \
    && adduser -u 33 -S -D -H -G www-data -s /sbin/nologin www-data

# Install system runtime libraries (permanent) + build-time headers (virtual).
# postgresql-dev kept permanent: on Alpine it bundles libpq.so (no separate
# runtime-only package), so removing it after build breaks pdo_pgsql at runtime.
RUN apk add --no-cache \
        su-exec \
        fcgi \
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
        libzip \
        icu-libs \
        postgresql-dev \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        exif \
        pcntl \
    && docker-php-ext-enable opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

# PHP runtime configuration
COPY docker/php/opcache.ini  /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/php.ini      /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/zzz-app.conf /usr/local/etc/php-fpm.d/zzz-app.conf

WORKDIR /var/www/html

# Copy project source (.dockerignore excludes vendor, node_modules, .env, storage/, etc.)
COPY --chown=www-data:www-data . .

# Overlay production vendor and compiled frontend assets
COPY --from=vendor  --chown=www-data:www-data /app/vendor       ./vendor/
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build/

# Ensure full storage directory tree for Docker volume seeding on first run
RUN mkdir -p \
        storage/app/private/actas \
        storage/app/private/documentos-identidad \
        storage/app/private/firmas-investigador \
        storage/app/private/livewire-tmp \
        storage/app/public/depositos \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]


# ============================================================
# Stage 4: Nginx web server with static assets baked in
# All public/ files are embedded at build time — no shared
# volume needed for CSS/JS. User uploads are served via the
# bind-mounted storage volume (alias directive in nginx conf).
# ============================================================
FROM nginx:alpine AS nginx-stage

RUN rm /etc/nginx/conf.d/default.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY --from=production /var/www/html/public /var/www/html/public

EXPOSE 80
