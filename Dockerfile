FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP 8.2-cli já vem com todas as extensões necessárias (mbstring, etc)

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

# Copy application files
COPY . .

# Generate autoload files
RUN composer dump-autoload --optimize

# Set permissions
RUN chmod -R 755 /var/www/html

CMD ["php", "-a"]
