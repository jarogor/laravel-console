FROM php:7.3-cli

RUN ln -snf /usr/share/zoneinfo/Europe/Moscow /etc/localtime \
    && echo Europe/Moscow > /etc/timezone

RUN apt-get update && apt-get install -y \
    # The libjpeg-turbo JPEG library is a library for handling JPEG files.
    libjpeg62-turbo-dev \
    # libpng is a library implementing an interface for reading and writing PNG (Portable Network Graphics) format files.
    libpng-dev \
    # header files for libpq5 (PostgreSQL library)
    libpq-dev \
    # Development files for the GNOME XML library
    libxml2-dev \
    # XSLT 1.0 processing library
    libxslt-dev \
    # compression library
    zlib1g-dev \
    # library for reading, creating, and modifying zip archives
    libzip4 \
    libzip-dev \
    # Development files for International Components for Unicode
    # need for intl
    libicu-dev \
    # FreeType is a freely available software library to render fonts.
    libfreetype6-dev \
    # Others
    curl \
    libmemcached-dev \
    git

RUN docker-php-ext-install -j$(nproc) mysqli opcache pcntl pdo_mysql pdo_pgsql pgsql shmop soap xsl zip \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && pecl install memcached \
    && docker-php-ext-enable memcached

COPY 99-xdebug.ini /usr/local/etc/php/conf.d/

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin --filename=composer --quiet

ENV COMPOSER_ALLOW_SUPERUSER 1

WORKDIR /var/www

RUN usermod -u 1000 www-data
RUN chown -R www-data:www-data /var/www