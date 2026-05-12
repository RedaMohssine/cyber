<?php
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

$account = db()->prepare('SELECT * FROM accounts WHERE user_id = :uid');
$account->execute([':uid' => $uid]);
$account = $account->fetch();

$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        audit('csrf_failure', 'warning', $uid, session_id(), ['endpoint' => 'transfer']);
        http_response_code(400);
        die('CSRF token invalide.');
    }
    $to     = trim($_POST['to_iban'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if ($amount <= 0)                              $error = 'Montant invalide.';
    elseif ($amount > $account['balance'])         $error = 'Solde insuffisant.';
    elseif (!preg_match('/^[A-Z0-9]{15,34}$/', $to)) $error = 'IBAN invalide.';
    elseif (mb_strlen($note) > 255)                $error = 'Note trop longue.';
    else {
        db()->beginTransaction();
        db()->prepare('UPDATE accounts SET balance = balance - :a WHERE id = :id')
            ->execute([':a' => $amount, ':id' => $account['id']]);
        db()->prepare('INSERT INTO transfers (from_account, to_iban, amount, note) VALUES (:f,:t,:a,:n)')
            ->execute([':f' => $account['id'], ':t' => $to, ':a' => $amount, ':n' => $note]);
        db()->commit();
        audit('transfer', 'info', $uid, session_id(), [
            'to'     => $to, 'amount' => $amount,
        ]);
        $success = "Virement de $amount € effectué vers $to.";
        $account['balance'] -= $amount;
    }
}

render_header('Virement');
?>
<section class="transfer">
    <h2>Nouveau virement</h2>
    <p>Solde disponible : <strong><?= number_format($account['balance'], 2, ',', ' ') ?> €</strong></p>

    <?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="error"><?= e($error) ?></div>    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <label>IBAN destinataire <input type="text" name="to_iban" required pattern="[A-Z0-9]{15,34}"></label>
        <label>Montant (€) <input type="number" step="0.01" name="amount" required></label>
        <label>Note (optionnel) <input type="text" name="note" maxlength="255"></label>
        <button type="submit" class="btn primary">Valider le virement</button>
    </form>
</section>
<?php render_footer();
