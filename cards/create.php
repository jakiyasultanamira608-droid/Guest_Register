<?php
// Card Inventory: add a new visitor card (FR-10, administrators only)
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/cards.php';

$user = require_role('admin');

$cardNumber = '';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $cardNumber = normalize_card_number(post('card_number'));
    $error      = validate_card_number($pdo, $cardNumber, null);

    if ($error !== '') {
        $errors['card_number'] = $error;
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO cards (card_number, status, updated_by) VALUES (?, 'available', ?)");
            $stmt->execute([$cardNumber, $user['id']]);

            flash('success', 'Card ' . $cardNumber . ' added and available to issue.');
            redirect('cards/index.php');
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            $errors['card_number'] = 'A card with this number already exists.';
        }
    }
}

$pageTitle = 'Add card';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <a class="back-link" href="<?= e(url('cards/index.php')) ?>"><?= icon('back') ?>Cards</a>
    <h1>Add card</h1>
    <p class="muted">Register a physical visitor card. New cards are available to issue straight away.</p>
</div>

<form class="form panel" method="post" action="<?= e(url('cards/create.php')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="form-grid">
        <div class="field">
            <label for="card_number">Card number</label>
            <input type="text" id="card_number" name="card_number" class="input mono" value="<?= e($cardNumber) ?>"
                   maxlength="20" placeholder="e.g. V-011" autocomplete="off" required autofocus<?= invalid_attrs($errors, 'card_number') ?>>
            <?= field_error($errors, 'card_number') ?>
            <p class="field-hint">Exactly as printed on the card. Letters, numbers and hyphens only.</p>
        </div>
    </div>

    <div class="form-actions">
        <a class="pill-button" href="<?= e(url('cards/index.php')) ?>">Cancel</a>
        <button type="submit" class="pill-button pill-primary">Add card</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
