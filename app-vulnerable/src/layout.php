<?php
// app-vulnerable/src/layout.php — header/footer partagés

function render_header(string $title = 'CaptusBank'): void {
    $user = $_SESSION['username'] ?? null;
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <title><?= $title /* VULN: pas d'échappement */ ?></title>
        <link rel="stylesheet" href="/assets/style.css">
        <!-- VULN : aucune CSP -->
    </head>
    <body>
    <header class="topbar">
        <div class="brand">
            <span class="logo">⚠</span>
            <strong>CaptusBank</strong>
            <span class="env-badge env-vuln">VULNERABLE BUILD</span>
        </div>
        <nav>
            <?php if ($user): ?>
                <a href="/dashboard.php">Tableau de bord</a>
                <a href="/transfer.php">Virement</a>
                <a href="/comments.php">Communauté</a>
                <a href="/profile.php">Profil</a>
                <span class="who">connecté en tant que <?= /*VULN*/ $user ?></span>
                <a href="/logout.php">Déconnexion</a>
            <?php else: ?>
                <a href="/login.php">Connexion</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="container">
    <?php
}

function render_footer(): void {
    ?>
    </main>
    <footer>
        <small>CaptusBank © 2026 — Projet pédagogique P03. <em>Ne pas déployer en production.</em></small>
    </footer>
    </body></html>
    <?php
}
