FROM php:8-alpine

RUN apk update && \
    apk add --no-cache openssl bash mysql-client postgresql-dev && \
    docker-php-ext-install pdo pdo_mysql pdo_pgsql

WORKDIR /app

COPY . /app

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer install --no-dev --optimize-autoloader

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]