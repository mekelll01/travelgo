FROM php:8.2-apache

# Install dependencies sistem
RUN apt-get update && apt-get install -y \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install extension MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install extension MongoDB lewat PECL
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Aktifkan mod_rewrite (kalau pakai .htaccess)
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy semua source code ke folder web Apache
COPY . /var/www/html/

WORKDIR /var/www/html

# Hapus vendor lama (kalau ada) dan install ulang sesuai PHP 8.2
RUN rm -rf vendor composer.lock && composer install --no-dev --optimize-autoloader

# Set permission
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
