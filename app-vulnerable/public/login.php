<?php
// app-vulnerable/public/login.php
// ===========================================================
//  VULNÉRABILITÉ : aucune régénération de session après login
//  Le SID présent avant l'authentification reste valide après.
//  Si un attaquant a réussi à imposer ce SID à la victime
//  (via XSS, paramètre URL, cookie injecté), il hérite de la session.
// ===========================================================

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

    // Mots de passe en clair — défaut intentionnel pour la démo
    if ($user && hash_equals($user['password'], $password)) {
        // ======================================================
        //  BUG CRITIQUE : on ne régénère PAS le session ID ici.
        //  Il faudrait : session_regenerate_id(true);
        //  Conséquence : le SID pré-auth devient un SID authentifié.
        // ======================================================
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_at'] = time();

        // Honeypot : si quelqu'un se connecte avec admin_honeypot, on log fort
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
        <!-- VULN : XSS réfléchie via le paramètre ?msg= -->
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
        <!-- VULN: paramètre ?msg= injecté tel quel pour démontrer XSS réfléchie -->
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
