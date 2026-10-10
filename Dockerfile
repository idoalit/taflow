# syntax=docker/dockerfile:1.7

# The application for production: FrankenPHP serving Laravel through Octane, one image,
# no state inside it. The database, Redis and the object store are servers of
# their own and are reached over the network.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ── base ──────────────────────────────────────────────────────────────────────
# The PHP the application runs on, with exactly the extensions it uses. Every
# later stage starts here, so what builds the application is what runs it.
FROM dunglas/frankenphp:1-php${PHP_VERSION} AS base

RUN install-php-extensions \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        zip

WORKDIR /app

# ── vendor ────────────────────────────────────────────────────────────────────
# PHP dependencies without the development ones. The manifests are copied
# before the code so a change to the code does not download everything again.
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .

# storage/ is kept out of the build context — it is where a developer's own
# uploads and logs live — so the empty tree Laravel expects is made here.
RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/caddy/config \
        storage/caddy/data \
        storage/fonts \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && php artisan package:discover --ansi

# ── assets ────────────────────────────────────────────────────────────────────
# The frontend bundle. Built on top of the PHP stage rather than a plain Node
# image because the Wayfinder plugin asks `php artisan` for the route list
# while Vite builds.
FROM node:${NODE_VERSION}-bookworm-slim AS node

FROM vendor AS assets

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund

RUN npm run build

# ── runtime ───────────────────────────────────────────────────────────────────
# What is shipped: the code, its PHP dependencies and the built assets. No
# Node, no Composer, no development packages.
FROM base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    OCTANE_SERVER=frankenphp \
    # Caddy keeps its own state here; inside storage/ so one writable tree
    # covers everything the process writes.
    XDG_CONFIG_HOME=/app/storage/caddy/config \
    XDG_DATA_HOME=/app/storage/caddy/data

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build

# The process runs as an unprivileged user, and only the directories it has to
# write to are its own; the code itself stays read-only to it.
RUN cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/frankenphp-worker.php \
    && useradd --system --uid 10001 --user-group --no-create-home app \
    && chown -R app:app bootstrap/cache storage public/frankenphp-worker.php \
    && chmod +x docker/entrypoint.sh

# The upstream binary is allowed to bind ports below 1024. This one listens on
# 8000 and never needs to — and a binary that asks for a capability cannot be
# started at all in a container that has dropped them all, which is how the
# stack runs it.
RUN setcap -r /usr/local/bin/frankenphp

USER app

EXPOSE 8000

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["serve"]
