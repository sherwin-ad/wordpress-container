FROM wordpress:latest

COPY --chown=www-data:www-data wp-content/ /var/www/html/wp-content/
