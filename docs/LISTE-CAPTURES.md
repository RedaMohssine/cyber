# Liste des captures d'écran à effectuer

Mettre toutes les captures dans le dossier `docs/photos/` avec le nom exact indiqué.

---

## Général

| Fichier | Ce qu'il faut capturer |
|---|---|
| `architecture-docker.png` | Terminal : `docker compose ps` (tous les containers UP) |
| `page-accueil-vuln.png` | Page `http://vuln.bank.local:8080` — page d'accueil CaptusBank (VULNERABLE BUILD) |

---

## Démo 1A — Session Fixation via URL

| Fichier | Ce qu'il faut capturer |
|---|---|
| `demo1a-url-phpsessid.png` | Barre d'adresse montrant `login.php?PHPSESSID=ATTACKERfixedSID001` + la page de login |
| `demo1a-sid-avant-login.png` | Bas de la page login : **"SID actuel (pré-auth) : ATTACKERfixedSID001"** |
| `demo1a-sid-apres-login.png` | Dashboard d'Alice après connexion : **"Session ID courant : ATTACKERfixedSID001"** (le SID n'a PAS changé) |
| `demo1a-mallory-voit-alice.png` | Dashboard d'Alice vu depuis la fenêtre privée de Mallory (solde 12 450 €, nom Alice affiché) |
| `db-sessions-partage.png` | Terminal MySQL : `SELECT sid, user_id, ip_address FROM bank_vuln.sessions;` — un seul SID avec user_id d'Alice |

---

## Démo 1B — Session Fixation via XSS

| Fichier | Ce qu'il faut capturer |
|---|---|
| `demo1b-commentaire-xss.png` | Page comments.php avec le commentaire XSS posté par Mallory (le `<script>` est visible dans la liste) |
| `demo1b-alice-redirigee-login.png` | Page login.php avec le message "Session expirée. Reconnectez-vous." après que le XSS ait frappé |
| `demo1b-sid-fixe-apres-reconnexion.png` | Dashboard d'Alice après reconnexion : **"Session ID courant : ATTACKERfixedSID001"** |

---

## Dashboard attaquant

| Fichier | Ce qu'il faut capturer |
|---|---|
| `attacker-dashboard.png` | Page `http://evil.attacker.lab:8080` complète — dashboard avec la section "Payloads" et la table "Sessions interceptées" |
| `attacker-dashboard-beacon.png` | Dashboard avec au moins une entrée dans le tableau (SID, cookie, URL victime, IP) — zoomer sur la table |

---

## Démo 2 — XSS Réfléchi

| Fichier | Ce qu'il faut capturer |
|---|---|
| `xss-reflechi-popup.png` | Popup `alert()` sur la page login après injection `?msg=<script>alert('XSS!')</script>` |

---

## Démo 3 — XSS Stocké (note de virement)

| Fichier | Ce qu'il faut capturer |
|---|---|
| `xss-dashboard-note-popup.png` | Dashboard avec la popup XSS déclenchée par la note de virement (`<img onerror=alert(...)>`) |

---

## Démo 4 — XSS Stocké (profil)

| Fichier | Ce qu'il faut capturer |
|---|---|
| `xss-profil-deface.png` | Page profil entièrement défacée avec le message HACKED en rouge |

---

## Démo 5 — Cookies

| Fichier | Ce qu'il faut capturer |
|---|---|
| `document-cookie-vuln.png` | Console DevTools (F12) sur vuln.bank.local : `document.cookie` → retourne le SID |
| `document-cookie-secure.png` | Console DevTools sur secure.bank.local : `document.cookie` → retourne `""` (vide) |
| `cookies-vuln-devtools.png` | DevTools → Application → Cookies → vuln.bank.local : colonnes HttpOnly et Secure sont **vides** |
| `cookies-secure-devtools.png` | DevTools → Application → Cookies → secure.bank.local : HttpOnly ✓, Secure ✓, SameSite=Strict |

---

## Démo 6 — Mots de passe

| Fichier | Ce qu'il faut capturer |
|---|---|
| `db-passwords-clear.png` | Terminal : `SELECT username, password FROM bank_vuln.users;` — mots de passe en clair |
| `db-passwords-hash.png` | Terminal : `SELECT username, password_hash FROM bank_secure.users;` — hashes bcrypt ($2y$10$...) |

---

## Démo 7 — CSRF

| Fichier | Ce qu'il faut capturer |
|---|---|
| `csrf-email-phishing.png` | Page `http://evil.attacker.lab:8080/csrf-email.html` — simulateur email avec le mail de phishing CaptusBank |
| `csrf-page-banque-fake.png` | Page `http://evil.attacker.lab:8080/csrf-mail.html` — fausse page alerte sécurité avec bouton rouge |
| `csrf-solde-alice-apres.png` | Dashboard d'Alice après avoir cliqué le bouton CSRF : solde réduit de 500 € |
| `secure-csrf-bloque.png` | App sécurisée : message d'erreur "CSRF token invalide" après tentative de CSRF |

---

## Démo 8 — Information Disclosure

| Fichier | Ce qu'il faut capturer |
|---|---|
| `x-powered-by-vuln.png` | DevTools → Network → n'importe quelle requête → Response Headers : **X-Powered-By: PHP/8.2.x** (app vulnérable) |
| `x-powered-by-absent-secure.png` | Mêmes Response Headers sur secure.bank.local : **pas de X-Powered-By** |

---

## App sécurisée (comparaison)

| Fichier | Ce qu'il faut capturer |
|---|---|
| `secure-session-regeneree.png` | Dashboard secure.bank.local après login : SID **différent** de celui avant login (montrer les deux SIDs si possible) |
| `secure-xss-bloque.png` | Page comments.php sur secure.bank.local : le payload XSS s'affiche comme du **texte brut** (pas d'exécution) |
| `csp-header.png` | DevTools → Network → Response Headers sur secure.bank.local : **Content-Security-Policy** avec ses directives |

---

## Total : 32 captures

**Conseil pour les captures** :
- Utiliser un navigateur avec une fenêtre suffisamment large (au moins 1280px)
- Pour les fenêtres côte à côte (Mallory/Alice), prendre chaque fenêtre séparément
- Masquer les extensions et la barre de favoris pour un rendu propre
- Pour les captures terminal/console, s'assurer que le texte est lisible (taille police suffisante)
