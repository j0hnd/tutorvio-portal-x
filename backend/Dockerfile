# Use the official PHP 8.4 image
FROM php:8.4-cli

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . .

# Install dependencies
RUN composer install
RUN npm install

# Copy existing application directory permissions
COPY --chown=www-data:www-data . .

# Expose port 8000
EXPOSE 8000

# Start the Laravel development server
CMD php artisan serve --host=0.0.0.0 --port=8000
