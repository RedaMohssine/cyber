#!/bin/sh
set -e

echo "[entrypoint] waiting for MySQL bank_vuln to be ready..."
i=0
while [ $i -lt 60 ]; do
    if php -r "new PDO('mysql:host=mysql;dbname=bank_vuln','bankuser','bankpass');" 2>/dev/null; then
        echo "[entrypoint] bank_vuln ready"
        break
    fi
    sleep 2
    i=$((i+1))
done

i=0
while [ $i -lt 30 ]; do
    if php -r "
        \$p = new PDO('mysql:host=mysql;dbname=bank_vuln','bankuser','bankpass');
        \$p->query('SELECT 1 FROM users LIMIT 1');
    " 2>/dev/null; then
        echo "[entrypoint] schema ready"
        break
    fi
    sleep 1
    i=$((i+1))
done

exec apache2-foreground
