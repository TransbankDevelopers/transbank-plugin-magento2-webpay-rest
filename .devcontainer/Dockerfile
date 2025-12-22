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
 && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ---------- Apache config ----------
RUN a2enmod rewrite proxy proxy_fcgi headers
COPY .devcontainer/apache.conf /etc/apache2/sites-available/000-default.conf

RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf \
    && sed -i 's/export APACHE_RUN_USER=www-data/export APACHE_RUN_USER=www-data/g' /etc/apache2/envvars \
    && sed -i 's/export APACHE_RUN_GROUP=www-data/export APACHE_RUN_GROUP=www-data/g' /etc/apache2/envvars

RUN mkdir -p /var/lock/apache2 /var/run/apache2 /var/www/html
RUN chown -R www-data:www-data /var/lock/apache2 /var/run/apache2 /var/www/html
RUN chmod -R 777 /var/lock/apache2 /var/run/apache2 /var/www/html

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

USER www-data

EXPOSE 8080

CMD ["bash", "-c", "php-fpm -D && . /etc/apache2/envvars && exec /usr/sbin/apache2 -DFOREGROUND"]
