# Base oficial multi-arch (tiene linux/arm64 y linux/amd64)
FROM php:8.3-fpm

# Dependencias del sistema para Bagisto
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
    libonig-dev libxml2-dev libicu-dev default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql mbstring bcmath gd zip intl exif pcntl opcache calendar \
 && pecl install redis && docker-php-ext-enable redis \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 20 (para compilar assets del tema)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-bagisto.ini

WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]
