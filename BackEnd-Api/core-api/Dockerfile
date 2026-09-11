FROM php:8.2-cli

# System deps (added netcat for DB health check)
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip \
    default-mysql-client libfreetype6-dev libjpeg62-turbo-dev libwebp-dev netcat-openbsd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs 2>&1 || \
    (echo "Lock outdated, stripping require-dev and regenerating..." && \
     php -r '$j=json_decode(file_get_contents("composer.json"),true); unset($j["require-dev"]); file_put_contents("composer.json",json_encode($j,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));' && \
     composer update --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --with-all-dependencies)

COPY . .

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Cloud Run uses PORT env (8080), locally we default to 8000
EXPOSE 8080 8000
ENTRYPOINT ["/entrypoint.sh"]
