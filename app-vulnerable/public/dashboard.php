<?php
// app-vulnerable/public/dashboard.php
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$uid = (int)$_SESSION['user_id'];
$account = db()->prepare('SELECT * FROM accounts WHERE user_id = :uid');
$account->execute([':uid' => $uid]);
$account = $account->fetch();

$txs = db()->prepare('
    SELECT t.*, "out" as direction FROM transfers t WHERE t.from_account = :aid
    UNION ALL
    SELECT t.*, "in" as direction FROM transfers t WHERE t.to_iban = :iban
    ORDER BY id DESC LIMIT 10
');
$txs->execute([':aid' => $account['id'], ':iban' => $account['iban']]);
$txs = $txs->fetchAll();

render_header('Tableau de bord');
?>
<section class="dashboard">
    <h2>Bonjour <?= /*VULN*/ $_SESSION['username'] ?>,</h2>

    <div class="account-card">
        <div class="iban">IBAN <code><?= /*VULN*/ $account['iban'] ?></code></div>
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
                <td><?= $tx['created_at'] ?></td>
                <td><code><?= /*VULN*/ $tx['to_iban'] ?></code></td>
                <td style="color:<?= $tx['direction']==='in' ? 'green' : 'red' ?>">
                    <?= $tx['direction']==='in' ? '+' : '-' ?><?= number_format($tx['amount'], 2, ',', ' ') ?> €
                </td>
                <!-- VULN majeure : note de virement non échappée -->
                <td><?= $tx['note'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$txs): ?>
            <tr><td colspan="4"><em>Aucun virement.</em></td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="sid-debug">
        Session ID courant : <code><?= session_id() ?></code><br>
        <small>Identifiant attribué <?= date('H:i:s', $_SESSION['logged_at'] ?? time()) ?>. Ce SID est-il bien le même qu'avant connexion ?</small>
    </div>
</section>
<?php render_footer();
