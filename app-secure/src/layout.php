<?php
// app-secure/src/layout.php
require_once __DIR__ . '/security.php';

function render_header(string $title = 'CaptusBank'): void {
    $user = $_SESSION['username'] ?? null;
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <title><?= e($title) ?></title>
        <link rel="stylesheet" href="/assets/style.css">
    </head>
    <body>
    <header class="topbar">
        <div class="brand">
            <span class="logo">🔒</span>
            <strong>CaptusBank</strong>
            <span class="env-badge env-secure">SECURE BUILD</span>
        </div>
        <nav>
            <?php if ($user): ?>
                <a href="/dashboard.php">Tableau de bord</a>
                <a href="/transfer.php">Virement</a>
                <a href="/comments.php">Communauté</a>
                <a href="/profile.php">Profil</a>
                <span class="who">connecté en tant que <?= e($user) ?></span>
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
    <footer><small>CaptusBank © 2026 — Build sécurisée (P03).</small></footer>
    </body></html>
    <?php
}
