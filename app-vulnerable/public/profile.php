<?php
// app-vulnerable/public/profile.php — page profil, affiche nom/email NON échappés
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['full_name'] ?? '';
    db()->prepare('UPDATE users SET full_name = :n WHERE id = :id')
        ->execute([':n' => $name, ':id' => $uid]);
}

$user = db()->prepare('SELECT * FROM users WHERE id = :id');
$user->execute([':id' => $uid]);
$user = $user->fetch();

render_header('Profil');
?>
<section class="profile">
    <h2>Profil de <?= /*VULN*/ $user['full_name'] ?></h2>
    <p>Email : <?= /*VULN*/ $user['email'] ?></p>
    <p>Rôle : <?= $user['role'] ?></p>

    <form method="post">
        <label>Nom complet
            <input type="text" name="full_name" value="<?= /*VULN*/ $user['full_name'] ?>">
        </label>
        <button class="btn primary">Mettre à jour</button>
    </form>
</section>
<?php render_footer();
