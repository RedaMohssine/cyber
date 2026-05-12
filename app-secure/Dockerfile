FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite headers

# Configuration PHP DURCIE
RUN { \
    echo "session.use_strict_mode = 1"; \
    echo "session.use_only_cookies = 1"; \
    echo "session.use_trans_sid = 0"; \
    echo "session.cookie_httponly = 1"; \
    echo "session.cookie_secure = 0"; \
    echo "session.cookie_samesite = \"Strict\""; \
    echo "session.cookie_lifetime = 0"; \
    echo "session.gc_maxlifetime = 1800"; \
    echo "session.name = SBSID"; \
    echo "session.sid_length = 64"; \
    echo "session.sid_bits_per_character = 6"; \
    echo "display_errors = Off"; \
    echo "expose_php = Off"; \
    } > /usr/local/etc/php/conf.d/zz-secure.ini

COPY public/ /var/www/html/
COPY src/ /var/www/src/
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data /var/www \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
