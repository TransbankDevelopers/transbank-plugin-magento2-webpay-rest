FROM php:8.3-fpm-bullseye

ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_VERSION=2.9.2

RUN set -eux; \
    \
    # ---------- System dependencies ----------
    apt-get update; \
    apt-get install -y --no-install-recommends \
        apache2 \
        bash \
        ca-certificates \
        cron \
        curl \
        default-mysql-client \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libxslt1-dev \
        libzip-dev \
        netcat-openbsd \
        ripgrep \
        ssh \
        tzdata \
        unzip; \
    \
    # ---------- PHP extensions ----------
    docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        soap \
        xsl \
        zip \
        sockets \
        opcache \
        gd \
        pcntl; \
    \
    # ---------- PHP config ----------
    echo "memory_limit=2G" > /usr/local/etc/php/conf.d/magento.ini; \
    echo "max_execution_time=1800" >> /usr/local/etc/php/conf.d/magento.ini; \
    echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini; \
    echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini; \
    \
    # ---------- Apache config ----------
    echo "ServerName localhost" >> /etc/apache2/apache2.conf; \
    a2enmod rewrite proxy proxy_fcgi headers; \
    sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf; \
    \
    # ---------- Runtime dirs for apache ----------
    mkdir -p /var/lock/apache2 /var/run/apache2 /var/www/html; \
    chown -R www-data:www-data /var/lock/apache2 /var/run/apache2 /var/www/html; \
    chmod -R 775 /var/lock/apache2 /var/run/apache2 /var/www/html; \
    \
    # ---------- Cleanup ----------
    rm -rf /var/lib/apt/lists/*

COPY .devcontainer/apache.conf /etc/apache2/sites-available/000-default.conf

# ---------- composer install ----------
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --version="${COMPOSER_VERSION}" --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');" \
 && composer --version

# ---------- Magento ----------
WORKDIR /var/www/html

# ---------- Entrypoint ----------
COPY .devcontainer/magento-setup.sh /usr/local/bin/magento-setup.sh
RUN chmod +x /usr/local/bin/magento-setup.sh

USER www-data

EXPOSE 8080

CMD ["bash", "-c", "php-fpm -D && . /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND"]
