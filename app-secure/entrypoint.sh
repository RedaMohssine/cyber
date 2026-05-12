#!/bin/sh
set -e

echo "[entrypoint] waiting for MySQL bank_secure to be ready..."
i=0
while [ $i -lt 60 ]; do
    if php -r "new PDO('mysql:host=mysql;dbname=bank_secure','bankuser','bankpass');" 2>/dev/null; then
        echo "[entrypoint] bank_secure ready after ${i}*2s"
        break
    fi
    sleep 2
    i=$((i+1))
done

# Vérifie aussi que la table users existe (= init script terminé)
i=0
while [ $i -lt 30 ]; do
    if php -r "
        \$p = new PDO('mysql:host=mysql;dbname=bank_secure','bankuser','bankpass');
        \$p->query('SELECT 1 FROM users LIMIT 1');
    " 2>/dev/null; then
        echo "[entrypoint] schema ready"
        break
    fi
    sleep 1
    i=$((i+1))
done

php /var/www/src/bootstrap_passwords.php || echo "[entrypoint] bootstrap warning (non-fatal)"

exec apache2-foreground
