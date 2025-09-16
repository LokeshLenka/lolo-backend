# Dockerfile - single-container for Render (nginx + php-fpm + supervisor)
FROM php:8.2-fpm

ARG DEBIAN_FRONTEND=noninteractive

# --- System packages and PHP extensions ---
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    git \
    curl \
    wget \
    unzip \
    nano \
    mariadb-client \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    tzdata \
    build-essential \
    libmagickwand-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
       pdo \
       pdo_mysql \
       mbstring \
       zip \
       exif \
       pcntl \
       gd \
    && pecl install imagick || true \
    && docker-php-ext-enable imagick || true \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Timezone ---
ENV TZ=Asia/Kolkata
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN echo "date.timezone = Asia/Kolkata" > /usr/local/etc/php/conf.d/timezone.ini

# --- Composer (copy from official image) ---
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- Workdir ---
WORKDIR /var/www

# --- Optimize layer caching for composer: copy composer files first ---
COPY composer.json composer.lock* /var/www/

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader || true

# --- Copy application code ---
COPY . /var/www

# --- Remove default nginx site (important on some images) and load our config ---
RUN rm -f /etc/nginx/conf.d/default.conf || true
COPY ./nginx.conf /etc/nginx/conf.d/default.conf

# --- Supervisor config & startup script ---
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./start-container.sh /usr/local/bin/start-container.sh
RUN chmod +x /usr/local/bin/start-container.sh

# --- Permissions ---
RUN chown -R www-data:www-data /var/www \
    && find /var/www -type f -exec chmod 664 {} \; \
    && find /var/www -type d -exec chmod 775 {} \;

# --- Laravel optimizations (re-run after code copy) ---
RUN composer dump-autoload --optimize || true
RUN php artisan config:cache || true
RUN php artisan route:cache || true
RUN php artisan view:cache || true || true

EXPOSE 80

# Use the start script. It runs migrations then supervisord.
CMD ["bash", "/usr/local/bin/start-container.sh"]
