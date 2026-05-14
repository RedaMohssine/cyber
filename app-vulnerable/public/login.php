<?php
// VULN: aucun session_regenerate_id() après login → le SID pré-auth reste valide
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if ($user && hash_equals($user['password'], $password)) {
        // VULN: il manque session_regenerate_id(true) ici
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_at'] = time();

        if ($user['role'] === 'honeypot') {
            error_log("[HONEYPOT TRIGGERED] sid={$_COOKIE['PHPSESSID']} ip={$_SERVER['REMOTE_ADDR']}");
        }

        header('Location: /dashboard.php');
        exit;
    }

    $error = 'Identifiants invalides.';
}

render_header('Connexion');
?>
<section class="auth">
    <h2>Connexion à votre espace</h2>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
        <!-- VULN: XSS réfléchie — paramètre ?msg= non échappé -->
        <div class="notice"><?= $_GET['msg'] ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <label>Nom d'utilisateur
            <input type="text" name="username" autocomplete="username" required>
        </label>
        <label>Mot de passe
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn primary">Se connecter</button>
    </form>

    <p class="hint">
        Comptes de démo : <code>alice / Password123!</code> · <code>bob / BobPass456!</code>
    </p>
    <p class="sid-debug">
        SID actuel (pré-auth) : <code><?= session_id() ?></code>
    </p>
</section>
<?php render_footer();
