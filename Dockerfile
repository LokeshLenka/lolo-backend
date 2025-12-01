# -------- Base Image --------
FROM php:8.2-fpm

# -------- System Tools & PHP Extensions --------
RUN apt-get update && apt-get install -y \
    nginx \
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
    tzdata \
    nano \
    libmagickwand-dev --no-install-recommends \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl gd \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# -------- File Upload Size --------
RUN echo "upload_max_filesize=100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/uploads.ini

# -------- Timezone --------
ENV TZ=Asia/Kolkata
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && echo "date.timezone = Asia/Kolkata" > /usr/local/etc/php/conf.d/timezone.ini

# -------- Composer --------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# -------- Working Directory --------
WORKDIR /var/www

# -------- Copy Project --------
COPY . .

# -------- Install Dependencies --------
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# -------- Laravel Storage & Permissions --------
RUN mkdir -p /run/php /var/lib/nginx /var/log/nginx && \
    chmod -R 775 /var/lib/nginx /var/log/nginx /run/php && \
    chown -R www-data:www-data .

# -------- Copy nginx.conf --------
COPY nginx.conf /etc/nginx/conf.d/default.conf

# -------- Laravel Optimization --------
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan storage:link || true

# -------- Expose Port --------
EXPOSE 8000

# -------- Start Services --------
CMD sh -c "php-fpm & nginx -g 'daemon off;'"



# prodiction specific commands

# add php artisan commands
# RUN php artisan config:cache
# RUN php artisan route:cache
# RUN php artisan view:cache

# php artisan key:generate --force
# RUN php artisan storage:link
# RUN php artisan migrate --force
# RUN php artisan db:seed --force
