# syntax=docker/dockerfile:1

FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

FROM php:8.4-cli-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    libsqlite3-dev \
    libzip-dev \
    nodejs \
    npm \
    chromium \
    fonts-liberation \
    fonts-noto-color-emoji \
    ca-certificates \
    && docker-php-ext-install pdo_sqlite zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS builder

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

COPY . .

COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

FROM base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    BROWSERSHOT_CHROME_PATH=/usr/bin/chromium \
    BROWSERSHOT_NODE_BINARY=/usr/bin/node \
    BROWSERSHOT_NPM_BINARY=/usr/bin/npm \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true

COPY --from=builder /var/www/html /var/www/html

RUN npm ci --ignore-scripts --omit=dev \
    && rm -rf /root/.npm

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache database \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache database

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8000/ >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
