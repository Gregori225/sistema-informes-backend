# Imagen oficial ligera de PHP 8.2 con Apache
FROM php:8.2-apache

# 1. Instalar dependencias del sistema y el driver de PostgreSQL (libpq-dev)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Habilitar el módulo rewrite de Apache para la API REST
RUN a2enmod rewrite

# 3. Configurar Apache para que la raíz pública sea la carpeta /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Permitir que el archivo .htaccess o las rutas funcionen sin restricciones
RUN echo '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# 5. Instalar Composer desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Copiar el código del backend al contenedor
WORKDIR /var/www/html
COPY . .

# 7. Instalar dependencias de PHP y generar el autoload optimizado
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 8. Render redirige el tráfico al puerto 80 por defecto
EXPOSE 80

CMD ["apache2-foreground"]