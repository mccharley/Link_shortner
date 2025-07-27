# Use PHP 8.2 with Apache as the base image
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    unzip \
    zip \
    nodejs \
    npm \
    supervisor \
    cron \
    wget \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        intl \
        gd \
        curl \
        json \
        bcmath \
        opcache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Copy Apache configuration
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/apache2.conf /etc/apache2/apache2.conf

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy cron jobs
COPY docker/cron/linkshortener-cron /etc/cron.d/linkshortener-cron
RUN chmod 0644 /etc/cron.d/linkshortener-cron \
    && crontab /etc/cron.d/linkshortener-cron

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/storage \
    && chmod -R 777 /var/www/html/logs \
    && chmod +x /var/www/html/scripts/*.sh

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node.js dependencies for admin panel assets
RUN npm install && npm run build

# Create necessary directories
RUN mkdir -p /var/www/html/storage/uploads \
    && mkdir -p /var/www/html/storage/cache \
    && mkdir -p /var/www/html/storage/sessions \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/backups

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose port 80
EXPOSE 80

# Start supervisor to manage all services
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]