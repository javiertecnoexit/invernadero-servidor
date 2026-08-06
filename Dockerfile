# ============================================================
# Dockerfile — API + Dashboard del invernadero (PHP 8 + Apache)
# Se usa en EasyPanel para construir el contenedor desde este repo.
# ============================================================

FROM docker.1ms.run/library/php:8.2-apache

# Habilita la extensión de MySQL (PDO)
RUN docker-php-ext-install pdo pdo_mysql && \
    a2enmod rewrite

# Copia el código a la raíz web de Apache
COPY . /var/www/html/

# Permisos correctos
RUN chown -R www-data:www-data /var/www/html
