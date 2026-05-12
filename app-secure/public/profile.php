<?php
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { http_response_code(400); die('CSRF'); }
    $name = trim($_POST['full_name'] ?? '');
    if (mb_strlen($name) > 0 && mb_strlen($name) <= 150) {
        db()->prepare('UPDATE users SET full_name = :n WHERE id = :id')
            ->execute([':n' => $name, ':id' => $uid]);
    }
}

$user = db()->prepare('SELECT * FROM users WHERE id = :id');
$user->execute([':id' => $uid]);
$user = $user->fetch();

render_header('Profil');
?>
<section class="profile">
    <h2>Profil de <?= e($user['full_name']) ?></h2>
    <p>Email : <?= e($user['email']) ?></p>
    <p>Rôle : <?= e($user['role']) ?></p>

    <form method="post">
        <?= csrf_field() ?>
        <label>Nom complet <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" maxlength="150"></label>
        <button class="btn primary">Mettre à jour</button>
    </form>
</section>
<?php render_footer();
