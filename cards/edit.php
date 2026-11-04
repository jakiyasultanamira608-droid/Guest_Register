<?php
// Card Inventory: correct a card number or change its status
// (FR-10, administrators only). Issued cards are locked: they become
// free again only when the visitor is checked out (business rule 8).
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/cards.php';

$user = require_role('admin');

$cardId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$stmt = $pdo->prepare(
    'SELECT c.id, c.card_number, c.status, v.reference, vi.full_name AS visitor_name
     FROM cards c
     LEFT JOIN visits v    ON v.id = c.current_visit_id
     LEFT JOIN visitors vi ON vi.id = v.visitor_id
     WHERE c.id = ?'
);
$stmt->execute([$cardId]);
$card = $stmt->fetch();

if (!$card) {
    http_response_code(404);
    $pageTitle = 'Card not found';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="panel"><h1>Card not found</h1>'
        . '<p class="muted">This card does not exist.</p>'
        . '<a class="pill-button pill-primary" href="' . e(url('cards/index.php')) . '">Back to cards</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$data   = ['card_number' => $card['card_number'], 'status' => $card['status']];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $card['status'] !== 'issued') {
    csrf_verify();

    $data = [
        'card_number' => normalize_card_number(post('card_number')),
        'status'      => post('status'),
    ];

    $numberError = validate_card_number($pdo, $data['card_number'], (int) $card['id']);
    if ($numberError !== '') {
        $errors['card_number'] = $numberError;
    }

    if (!isset(CARD_MANUAL_STATUSES[$data['status']])) {
        $errors['status'] = 'Choose a status.';
    }

    if (!$errors) {
        try {
            // Lock the card row, then re-check it, so a receptionist issuing
            // this card at the same moment cannot be overwritten
            $pdo->beginTransaction();

            $lock = $pdo->prepare('SELECT status FROM cards WHERE id = ? FOR UPDATE');
            $lock->execute([$card['id']]);

            if ($lock->fetchColumn() === 'issued') {
                $pdo->rollBack();
                flash('error', 'Card ' . $card['card_number'] . ' was just issued to a visitor, so it was not changed.');
                redirect('cards/index.php');
            }

            $update = $pdo->prepare('UPDATE cards SET card_number = ?, status = ?, updated_by = ? WHERE id = ?');
            $update->execute([$data['card_number'], $data['status'], $user['id'], $card['id']]);

            $pdo->commit();

            flash('success', 'Card ' . $data['card_number'] . ' saved as ' . card_status_label($data['status']) . '.');
            redirect('cards/index.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            $errors['card_number'] = 'A card with this number already exists.';
        }
    }
}

$pageTitle = 'Edit card ' . $card['card_number'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <a class="back-link" href="<?= e(url('cards/index.php')) ?>"><?= icon('back') ?>Cards</a>
    <h1>Card <span class="mono"><?= e($card['card_number']) ?></span></h1>
    <p class="muted">Correct the card number, report it lost, or take it out of service.</p>
</div>

<?php if ($card['status'] === 'issued'): ?>

    <section class="panel callout">
        <?= icon('lock') ?>
        <div>
            <h2>This card is in use</h2>
            <p class="muted">
                It is issued to <strong><?= e($card['visitor_name']) ?></strong> (<?= e($card['reference']) ?>).
                It can be changed after the visitor is checked out.
            </p>
        </div>
    </section>

<?php else: ?>

    <form class="form panel" method="post" action="<?= e(url('cards/edit.php?id=' . $card['id'])) ?>" novalidate>
        <?= csrf_field() ?>

        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert">Please fix the highlighted fields.</div>
        <?php endif; ?>

        <div class="form-grid">
            <div class="field">
                <label for="card_number">Card number</label>
                <input type="text" id="card_number" name="card_number" class="input mono" value="<?= e($data['card_number']) ?>"
                       maxlength="20" autocomplete="off" required<?= invalid_attrs($errors, 'card_number') ?>>
                <?= field_error($errors, 'card_number') ?>
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status" class="input" required<?= invalid_attrs($errors, 'status') ?>>
                    <?php foreach (CARD_MANUAL_STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $data['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'status') ?>
            </div>
        </div>

        <?php if ($card['status'] === 'lost'): ?>
            <p class="hint">If this card has been found, set it to <strong>Available</strong> so it can be issued again.</p>
        <?php endif; ?>

        <div class="form-actions">
            <a class="pill-button" href="<?= e(url('cards/index.php')) ?>">Cancel</a>
            <button type="submit" class="pill-button pill-primary">Save card</button>
        </div>
    </form>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
