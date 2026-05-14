<?php
// VULN: commentaires restitués sans échappement → XSS stocké
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';
    if ($content !== '') {
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
        <label>Votre commentaire
            <textarea name="content" rows="3" required></textarea>
        </label>
        <button type="submit" class="btn primary">Publier</button>
    </form>

    <ul class="comment-list">
        <?php foreach ($rows as $r): ?>
            <li>
                <header>
                    <strong><?= $r['username'] ?></strong>
                    <time><?= $r['created_at'] ?></time>
                </header>
                <div class="body"><?= $r['content'] ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php render_footer();
