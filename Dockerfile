# Use official PHP Apache image
FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/ \
    && chmod -R 777 /var/www/html/users.json \
    && chmod -R 777 /var/www/html/error.log

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]