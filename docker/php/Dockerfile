FROM php:8.4-fpm

# System dependencies for Laravel / Filament extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    ca-certificates \
    gnupg \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Node.js 22 (frontend tooling — Vite/npm run inside this container)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Core PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        bcmath \
        intl \
        opcache \
        exif \
        pcntl

# Redis extension (client library — connects to the separate redis service)
RUN pecl install redis && docker-php-ext-enable redis

# Composer binary only — application dependencies are NOT installed at build time
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Local PHP overrides (uploads, memory, execution time)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-softphoria.ini

# Realigns storage/bootstrap-cache ownership for the www-data worker user
# on every start, since the project directory is bind-mounted from the
# Windows host and file ownership does not reliably match www-data.
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
