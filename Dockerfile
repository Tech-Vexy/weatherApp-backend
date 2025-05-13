# Use the richarvey/nginx-php-fpm base image
FROM richarvey/nginx-php-fpm:3.1.6

# Copy the application code into the container
COPY . .
COPY . /var/www/html
# Image config
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

# Laravel config
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

# Allow composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER 1

# Start the container
CMD ["/start.sh"]
