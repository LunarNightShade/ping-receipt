# syntax=docker/dockerfile:1

# ---------- Stage 1: build the front-end assets (Vite + Tailwind) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------- Stage 2: install PHP dependencies (Composer) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --ignore-platform-reqs: the composer image may lack intl/mbstring; the
# runtime image below provides them. Locked versions are installed as-is.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-reqs \
    --optimize-autoloader

# ---------- Stage 3: runtime ----------
FROM dunglas/frankenphp:1-php8.3

# Use the production php.ini shipped inside the image.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Extensions used by mike42/escpos-php for text encoding.
RUN install-php-extensions intl mbstring

# Safe production defaults (override in compose.yml if you need to debug).
# FrankenPHP serves /app/public; SERVER_NAME=:80 listens on port 80.
ENV APP_ENV=production \
    APP_DEBUG=false \
    SERVER_NAME=:80

WORKDIR /app

# Application source. vendor/ and public/build are excluded by .dockerignore
# and copied in from the build stages so we always ship a clean, built set.
COPY . /app
COPY --from=vendor /app/vendor /app/vendor
COPY --from=assets /app/public/build /app/public/build

# Startup script: prepares .env, app key and database, then starts the server.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
