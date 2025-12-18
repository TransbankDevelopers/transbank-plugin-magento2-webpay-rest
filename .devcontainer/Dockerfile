FROM php:7.4-fpm-bullseye

ARG MAGENTO_VERSION=2.4.3
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_VERSION=2.9.2

# ---------- System dependencies ----------
RUN apt-get update && apt-get install -y --no-install-recommends \
  apache2 \
  cron \
  ssh \
  libicu-dev \
  libxml2-dev \
  libzip-dev \
  libonig-dev \
  libxslt1-dev \
  libjpeg62-turbo-dev \
  libpng-dev \
  libfreetype6-dev \
  git \
  curl \
  netcat-openbsd \
  unzip \
  bash \
  ca-certificates \
  default-mysql-client \
  tzdata \
 && rm -rf /var/lib/apt/lists/*

# ---------- PHP extensions ----------
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    bcmath \
    intl \
    mbstring \
    pdo_mysql \
    soap \
    xsl \
    zip \
    sockets \
    opcache \
    gd

# ---------- PHP config ----------
RUN echo "memory_limit=2G" > /usr/local/etc/php/conf.d/magento.ini \
 && echo "max_execution_time=1800" >> /usr/local/etc/php/conf.d/magento.ini \
 && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini

# ---------- Apache config ----------
RUN a2enmod rewrite proxy proxy_fcgi headers
COPY .devcontainer/apache.conf /etc/apache2/sites-available/000-default.conf

# composer install
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --version=${COMPOSER_VERSION} --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');" \
 && composer --version

# ---------- Magento ----------
WORKDIR /var/www/html

# ---------- Entrypoint ----------
COPY .devcontainer/magento-setup.sh /usr/local/bin/magento-setup.sh
RUN chmod +x /usr/local/bin/magento-setup.sh

EXPOSE 80

CMD ["bash", "-c", "php-fpm -D && apachectl -D FOREGROUND"]
