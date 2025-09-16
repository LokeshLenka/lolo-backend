FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    mariadb-client \
    git \
    curl \
    wget \
    unzip \
    nano \
    tzdata \
    libmagickwand-dev --no-install-recommends \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
       pdo \
       pdo_mysql \
       mbstring \
       zip \
       exif \
       pcntl \
       gd \
    && pecl install imagick || pecl install imagick \
    && docker-php-ext-enable imagick \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Increase file upload limit for PHP
RUN echo "upload_max_filesize=100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/uploads.ini


# Set timezone for the container
ENV TZ=Asia/Kolkata
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure PHP timezone
RUN echo "date.timezone = Asia/Kolkata" > /usr/local/etc/php/conf.d/timezone.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set build arguments for user and group (default to 1000)
ARG UID=1000
ARG GID=1000

# Create a user matching host user
RUN groupadd -g ${GID} appgroup && useradd -u ${UID} -g appgroup -m appuser

# Set working directory
WORKDIR /var/www

# Copy app source code
COPY . .

# Set proper permissions and ownership recursively
RUN chown -R appuser:www-data /var/www && \
    find /var/www -type f -exec chmod 664 {} \; && \
    find /var/www -type d -exec chmod 775 {} \;

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Generate application key if not set
RUN if [ ! -f .env ]; then cp .env.example .env; fi \
    && php artisan key:generate --force

# Cache config, routes, and views for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Link storage and run migrations (optional: comment out if you want to run migrations manually)
RUN php artisan storage:link
# RUN php artisan migrate --force
# RUN php artisan db:seed --force

# Use the created user for the container
USER appuser

EXPOSE 9000
CMD ["php-fpm"]


# prodiction specific commands

# add php artisan commands
# RUN php artisan config:cache
# RUN php artisan route:cache
# RUN php artisan view:cache

# php artisan key:generate --force
# RUN php artisan storage:link
# RUN php artisan migrate --force
# RUN php artisan db:seed --force
