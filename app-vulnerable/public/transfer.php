<?php
// app-vulnerable/public/transfer.php — virement (pas de CSRF token : vuln supplémentaire)
require_once __DIR__ . '/../src/session_handler.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/layout.php';

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

$account = db()->prepare('SELECT * FROM accounts WHERE user_id = :uid');
$account->execute([':uid' => $uid]);
$account = $account->fetch();

$success = $error = null;

// VULN CSRF : accepte aussi les paramètres GET — toute navigation vers cette URL
// déclenche un virement. Viole le principe HTTP : GET ne doit pas modifier des données.
$input  = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$is_get = $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['to_iban']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $is_get) {
    $to     = trim($input['to_iban'] ?? '');
    $amount = (float)($input['amount'] ?? 0);
    $note   = $input['note'] ?? '';

    if ($amount <= 0)                        $error = 'Montant invalide.';
    elseif ($amount > $account['balance'])   $error = 'Solde insuffisant.';
    elseif (!preg_match('/^[A-Z0-9]{15,34}$/', $to)) $error = 'IBAN invalide.';
    else {
        db()->beginTransaction();
        db()->prepare('UPDATE accounts SET balance = balance - :a WHERE id = :id')
            ->execute([':a' => $amount, ':id' => $account['id']]);
        db()->prepare('INSERT INTO transfers (from_account, to_iban, amount, note) VALUES (:f, :t, :a, :n)')
            ->execute([':f' => $account['id'], ':t' => $to, ':a' => $amount, ':n' => $note]);
        // Créditer le destinataire si son IBAN existe dans la banque
        db()->prepare('UPDATE accounts SET balance = balance + :a WHERE iban = :iban')
            ->execute([':a' => $amount, ':iban' => $to]);
        db()->commit();
        $success = "Virement de $amount € effectué vers $to.";
        $account['balance'] -= $amount;
    }
}

render_header('Virement');
?>
<section class="transfer">
    <h2>Nouveau virement</h2>
    <p>Solde disponible : <strong><?= number_format($account['balance'], 2, ',', ' ') ?> €</strong></p>

    <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="error"><?= $error ?></div>    <?php endif; ?>

    <form method="post">
        <label>IBAN destinataire <input type="text" name="to_iban" required></label>
        <label>Montant (€) <input type="number" step="0.01" name="amount" required></label>
        <label>Note (optionnel) <input type="text" name="note" maxlength="255"></label>
        <button type="submit" class="btn primary">Valider le virement</button>
    </form>
    <!-- VULN : aucun token CSRF -->
</section>
<?php render_footer();
