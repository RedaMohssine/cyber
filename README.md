# CaptusBank — P03 Session Fixation + XSS

Projet pédagogique d'ethical hacking : démonstration d'une chaîne d'exploitation **Session Fixation + XSS + CSRF** sur une application bancaire, avec une version sécurisée jumelle pour comparer les contre-mesures.

## Architecture

| Service | Hostname | Description |
|---|---|---|
| `app-vulnerable` | `vuln.bank.local` | Version volontairement vulnérable |
| `app-secure` | `secure.bank.local` | Version corrigée (contre-mesures appliquées) |
| `attacker-server` | `evil.attacker.lab` | C2 attaquant : dashboard + collecteur de sessions |
| `nginx` | proxy sur `:8080` | Reverse proxy routant par Host header |
| `mysql` | interne | BDD partagée (`bank_vuln` + `bank_secure`) |

```
                ┌──────────────────┐
                │   Nginx :8080    │
                └────────┬─────────┘
         ┌───────────────┼──────────────┐
         ▼               ▼              ▼
   app-vulnerable   app-secure    attacker-server
      (PHP 8.2)      (PHP 8.2)      (PHP 8.2)
         │               │              │
         └───────┬────────┘             │
                 ▼                      ▼
             MySQL                   SQLite
           (bank data)          (stolen sessions)
```

## Prérequis

- Docker + Docker Compose
- Accès administrateur (pour modifier `/etc/hosts`)
- Python 3.9+ (optionnel — pour les outils d'inspection)

```bash
pip install -r requirements.txt
```

## Installation

### 1. Configurer les hostnames

**Linux / macOS :**
```bash
echo "127.0.0.1 vuln.bank.local secure.bank.local evil.attacker.lab" | sudo tee -a /etc/hosts
```

**Windows (PowerShell en tant qu'administrateur) :**
```powershell
Add-Content -Path "C:\Windows\System32\drivers\etc\hosts" -Value "127.0.0.1 vuln.bank.local"
Add-Content -Path "C:\Windows\System32\drivers\etc\hosts" -Value "127.0.0.1 secure.bank.local"
Add-Content -Path "C:\Windows\System32\drivers\etc\hosts" -Value "127.0.0.1 evil.attacker.lab"
```

### 2. Lancer la stack

```bash
docker compose up --build -d
```

Attendre ~15 secondes que MySQL initialise les deux schémas, puis vérifier :
```bash
curl -I http://vuln.bank.local:8080
curl -I http://secure.bank.local:8080
curl -I http://evil.attacker.lab:8080
```

### 3. Comptes de test

| Rôle | Username | Password | Solde |
|---|---|---|---|
| Victime | `alice` | `Password123!` | 12 450 € |
| Utilisateur | `bob` | `BobPass456!` | 3 200 € |
| Attaquant | `mallory` | `EvilPass1!` | 5 € |
| Honeypot | `admin_honeypot` | `D0_n0t_use!` | — |

## Réinitialiser les données

```bash
docker compose down -v
docker compose up --build -d
```

## Reproduire les attaques

Voir **[docs/GUIDE-COMPLET.md](docs/GUIDE-COMPLET.md)** pour les instructions détaillées de chaque démonstration.

### Vue d'ensemble des démonstrations

| # | Attaque | Vecteur | Impact |
|---|---|---|---|
| 1A | Session Fixation | URL phishing (`?PHPSESSID=`) | Hijack de session |
| 1B | Session Fixation | XSS stocké (commentaire) | Hijack sans phishing URL |
| 2 | XSS Stocké | Commentaire → alerte JS | Preuve d'exécution JS |
| 3 | XSS Stocké → vol de cookie | `document.cookie` → beacon | Cookie exfiltré dans dashboard attaquant |
| 4 | XSS Stocké → virement | `fetch('/transfer.php?...')` | 500 € débités depuis le compte victime |
| 5 | XSS Réfléchi | `?msg=<script>` en URL | Exécution JS via lien |
| 6 | XSS Stocké → profil | Payload dans `full_name` | Persistance XSS dans la page Profil |
| 7 | CSRF | Email phishing → fausse page banque → GET transfer | Virement sans interaction JS |

## Arrêter

```bash
docker compose down
```
