FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libmagickwand-dev \
    default-mysql-client \
    chromium \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Browsershot (usado pelo spatie/laravel-pdf) invoca um script Node que
# exige o pacote 'puppeteer'. Saltamos o download do Chromium proprio dele
# porque ja instalamos o Chromium do sistema (apt) acima.
ENV PUPPETEER_SKIP_DOWNLOAD=true
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
RUN npm install -g puppeteer

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        exif \
        pcntl \
        gd \
        zip \
        intl \
        opcache

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} sge || true \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash sge || true

USER sge

EXPOSE 9000

CMD ["php-fpm"]
