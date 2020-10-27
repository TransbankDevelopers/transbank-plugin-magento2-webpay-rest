FROM php:7.1-apache-stretch

# Install System Dependencies
RUN apt-get update \
	&& DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
	software-properties-common \
	&& apt-get update \
	&& DEBIAN_FRONTEND=noninteractive apt-get install -y \
	libfreetype6-dev \
	libicu-dev \
  	libssl-dev \
	libjpeg62-turbo-dev \
    libmcrypt-dev \
	libedit-dev \
	libedit2 \
	libxslt1-dev \
	apt-utils \
	gnupg \
	default-mysql-client \
	git \
	nano \
	wget \
	curl \
	lynx \
	psmisc \
	unzip \
	tar \
	cron \
	bash-completion \
	sudo \
	&& apt-get clean

# Install Magento Dependencies
RUN docker-php-ext-configure \
  	gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/; \
  	docker-php-ext-install \
  	opcache \
  	gd \
  	bcmath \
  	intl \
  	mbstring \
    mcrypt \
  	pdo_mysql \
  	soap \
  	xsl \
  	zip

# Install oAuth
RUN apt-get update \
  	&& apt-get install -y \
  	libpcre3 \
  	libpcre3-dev \
  	# php-pear \
  	&& pecl install oauth \
  	&& echo "extension=oauth.so" > /usr/local/etc/php/conf.d/docker-php-ext-oauth.ini

# Install Node, NVM, NPM and Grunt
RUN curl -sL https://deb.nodesource.com/setup_6.x | bash - \
  	&& apt-get install -y nodejs build-essential \
    && curl https://raw.githubusercontent.com/creationix/nvm/v0.16.1/install.sh | sh

# Install Composer
RUN	curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer
RUN composer global require hirak/prestissimo

# Install Code Sniffer
RUN git clone https://github.com/magento/marketplace-eqp.git ~/.composer/vendor/magento/marketplace-eqp
RUN cd ~/.composer/vendor/magento/marketplace-eqp && composer install
RUN ln -s ~/.composer/vendor/magento/marketplace-eqp/vendor/bin/phpcs /usr/local/bin;

ENV PATH="/var/www/.composer/vendor/bin/:${PATH}"

# Configuring system
ADD .docker/config/php.ini /usr/local/etc/php/php.ini
ADD .docker/config/magento.conf /etc/apache2/sites-available/magento.conf
COPY .docker/bin/* /usr/local/bin/
COPY .docker/users/* /var/www/
RUN chmod +x /usr/local/bin/*
RUN ln -s /etc/apache2/sites-available/magento.conf /etc/apache2/sites-enabled/magento.conf

RUN curl -o /etc/bash_completion.d/m2install-bash-completion https://raw.githubusercontent.com/yvoronoy/m2install/master/m2install-bash-completion
RUN echo "source /etc/bash_completion" >> /root/.bashrc
RUN echo "source /etc/bash_completion" >> /var/www/.bashrc

#set sudo access
RUN adduser www-data sudo
RUN echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

RUN chmod 777 -Rf /var/www /var/www/.*
RUN chown -Rf www-data:www-data /var/www /var/www/.*
RUN usermod -u 1000 www-data
RUN chsh -s /bin/bash www-data
RUN a2enmod rewrite
RUN a2enmod headers

RUN mkdir -p /var/www/plugin

VOLUME /var/www/html
WORKDIR /var/www/html
