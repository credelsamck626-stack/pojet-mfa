FROM php:8.2-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN php artisan key:generate --force

EXPOSE 8000

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT