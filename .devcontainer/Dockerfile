# Environnement GitHub Codespaces : PHP 8.3 avec les extensions de l'application.
FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libzip-dev libicu-dev sqlite3 \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd zip intl exif \
    && rm -rf /var/lib/apt/lists/*
