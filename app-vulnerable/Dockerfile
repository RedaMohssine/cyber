FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite headers

# Configuration PHP volontairement permissive
RUN { \
    echo "session.use_strict_mode = 0"; \
    echo "session.use_only_cookies = 0"; \
    echo "session.use_trans_sid = 1"; \
    echo "session.cookie_httponly = 0"; \
    echo "session.cookie_secure = 0"; \
    echo "session.cookie_samesite = \"\""; \
    echo "session.cookie_lifetime = 0"; \
    echo "session.gc_maxlifetime = 86400"; \
    echo "session.name = PHPSESSID"; \
    echo "display_errors = On"; \
    echo "error_reporting = E_ALL"; \
    echo "expose_php = On"; \
    } > /usr/local/etc/php/conf.d/zz-vulnerable.ini

COPY public/ /var/www/html/
COPY src/ /var/www/src/
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data /var/www \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
