# P03 — Session Fixation combinée à XSS

> Projet cybersécurité — **BLUE TEAM**
> Démonstration d'une chaîne d'exploitation **Session Fixation + XSS** sur une application bancaire,
> puis remédiation complète dans une version sécurisée jumelle.

## Sommaire

- [Vue d'ensemble](#vue-densemble)
- [Architecture](#architecture)
- [Démarrage rapide](#démarrage-rapide)
- [Scénario d'attaque](#scénario-dattaque)
- [Contre-mesures](#contre-mesures)
- [Démonstration](#démonstration)
- [Livrables](#livrables)

## Vue d'ensemble

Ce projet implémente deux versions jumelles d'une application bancaire (**CaptusBank**) :

| Service | Port | Description |
|---|---|---|
| `app-vulnerable` | 8081 | Version volontairement vulnérable (XSS stocké/réfléchi, pas de régénération de session) |
| `app-secure`     | 8082 | Version corrigée appliquant toutes les contre-mesures |
| `attacker-server`| 8666 | Serveur de collecte de l'attaquant + dashboard temps réel des sessions volées |
| `mysql`          | 3306 | Base partagée (schémas `bank_vuln` et `bank_secure`) |
| `nginx`          | 80   | Reverse proxy unifiant les hostnames |

Hostnames simulés (via `/etc/hosts`) :
- `vuln.bank.local`    → app vulnérable
- `secure.bank.local`  → app sécurisée
- `evil.attacker.lab`  → infrastructure attaquant

## Architecture

```
                  ┌─────────────────────┐
                  │   Nginx reverse     │
                  │       proxy         │
                  └──────────┬──────────┘
                             │
        ┌────────────────────┼────────────────────┐
        ▼                    ▼                    ▼
 ┌────────────┐       ┌────────────┐       ┌────────────┐
 │  app-vuln  │       │ app-secure │       │  attacker  │
 │  (PHP 8.2) │       │  (PHP 8.2) │       │  (PHP 8.2) │
 └─────┬──────┘       └─────┬──────┘       └─────┬──────┘
       │                    │                    │
       └────────┬───────────┘                    │
                ▼                                ▼
        ┌──────────────┐                ┌──────────────┐
        │    MySQL     │                │  SQLite      │
        │ (bank data)  │                │  (stolen     │
        └──────────────┘                │   sessions)  │
                                        └──────────────┘
```

## Démarrage rapide

```bash
# Ajout des hostnames locaux
sudo bash scripts/setup-hosts.sh

# Lancement de la stack complète
docker compose up --build -d

# Vérification
curl -I http://vuln.bank.local
curl -I http://secure.bank.local
curl -I http://evil.attacker.lab
```

Comptes de test :
- **Victime** : `alice` / `Password123!` (solde 12 450 €)
- **Attaquant**: `mallory` / `EvilPass1!`
- **Autre user** : `bob` / `BobPass456!`

## Scénario d'attaque

Voir [docs/SCENARIO.md](docs/SCENARIO.md) pour le détail complet.

Résumé en 5 phases :
1. **Reconnaissance** — Mallory identifie l'absence de régénération de session ID
2. **Préparation** — Mallory obtient un session ID valide pré-auth
3. **Fixation** — Injection du cookie chez la victime via 3 vecteurs (URL / XSS stocké / sous-domaine)
4. **Phishing** — Email piégé envoyé à Alice
5. **Hijacking** — Une fois Alice connectée, Mallory utilise le même ID et vide le compte

## Contre-mesures

Voir [docs/REMEDIATION.md](docs/REMEDIATION.md). Liste résumée :

- `session_regenerate_id(true)` après login, logout, élévation de privilège
- Cookies `HttpOnly` + `Secure` + `SameSite=Strict`
- CSP stricte (`script-src 'self'; object-src 'none'; base-uri 'self'`)
- Échappement contextuel (`htmlspecialchars` avec ENT_QUOTES)
- Binding session ↔ User-Agent + IP /24
- Rotation périodique du SID (toutes les 5 min)
- Logging structuré des anomalies (deux IPs sur un SID → alerte SIEM)
- Token CSRF sur toutes les mutations
- Honeytoken (compte `admin_honeypot` jamais légitimement utilisé)

## Démonstration

Preuve attendue :
1. Ouverture de la BDD MySQL → table `sessions` → **un seul SID partagé** par Alice et Mallory
2. Capture Wireshark → cookie identique côté attaquant et victime
3. Dashboard attaquant → affichage temps réel du SID volé
4. Tentative sur l'app sécurisée → l'attaque échoue, alerte loguée

## Livrables

- [docs/RAPPORT.md](docs/RAPPORT.md) — Rapport style pentest (executive summary, CVSS, OWASP/CWE, remédiation)
- [docs/SCENARIO.md](docs/SCENARIO.md) — Scénario d'attaque détaillé
- [docs/THREAT-MODEL.md](docs/THREAT-MODEL.md) — Threat modeling STRIDE
- [docs/REMEDIATION.md](docs/REMEDIATION.md) — Contre-mesures détaillées
- [scripts/attack.py](scripts/attack.py) — Script Python automatisant l'attaque
- [tests/](tests/) — Tests pytest validant vuln + sécurisée
- [siem/](siem/) — Règles Sigma de détection
