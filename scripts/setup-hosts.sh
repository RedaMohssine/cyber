#!/usr/bin/env bash
# scripts/setup-hosts.sh — ajoute les hostnames de la démo dans /etc/hosts
set -e

HOSTS=(
    "127.0.0.1 vuln.bank.local"
    "127.0.0.1 secure.bank.local"
    "127.0.0.1 evil.attacker.lab"
)

if [ "$EUID" -ne 0 ]; then
    echo "[!] Ce script doit être exécuté avec sudo (modification de /etc/hosts)."
    exit 1
fi

for h in "${HOSTS[@]}"; do
    if grep -qF "$h" /etc/hosts; then
        echo "[=] déjà présent : $h"
    else
        echo "$h" >> /etc/hosts
        echo "[+] ajouté : $h"
    fi
done

echo "[OK] hostnames configurés. Lance maintenant : docker compose up --build"
