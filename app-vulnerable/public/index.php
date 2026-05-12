<?php
// app-vulnerable/public/index.php — page d'accueil
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/layout.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

render_header('CaptusBank — Accueil');
?>
<section class="hero">
    <h1>Bienvenue sur <span class="brand-text">CaptusBank</span></h1>
    <p class="lead">La banque qui vous fait confiance. <em>(Vraiment trop confiance.)</em></p>
    <a class="btn primary" href="/login.php">Se connecter</a>
</section>

<section class="features">
    <div class="card">
        <h3>Virements instantanés</h3>
        <p>Effectuez vos virements en quelques clics.</p>
    </div>
    <div class="card">
        <h3>Communauté</h3>
        <p>Consultez les avis de nos utilisateurs.</p>
    </div>
    <div class="card">
        <h3>Sécurité<sup>?</sup></h3>
        <p>Nos sessions ne se régénèrent jamais, pour une expérience sans accroc.</p>
    </div>
</section>
<?php render_footer();
