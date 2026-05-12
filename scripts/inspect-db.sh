#!/usr/bin/env bash
# scripts/inspect-db.sh — preuve par la BDD : SID partagé attaquant + victime
set -e

echo "=== Table sessions de la BDD VULNÉRABLE ==="
docker exec -it p03-mysql mysql -uroot -prootpass -e "
    SELECT
        SUBSTRING(sid, 1, 32) AS sid,
        user_id,
        ip_address,
        LEFT(user_agent, 40) AS ua,
        last_seen
    FROM bank_vuln.sessions
    ORDER BY last_seen DESC;
"

echo
echo "=== Détection : SID partagé entre 2+ IPs ==="
docker exec -it p03-mysql mysql -uroot -prootpass -e "
    SELECT
        SUBSTRING(s.sid,1,24) AS sid,
        GROUP_CONCAT(DISTINCT s.ip_address) AS ips,
        COUNT(DISTINCT s.ip_address) AS ip_count,
        u.username
    FROM bank_vuln.sessions s
    LEFT JOIN bank_vuln.users u ON u.id = s.user_id
    GROUP BY s.sid
    HAVING ip_count >= 2;
"

echo
echo "=== Audit log côté SÉCURISÉ (anomalies détectées) ==="
docker exec -it p03-mysql mysql -uroot -prootpass -e "
    SELECT created_at, event, severity, user_id, SUBSTRING(sid,1,16) AS sid, ip_address
    FROM bank_secure.audit_log
    WHERE severity IN ('warning','critical')
    ORDER BY id DESC LIMIT 20;
"
