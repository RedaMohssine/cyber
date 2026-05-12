<?php
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/layout.php';

if (!empty($_SESSION['user_id'])) { header('Location: /dashboard.php'); exit; }

render_header('CaptusBank — Accueil');
?>
<section class="hero">
    <h1>Bienvenue sur <span class="brand-text">CaptusBank</span></h1>
    <p class="lead">Build sécurisée — toutes les contre-mesures sont actives.</p>
    <a class="btn primary" href="/login.php">Se connecter</a>
</section>

<section class="features">
    <div class="card"><h3>Régénération SID</h3><p>Le SID change à chaque login / logout / élévation, plus rotation toutes les 5 min.</p></div>
    <div class="card"><h3>Cookies durcis</h3><p>HttpOnly, Secure (TLS), SameSite=Strict, strict_mode = 1.</p></div>
    <div class="card"><h3>CSP stricte</h3><p>Script externes interdits, pas d'inline, frame-ancestors none.</p></div>
    <div class="card"><h3>Échappement</h3><p>Toutes les sorties utilisateurs passent par <code>htmlspecialchars</code>.</p></div>
    <div class="card"><h3>Binding session</h3><p>Empreinte IP/24 + UA — détourner un SID déclenche une alerte.</p></div>
    <div class="card"><h3>Audit SIEM</h3><p>Évènements de sécurité loggés en BDD + stderr.</p></div>
</section>
<?php render_footer();
