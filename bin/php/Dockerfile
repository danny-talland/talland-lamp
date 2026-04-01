FROM php:8.4-apache-bookworm

# Suppresses debconf complaints of trying to install apt packages interactively
ARG DEBIAN_FRONTEND=noninteractive

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Install system packages and build dependencies in one layer.
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        apt-utils \
        build-essential \
        curl \
        default-mysql-client \
        dialog \
        git \
        iputils-ping \
        libcurl4 \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libmagickwand-dev \
        libonig-dev \
        libpng-dev \
        libsqlite3-0 \
        libsqlite3-dev \
        libzip-dev \
        nano \
        openssh-client \
        openssl \
        unzip \
        wget \
        zip \
        zlib1g-dev && \
    rm -rf /var/lib/apt/lists/* && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install xdebug
RUN pecl install xdebug-3.4.5 && \
    docker-php-ext-enable xdebug && \
    mkdir -p /var/log/xdebug

# Install imagick
RUN cd /usr/local/src && \
    git clone https://github.com/Imagick/imagick && \
    cd imagick && \
    git checkout master && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    cd .. && \
    rm -rf imagick && \
    docker-php-ext-enable imagick

# Core PHP extensions
RUN docker-php-ext-install \
        bcmath \
        calendar \
        curl \
        exif \
        gettext \
        mbstring \
        mysqli \
        pdo_mysql \
        pdo_sqlite \
        zip && \
    docker-php-ext-install -j"$(nproc)" intl

RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd

# Ensure Apache/PHP can manage SSL files created through the UI.
RUN mkdir -p /etc/apache2/ssl && \
    chown root:www-data /etc/apache2/ssl && \
    chmod 775 /etc/apache2/ssl

# Suppress Apache FQDN warning inside the container.
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf && \
    a2enconf servername

COPY bin/php/start-with-ssl-watch.sh /usr/local/bin/start-with-ssl-watch.sh
RUN chmod 755 /usr/local/bin/start-with-ssl-watch.sh

# Enable Apache modules used by the stack.
RUN a2enmod ssl rewrite headers

# Cleanup build leftovers
RUN rm -rf /usr/src/*

CMD ["/usr/local/bin/start-with-ssl-watch.sh"]
