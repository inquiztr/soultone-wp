FROM wordpress:6.9-php8.3-apache
# Create a static HTML file (No PHP, no DB needed)
# RUN echo "OK" > /var/www/html/up.html
# Ensure permissions
# RUN chown www-data:www-data /var/www/html/up.html
EXPOSE 80