FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV PORT=8000

# System dependencies
RUN apt-get update && apt-get install -y \
    curl git unzip zip gnupg ca-certificates \
    libpng-dev libzip-dev libicu-dev libxml2-dev libexif-dev \
    libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    libmagickwand-dev \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install \
    bcmath \
    exif \
    intl \
    pcntl \
    pdo \
    pdo_pgsql \
    pgsql \
    sockets \
    xml \
    zip

# GD (with JPEG/PNG/WebP) for general image work
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd

# Imagick for avatar thumbnailing — its reduced-resolution JPEG decode (jpeg:size) keeps
# huge photos from OOM-killing the container the way a full GD decode would.
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Node.js 22 for Vite assets
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application
COPY . .

# Install PHP dependencies (no dev, optimized autoloader)
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

# Install and build frontend assets
RUN npm ci && npm run build && rm -rf node_modules

# Storage and cache directories
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE ${PORT}

CMD ["/start.sh"]
