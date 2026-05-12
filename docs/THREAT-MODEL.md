# Threat Model — CaptusBank

## 1. Data Flow Diagram

```
                         ┌─────────────────────────────────┐
                         │     ✦ Trust boundary internet ✦  │
                         └────────────────┬────────────────┘
                                          │
   ┌────────────┐   HTTP/cookies    ┌─────▼─────┐  SQL    ┌──────────┐
   │  Browser   │ ◄─────────────►   │  App PHP  │ ◄────► │  MySQL    │
   │  (Alice)   │                    │ (Apache)  │        │           │
   └────────────┘                    └─────┬─────┘        └──────────┘
         ▲                                 │
         │ XSS exfil                       │ stderr
         │                                 ▼
   ┌─────┴──────┐   HTTP             ┌──────────┐
   │  Mallory   │ ◄─────────────►    │   SIEM   │
   │ + evil.lab │                    │ (Sigma)  │
   └────────────┘                    └──────────┘
```

Frontières de confiance :
- **TB1** Internet ↔ Reverse proxy Nginx
- **TB2** Application PHP ↔ MySQL
- **TB3** Application ↔ SIEM (one-way logs)
- **TB4** Navigateur d'Alice ↔ infrastructure de Mallory

## 2. STRIDE par composant

| Composant            | S Spoofing | T Tampering | R Repudiation | I Info Disclosure | D Denial | E Elev. Priv. |
|----------------------|:----------:|:-----------:|:-------------:|:-----------------:|:--------:|:-------------:|
| Login form           |    🔴      |     ⚪      |      🟠       |        ⚪          |    ⚪    |     🔴        |
| Session cookie       |    🔴      |     🔴      |      🔴       |        🔴          |    ⚪    |     🔴        |
| Comments endpoint    |    🟠      |     🔴      |      🟠       |        🔴          |    🟠    |     🔴        |
| Transfer endpoint    |    🔴      |     🔴      |      🔴       |        🟠          |    ⚪    |     🔴        |
| MySQL sessions table |    ⚪      |     🔴      |      ⚪       |        🔴          |    🟠    |     ⚪        |

Légende : 🔴 critique · 🟠 important · ⚪ acceptable / hors scope

### Détail des menaces clés

**S — Spoofing du cookie session** : un attaquant impose son propre SID au navigateur
de la victime (cf. V001/V002/V003).
*Mitigation : `session_regenerate_id(true)` + `use_strict_mode=1`.*

**T — Tampering du payload commentaires** : insertion de JavaScript exécuté dans
le contexte d'autres utilisateurs (V004).
*Mitigation : `htmlspecialchars` + CSP `script-src 'self'`.*

**R — Repudiation des virements** : aucun journal d'audit n'identifie l'IP/UA
qui a exécuté un virement.
*Mitigation : `audit_log` côté sécurisé, signe les actions avec session+IP+UA.*

**I — Information disclosure via XSS** : `document.cookie` exfiltré vers `evil.attacker.lab`.
*Mitigation : `HttpOnly` + CSP `connect-src 'self'`.*

**E — Elevation of privilege** : un commentaire posté par Mallory permet de prendre
le rôle d'un autre utilisateur via session hijack.
*Mitigation : régénération du SID + binding fingerprint.*

## 3. Attack tree

```
              [Goal: vider le compte d'Alice]
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
   [Hijack cookie]   [Reset password]  [Social eng]
        │                 │                 │
   ┌────┼────┐            │                 │
   │    │    │            │                 │
[Steal][Fix][Brute        │                 │
       │    SID]          │                 │
       │                  │                 │
   ┌───┼───┐              │                 │
   │   │   │              │                 │
 [URL][XSS][Cookie        │                 │
       │   subdomain]     │                 │
       │                  │                 │
   ┌───┼───┐              │                 │
   │       │              │                 │
[Stored][Reflected]       │                 │
   │       │              │                 │
   ▼       ▼              ▼                 ▼
 V004    V005            ❌                 ❌
   └───┬───┘              hors scope        hors scope
       │
       ▼
   V001+V002+V003  → CHAÎNE EXPLOITABLE
```

## 4. Risques résiduels (post-remédiation)

Même après remédiation, les risques suivants persistent à un niveau acceptable :

| Risque                                       | Probabilité | Impact | Niveau résiduel | Action proposée                                |
|----------------------------------------------|:-----------:|:------:|:---------------:|------------------------------------------------|
| 0-day dans le moteur de session PHP          | Très faible | Élevé  | Faible          | Patch management mensuel                       |
| Vol de cookie via malware sur poste utilisateur | Moyenne  | Élevé  | Moyen           | 2FA + WebAuthn obligatoire pour les virements  |
| Phishing menant à credentials disclosure     | Moyenne     | Élevé  | Moyen           | Détection MFA + alertes connexion inhabituelle |
| Compromission base SIEM                      | Faible      | Moyen  | Faible          | SIEM en lecture seule, sauvegardes WORM        |

## 5. Mapping CIA

| Dimension       | Avant remédiation | Après remédiation |
|-----------------|:-----------------:|:-----------------:|
| Confidentialité |        🔴         |        🟢         |
| Intégrité       |        🔴         |        🟢         |
| Disponibilité   |        🟢         |        🟢         |
| Auditabilité    |        🔴         |        🟢         |
| Non-répudiation |        🔴         |        🟠         |
