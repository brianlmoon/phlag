# Phlag Docker Image
#
# This Dockerfile creates a production-ready container for the Phlag feature
# flag management system. It uses Phusion Baseimage for proper init system
# support and runs both Nginx and PHP-FPM managed by runit.
#
# ## Architecture
#
# - Base: Ubuntu 22.04 LTS (Phusion Baseimage)
# - Services: Nginx + PHP 8.4-FPM (supervised by runit)
# - Document Root: /app/public
# - Process Manager: Runit (built into Phusion baseimage)
#
# ## Environment Variables
#
# Database configuration (DB_PHLAG_ prefix):
# - DB_PHLAG_TYPE: Database type (mysql|pgsql|sqlite)
# - DB_PHLAG_HOST: Database server hostname
# - DB_PHLAG_PORT: Database server port
# - DB_PHLAG_DB: Database name
# - DB_PHLAG_USER: Database username
# - DB_PHLAG_PASS: Database password
#
# Email configuration:
# - MAILER_FROM_ADDRESS: Email sender address
# - MAILER_FROM_NAME: Email sender name (default: "Phlag Admin")
# - MAILER_METHOD: Email method (smtp|mail, default: mail)
# - SMTP_HOST: SMTP server hostname
# - SMTP_PORT: SMTP server port (default: 587)
# - SMTP_ENCRYPTION: SMTP encryption (tls|ssl, default: tls)
# - SMTP_USERNAME: SMTP authentication username
# - SMTP_PASSWORD: SMTP authentication password
#
# Application configuration:
# - SESSION_TIMEOUT: Session timeout in seconds (default: 1800)
# - PHLAG_BASE_URL_PATH: Base URL path for subdirectory installs (e.g., /phlag)
#
# ## Volumes
#
# Recommended volume mounts:
# - /app/etc: Configuration files (mount config.ini here)
# - /var/log/nginx: Nginx access and error logs
# - /var/log/php: PHP-FPM logs
#
# ## Ports
#
# - 80: HTTP (exposed)
#
# ## Usage
#
# Build:
#   docker build -t phlag:latest .
#
# Run with environment variables:
#   docker run -d \
#     -p 8000:80 \
#     -e DB_PHLAG_TYPE=mysql \
#     -e DB_PHLAG_HOST=db.example.com \
#     -e DB_PHLAG_PORT=3306 \
#     -e DB_PHLAG_DB=phlag \
#     -e DB_PHLAG_USER=phlag_user \
#     -e DB_PHLAG_PASS=secret \
#     phlag:latest
#
# Run with volume-mounted config:
#   docker run -d \
#     -p 8000:80 \
#     -v /path/to/config.ini:/app/etc/config.ini:ro \
#     phlag:latest

FROM phusion/baseimage:jammy-1.0.4

# Set environment variables for non-interactive installation
ENV DEBIAN_FRONTEND=noninteractive \
    LANG=C.UTF-8 \
    LC_ALL=C.UTF-8

# Add Ondřej Surý PHP PPA for PHP 8.4
RUN apt-get update && \
    apt-get install -y software-properties-common && \
    add-apt-repository ppa:ondrej/php && \
    apt-get update

# Install Nginx, PHP 8.4, and required extensions
RUN apt-get install -y \
    nginx \
    php8.4-fpm \
    php8.4-cli \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-pgsql \
    php8.4-mysql \
    php8.4-sqlite3 \
    php8.4-bcmath \
    php8.4-intl \
    php8.4-yaml \
    curl \
    unzip \
    git && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

# Create application directory
RUN mkdir -p /app && \
    chown -R www-data:www-data /app

# Set working directory
WORKDIR /app

# Copy application files (excluding vendor, tests, etc. via .dockerignore)
COPY --chown=www-data:www-data . /app/

# Install Composer dependencies (production only, optimized)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --prefer-dist && \
    chown -R www-data:www-data /app/vendor

# Configure PHP-FPM
COPY docker/php-fpm/pool.conf /etc/php/8.4/fpm/pool.d/www.conf

# Configure Nginx
COPY docker/nginx/phlag.conf /etc/nginx/sites-available/phlag
RUN rm -f /etc/nginx/sites-enabled/default && \
    ln -s /etc/nginx/sites-available/phlag /etc/nginx/sites-enabled/phlag

# Create runit service directories
RUN mkdir -p /etc/service/nginx /etc/service/php-fpm

# Install runit service scripts
COPY docker/runit/nginx/run /etc/service/nginx/run
COPY docker/runit/php-fpm/run /etc/service/php-fpm/run
RUN chmod +x /etc/service/nginx/run /etc/service/php-fpm/run

# Create required directories
RUN mkdir -p /app/etc \
    /var/lib/php/sessions \
    /var/log/php \
    /var/run/php && \
    chown -R www-data:www-data \
    /var/lib/php/sessions \
    /var/log/php \
    /var/run/php \
    /app/etc

# Set proper file permissions
RUN find /app -type f -exec chmod 644 {} \; && \
    find /app -type d -exec chmod 755 {} \; && \
    chown -R www-data:www-data /app

# Expose HTTP port
EXPOSE 80

# Use baseimage-docker's init system
CMD ["/sbin/my_init"]
