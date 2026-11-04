<?php
// Check-out: close an active visit and record the card's return (FR-06).
// All staff. A card that is not returned is marked lost with a note.
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/visits.php';

$user = require_login();

$visitId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$stmt = $pdo->prepare(
    'SELECT v.id, v.reference, v.status, v.check_in_at, v.purpose,
            vi.full_name, d.name AS department_name, c.card_number
     FROM visits v
     JOIN visitors vi   ON vi.id = v.visitor_id
     JOIN departments d ON d.id = v.department_id
     LEFT JOIN cards c  ON c.current_visit_id = v.id
     WHERE v.id = ?'
);
$stmt->execute([$visitId]);
$visit = $stmt->fetch();

if (!$visit) {
    http_response_code(404);
    $pageTitle = 'Visit not found';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="panel"><h1>Visit not found</h1>'
        . '<p class="muted">This visit does not exist.</p>'
        . '<a class="pill-button pill-primary" href="' . e(url('visits/current.php')) . '">Back to current visitors</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Already checked out (e.g. by a colleague, or a second click): show the visit instead
if ($visit['status'] !== 'active') {
    flash('info', $visit['full_name'] . ' has already been checked out.');
    redirect('visits/view.php?id=' . $visit['id']);
}

$hasCard = $visit['card_number'] !== null;
$data    = ['card_outcome' => '', 'note' => ''];
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $data = [
        'card_outcome' => post('card_outcome'),
        'note'         => post('note'),
    ];

    if ($hasCard && !in_array($data['card_outcome'], ['returned', 'lost'], true)) {
        $errors['card_outcome'] = 'Choose whether the card was returned.';
    }

    if ($hasCard && $data['card_outcome'] === 'lost' && $data['note'] === '') {
        $errors['note'] = 'Add a short note about the missing card, e.g. "Visitor left with card, will return tomorrow".';
    } elseif (mb_strlen($data['note']) > 255) {
        $errors['note'] = 'Note must be 255 characters or fewer.';
    }

    if (!$errors) {
        try {
            check_out_visit($pdo, (int) $visit['id'], $hasCard ? $data['card_outcome'] : '', $hasCard ? $data['note'] : '', (int) $user['id']);

            $message = $visit['full_name'] . ' checked out.';
            if ($hasCard) {
                $message .= $data['card_outcome'] === 'returned'
                    ? ' Card ' . $visit['card_number'] . ' is available again.'
                    : ' Card ' . $visit['card_number'] . ' is marked lost.';
            }

            flash($hasCard && $data['card_outcome'] === 'lost' ? 'info' : 'success', $message);
            redirect('visits/current.php');
        } catch (VisitError $e) {
            if ($e->field === 'visit') {
                flash('info', $e->getMessage());
                redirect('visits/view.php?id=' . $visit['id']);
            }
            $errors[$e->field] = $e->getMessage();
        }
    }
}

$pageTitle = 'Check out ' . $visit['full_name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <a class="back-link" href="<?= e(url('visits/current.php')) ?>"><?= icon('back') ?>Current visitors</a>
    <h1>Check out <?= e($visit['full_name']) ?></h1>
    <p class="muted">
        <span class="mono"><?= e($visit['reference']) ?></span> &middot; <?= e($visit['department_name']) ?>
        &middot; inside for <?= e(format_duration(time() - strtotime($visit['check_in_at']))) ?>
        (since <?= e(format_date($visit['check_in_at'], 'g:i A')) ?>)
    </p>
</div>

<form class="form panel" method="post" action="<?= e(url('visits/checkout.php?id=' . $visit['id'])) ?>" novalidate data-submit-once>
    <?= csrf_field() ?>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">Please fix the highlighted fields.</div>
    <?php endif; ?>

    <?php if ($hasCard): ?>
        <fieldset class="choices"<?= invalid_attrs($errors, 'card_outcome') ?>>
            <legend>Was card <strong class="mono"><?= e($visit['card_number']) ?></strong> returned? <span class="required" aria-hidden="true">*</span></legend>

            <label class="choice">
                <input type="radio" name="card_outcome" value="returned"<?= $data['card_outcome'] === 'returned' ? ' checked' : '' ?>>
                <span>
                    <strong>Returned</strong>
                    <small>The card is back at the desk. It becomes available to issue again.</small>
                </span>
            </label>

            <label class="choice choice-danger">
                <input type="radio" name="card_outcome" value="lost"<?= $data['card_outcome'] === 'lost' ? ' checked' : '' ?>>
                <span>
                    <strong>Not returned</strong>
                    <small>The card is marked lost and cannot be issued until an administrator resolves it.</small>
                </span>
            </label>

            <?= field_error($errors, 'card_outcome') ?>
        </fieldset>

        <div class="field">
            <label for="note">Note <span class="optional">required if not returned</span></label>
            <textarea id="note" name="note" class="input" rows="2" maxlength="255"<?= invalid_attrs($errors, 'note') ?>><?= e($data['note']) ?></textarea>
            <?= field_error($errors, 'note') ?>
        </div>
    <?php else: ?>
        <div class="callout callout-plain">
            <?= icon('card') ?>
            <p class="muted">No card was issued for this visit, so there is nothing to collect.</p>
        </div>
    <?php endif; ?>

    <p class="hint hint-after">The check-out time is recorded automatically.</p>

    <div class="form-actions">
        <a class="pill-button" href="<?= e(url('visits/view.php?id=' . $visit['id'])) ?>">Cancel</a>
        <button type="submit" class="pill-button pill-primary"><?= icon('logout') ?>Check out visitor</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
