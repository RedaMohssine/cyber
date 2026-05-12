<?php
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        audit('csrf_failure', 'warning', (int)$_SESSION['user_id'], session_id(), ['endpoint' => 'comments']);
        http_response_code(400);
        die('CSRF token invalide.');
    }
    $content = trim($_POST['content'] ?? '');
    if ($content !== '' && mb_strlen($content) <= 1000) {
        $stmt = db()->prepare('INSERT INTO comments (user_id, content) VALUES (:u, :c)');
        $stmt->execute([':u' => (int)$_SESSION['user_id'], ':c' => $content]);
        header('Location: /comments.php');
        exit;
    }
}

$rows = db()->query('
    SELECT c.id, c.content, c.created_at, u.username
    FROM comments c JOIN users u ON u.id = c.user_id
    ORDER BY c.id DESC
')->fetchAll();

render_header('Communauté');
?>
<section class="comments">
    <h2>Commentaires de la communauté</h2>
    <form method="post" class="comment-form">
        <?= csrf_field() ?>
        <label>Votre commentaire <textarea name="content" rows="3" maxlength="1000" required></textarea></label>
        <button type="submit" class="btn primary">Publier</button>
    </form>

    <ul class="comment-list">
        <?php foreach ($rows as $r): ?>
            <li>
                <header>
                    <strong><?= e($r['username']) ?></strong>
                    <time><?= e($r['created_at']) ?></time>
                </header>
                <div class="body"><?= e($r['content']) ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php render_footer();
