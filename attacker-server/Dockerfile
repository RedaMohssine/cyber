FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_sqlite \
    && mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data

COPY public/ /var/www/html/

# Permettre CORS depuis tout pour la démo
RUN a2enmod headers

EXPOSE 80
