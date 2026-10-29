FROM php:8.2-apache

# PostgreSQL driver + curl (used by the Google Sheets webhook)
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libcurl4-openssl-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# Render tells the container which port to use through $PORT (default 10000)
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000
