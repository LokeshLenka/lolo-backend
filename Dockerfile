FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    nano \
    libzip-dev \
    libpq-dev \
    mariadb-client \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set build arguments for user and group (default to 1000)
ARG UID=1000
ARG GID=1000

# Set working directory
WORKDIR /var/www

# Copy app source code
COPY . .

# Set permissions for development (host user)
RUN chown -R ${UID}:${GID} /var/www \
    && chmod -R ug+rwX /var/www/storage /var/www/bootstrap/cache

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 9000
CMD ["php-fpm"]
