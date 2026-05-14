<?php
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        audit('csrf_failure', 'warning', null, session_id(), ['endpoint' => 'login']);
        http_response_code(400);
        die('CSRF token invalide.');
    }
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $oldSid = session_id();
        session_regenerate_id(true); // Contre-mesure principale : invalide le SID pré-auth
        audit('login_success', 'info', (int)$user['id'], session_id(), [
            'username' => $user['username'],
            'old_sid'  => substr($oldSid, 0, 8) . '...',
            'new_sid'  => substr(session_id(), 0, 8) . '...',
        ]);

        $_SESSION['user_id']      = (int)$user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['logged_at']    = time();
        $_SESSION['_last_rotate'] = time();

        if ($user['role'] === 'honeypot') {
            audit('honeypot_login', 'critical', (int)$user['id'], session_id(), [
                'message' => 'Honeypot account used — likely compromise',
            ]);
        }

        header('Location: /dashboard.php');
        exit;
    }
    audit('login_failure', 'warning', null, session_id(), ['username' => $username]);
    $error = 'Identifiants invalides.';
}

render_header('Connexion');
?>
<section class="auth">
    <h2>Connexion à votre espace</h2>
    <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <?php if (isset($_GET['msg'])): ?>
        <div class="notice"><?= e((string)$_GET['msg']) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <?= csrf_field() ?>
        <label>Nom d'utilisateur <input type="text" name="username" autocomplete="username" required></label>
        <label>Mot de passe <input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit" class="btn primary">Se connecter</button>
    </form>

    <p class="hint">Comptes : <code>alice / Password123!</code> · <code>bob / BobPass456!</code></p>
    <p class="sid-debug">SID pré-auth (sera détruit à l'authentification) : <code><?= e(session_id()) ?></code></p>
</section>
<?php render_footer();
