# Empaqueta NAVISSI Inventario (PHP + SQLite) para correr en un NAS
# (Synology/QNAP con Docker) o cualquier VPS Linux, sin depender del PC de TI.
#
# USO:
#   docker build -t navissi-inventario .
#   docker run -d --name navissi -p 8099:80 -v navissi_data:/var/www/html/data navissi-inventario
#   (o mejor: usa docker-compose.yml junto a este archivo)

FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev libzip-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_sqlite zip curl mbstring \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/data && chmod -R 775 /var/www/html/data

EXPOSE 80
