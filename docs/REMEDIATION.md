# Guide de remédiation technique

Ce document détaille chaque contre-mesure appliquée dans `app-secure/`,
avec le diff exact, le pourquoi, et les pièges à éviter.

## 1. Régénération du Session ID après login

**Fichier** : [app-secure/public/login.php:31](../app-secure/public/login.php#L31)

```php
// AVANT (vulnérable)
if ($user && hash_equals($user['password'], $password)) {
    $_SESSION['user_id'] = (int)$user['id'];
    // ... rien d'autre
}

// APRÈS (sécurisé)
if ($user && password_verify($password, $user['password_hash'])) {
    $oldSid = session_id();
    session_regenerate_id(true);   // 'true' = destruction de l'ancienne entrée
    audit('login_success', 'info', (int)$user['id'], session_id(), [
        'old_sid' => substr($oldSid, 0, 8) . '...',
        'new_sid' => substr(session_id(), 0, 8) . '...',
    ]);
    $_SESSION['user_id'] = (int)$user['id'];
}
```

**Pourquoi** : invalide tout SID pré-auth, neutralisant V001.

**Piège classique** : appeler `session_regenerate_id()` sans `true` laisse l'ancien
SID actif côté serveur. La fixation reste exploitable.

## 2. Hardening de la configuration PHP

**Fichier** : [app-secure/Dockerfile](../app-secure/Dockerfile)

```ini
session.use_strict_mode      = 1     ; refuse les SID inconnus
session.use_only_cookies     = 1     ; refuse le SID en URL
session.use_trans_sid        = 0     ; pas de fallback URL
session.cookie_httponly      = 1     ; cookie inaccessible en JS
session.cookie_secure        = 1     ; TLS only (en prod)
session.cookie_samesite      = "Strict"
session.cookie_lifetime      = 0     ; cookie de session uniquement
session.gc_maxlifetime       = 1800  ; 30 min d'inactivité
session.name                 = SBSID ; renomme pour ne pas crier "PHP"
session.sid_length           = 64    ; 384 bits
session.sid_bits_per_character = 6
display_errors               = Off
expose_php                   = Off
```

**Pourquoi** : `use_strict_mode = 1` est la défense critique : même si V001 régressait,
le serveur n'accepterait plus le SID forgé.

## 3. Échappement contextuel systématique

**Fichier** : [app-secure/src/security.php](../app-secure/src/security.php)

```php
function e(?string $s): string {
    return htmlspecialchars((string)$s,
        ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
        'UTF-8');
}
```

Utilisé partout : `<?= e($user['full_name']) ?>` au lieu de `<?= $user['full_name'] ?>`.

**Pourquoi** :
- `ENT_QUOTES` : échappe `'` et `"` (essentiel dans les attributs)
- `ENT_SUBSTITUTE` : remplace les séquences UTF-8 invalides au lieu de renvoyer une chaîne vide
- `ENT_HTML5` : conforme aux entités HTML5

**Piège** : `htmlspecialchars` ne suffit PAS pour des contextes JavaScript ou attribut URL.
Pour ces cas, utiliser un escaper dédié (encoder JSON pour JS, urlencode pour URL).

## 4. Content-Security-Policy stricte

**Fichier** : [app-secure/src/security.php](../app-secure/src/security.php)

```php
header("Content-Security-Policy: default-src 'self'; "
     . "script-src 'self'; "                  // pas d'inline, pas d'eval
     . "style-src 'self' 'unsafe-inline'; "   // CSS inline toléré
     . "img-src 'self' data:; "
     . "object-src 'none'; "                  // bloque <object>, <embed>
     . "base-uri 'self'; "                    // bloque <base> hijack
     . "form-action 'self'; "                 // formulaires vers origine seule
     . "frame-ancestors 'none'");             // clickjacking
```

**Pourquoi** : même si une XSS réussit à passer (par exemple via une dépendance future),
la CSP empêche l'exécution d'un script inline `document.cookie=...` et bloque l'exfiltration
vers `evil.attacker.lab`.

## 5. Binding session ↔ fingerprint

**Fichier** : [app-secure/src/security.php:9](../app-secure/src/security.php#L9) +
[session_handler.php:read()](../app-secure/src/session_handler.php)

```php
function request_fingerprint(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $ip = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';  // /24
    }
    return hash('sha256', $ip . '|' . $ua);
}

// Dans le SessionHandler.read() :
if ($row['fingerprint'] && !hash_equals($row['fingerprint'], request_fingerprint())) {
    audit('session_fingerprint_mismatch', 'critical', $row['user_id'], $sid, [...]);
    $this->destroy($sid);
    return '';
}
```

**Pourquoi** : si un cookie volé est rejoué depuis une autre machine, le fingerprint
diffère → la session est détruite + alerte SIEM levée.

**Compromis** : on utilise `/24` (et non l'IP exacte) pour tolérer le NAT et les load-balancers
mobiles. C'est un équilibre faux positifs/vrais positifs.

## 6. Rotation périodique du SID

**Fichier** : [app-secure/src/session_handler.php](../app-secure/src/session_handler.php)

```php
if (!empty($_SESSION['user_id'])) {
    if (empty($_SESSION['_last_rotate']) || time() - $_SESSION['_last_rotate'] > 300) {
        session_regenerate_id(true);
        $_SESSION['_last_rotate'] = time();
    }
}
```

**Pourquoi** : limite la fenêtre d'exploitation d'un cookie compromis à 5 min.

## 7. Tokens CSRF sur toutes les mutations

**Fichier** : [app-secure/src/security.php](../app-secure/src/security.php)

```php
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));  // 256 bits
    }
    return $_SESSION['_csrf'];
}
function csrf_check(): bool {
    return !empty($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $_POST['_csrf'] ?? '');
}
```

Et dans chaque endpoint POST : `if (!csrf_check()) { http_response_code(400); die; }`.

**Pourquoi** : empêche un attaquant de déclencher des virements via une page tierce.

## 8. Hash bcrypt des mots de passe

**Fichier** : [app-secure/src/bootstrap_passwords.php](../app-secure/src/bootstrap_passwords.php)

```php
$hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
// vérification :
if (password_verify($input, $row['password_hash'])) { ... }
```

## 9. Logout complet

**Fichier** : [app-secure/public/logout.php](../app-secure/public/logout.php)

```php
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
              $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
audit('logout', 'info', $uid, $sid, []);
```

**Pourquoi** : vide `$_SESSION`, supprime le cookie côté navigateur, ET détruit la ligne en BDD.

## 10. Audit log et SIEM

Toute opération sensible appelle `audit($event, $severity, $userId, $sid, $detail)`
qui écrit en BDD + stderr. Les règles Sigma dans [siem/](../siem/) sont prêtes pour
intégration dans Splunk, ELK, ou Wazuh.

## 11. Honeypot

Le compte `admin_honeypot` n'est jamais utilisé légitimement. Toute connexion
réussie déclenche une alerte critique : c'est un signal de compromission certaine.

---

## Récap : matrice de couverture

| Vuln    | Contre-mesure                          | Couvert par         |
|---------|----------------------------------------|---------------------|
| V001    | `session_regenerate_id(true)`          | §1                  |
| V002    | `use_strict_mode = 1`                  | §2                  |
| V003    | `use_only_cookies = 1`                 | §2                  |
| V004    | `htmlspecialchars` + CSP               | §3 + §4             |
| V005    | `htmlspecialchars` sur $_GET           | §3                  |
| V006    | `HttpOnly` + `Secure` + `SameSite`     | §2                  |
| V007    | Token CSRF                              | §7                  |
| V008    | `logout.php` complet                   | §9                  |
| V009    | `password_hash` + `password_verify`    | §8                  |
| Bonus   | Binding fingerprint + rotation         | §5 + §6             |
| Bonus   | Audit + Sigma + honeypot               | §10 + §11           |
