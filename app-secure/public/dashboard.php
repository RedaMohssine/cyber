<?php
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

$account = db()->prepare('SELECT * FROM accounts WHERE user_id = :uid');
$account->execute([':uid' => $uid]);
$account = $account->fetch();

$txs = db()->prepare('SELECT * FROM transfers WHERE from_account = :aid ORDER BY id DESC LIMIT 10');
$txs->execute([':aid' => $account['id']]);
$txs = $txs->fetchAll();

render_header('Tableau de bord');
?>
<section class="dashboard">
    <h2>Bonjour <?= e($_SESSION['username']) ?>,</h2>

    <div class="account-card">
        <div class="iban">IBAN <code><?= e($account['iban']) ?></code></div>
        <div class="balance">
            <span class="amount"><?= number_format($account['balance'], 2, ',', ' ') ?> €</span>
            <span class="label">Solde disponible</span>
        </div>
    </div>

    <h3>Derniers virements</h3>
    <table class="transfers">
        <thead><tr><th>Date</th><th>Destinataire</th><th>Montant</th><th>Note</th></tr></thead>
        <tbody>
        <?php foreach ($txs as $tx): ?>
            <tr>
                <td><?= e($tx['created_at']) ?></td>
                <td><code><?= e($tx['to_iban']) ?></code></td>
                <td>-<?= number_format($tx['amount'], 2, ',', ' ') ?> €</td>
                <td><?= e($tx['note']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$txs): ?><tr><td colspan="4"><em>Aucun virement.</em></td></tr><?php endif; ?>
        </tbody>
    </table>

    <div class="sid-debug">
        SID courant : <code><?= e(session_id()) ?></code><br>
        <small>Rotation auto toutes les 5 min. Binding fingerprint actif.</small>
    </div>
</section>
<?php render_footer();
