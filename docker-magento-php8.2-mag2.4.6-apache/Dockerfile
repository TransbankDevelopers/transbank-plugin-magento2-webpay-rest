FROM php:8.2-apache

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_MEMORY_LIMIT=2048M
ENV MAGENTO_VERSION=2.4.6

# Revisar dependecias segun la versión de Magento 
# https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/system-requirements.html

# Install dependencies
RUN apt-get update \
  && apt-get install -y \
    libfreetype6-dev \ 
    libicu-dev \ 
    libjpeg62-turbo-dev \ 
    libmcrypt-dev \ 
    libpng-dev \ 
    libxslt1-dev \ 
    sendmail-bin \ 
    sendmail \ 
    sudo \ 
    curl \
    cron \
    vim \
    libzip-dev \ 
    libonig-dev

# Configure the gd library
RUN docker-php-ext-configure \
  gd --with-freetype --with-jpeg

# Install required PHP extensions
RUN docker-php-ext-install \
  dom \ 
  gd \ 
  intl \ 
  mbstring \ 
  pdo_mysql \ 
  xsl \ 
  zip \ 
  soap \ 
  bcmath \ 
  pcntl \ 
  sockets    

# Install composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/* \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"  

RUN docker-php-ext-install mysqli

# Configuramos el archivo php.ini
RUN echo "memory_limit = $PHP_MEMORY_LIMIT" > /tmp/php_config && \
    echo "realpath_cache_size=10M" >> /tmp/php_config && \
    echo "realpath_cache_ttl=7200" >> /tmp/php_config && \
    cat /tmp/php_config >> /usr/local/etc/php/php.ini && \
    rm /tmp/php_config

WORKDIR /var/www/html

# Se descarga el repositorio de Magento y se usa el tag de la versión que se quiere usar
RUN git config --global --add safe.directory /var/www/html
RUN git clone https://github.com/magento/magento2.git . && git checkout $MAGENTO_VERSION
RUN a2enmod rewrite
RUN a2enmod rewrite headers

# Instalamos las dependencias del plugin
RUN composer require transbank/transbank-sdk:~2.0
RUN composer require tecnickcom/tcpdf:6.3

# Instalamos las dependencias de todo
RUN composer install

# Creamos la carpeta donde debe ir el plugin
RUN mkdir -p /var/www/html/app/code/Transbank/Webpay

# Establecemos los permisos de las carpetas, esto es muy importante o todo falla
# https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/prerequisites/file-system/configure-permissions.html?lang=es
RUN find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} +
RUN find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} +
RUN chown -R :www-data .
RUN chmod u+x bin/magento

# Copiamos el script de instalación de Magento
COPY magento_install.sh /var/www/html
# Reemplazamos los caracteres windows a caracteres linux
RUN sed -i 's/<CRLF>/\n/g' magento_install.sh
RUN chmod +x magento_install.sh

# Copiamos el script de instalación del plugin
COPY plugin_install.sh /var/www/html
# Reemplazamos los caracteres windows a caracteres linux
RUN sed -i 's/<CRLF>/\n/g' plugin_install.sh
RUN chmod +x plugin_install.sh

# Change CMD to have cron running
RUN echo "#!/bin/sh\ncron\n/usr/local/bin/apache2-foreground" > /usr/bin/run
RUN chmod u+x /usr/bin/run

EXPOSE 80



