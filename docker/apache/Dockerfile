###############################################################################
# Dockerfile — PHP 8.2 + Apache for Laravel (SIMPEG)
###############################################################################
FROM php:8.2-apache

LABEL maintainer="SIMPEG Dev Team"

# ── System dependencies ─────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        zip \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libicu-dev \
        libcurl4-openssl-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        xml \
        curl \
    && rm -rf /var/lib/apt/lists/*

# ── Redis extension via PECL ────────────────────────────────────────────────
RUN pecl install redis \
    && docker-php-ext-enable redis

# ── Apache modules & configuration ──────────────────────────────────────────
RUN a2enmod rewrite headers

# Copy the custom vhost that points DocumentRoot to /var/www/html/public
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Set the global ServerName to suppress the "Could not determine FQDN" warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ── Custom php.ini overrides ────────────────────────────────────────────────
COPY php.ini /usr/local/etc/php/conf.d/99-custom.ini

# ── Composer ────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Working directory ───────────────────────────────────────────────────────
WORKDIR /var/www/html

# ── File permissions ────────────────────────────────────────────────────────
# Ensure www-data owns the Laravel storage & cache directories at runtime.
# The actual project files are bind-mounted, so we fix perms at container start.
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
