<?php
// Register Visitor: record the visitor, check them in and issue a card
// in one step (FR-03, FR-04, FR-05). Receptionists and administrators only.
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/visits.php';

$user = require_role('admin', 'receptionist');

$data = [
    'full_name'      => '',
    'organization'   => '',
    'contact_number' => '',
    'department_id'  => '',
    'host_name'      => '',
    'purpose'        => '',
    'notes'          => '',
    'card'           => '',
];
$errors = [];

// Each form gets a one-time ID. If the same form is submitted twice
// (double-click, browser refresh), the second submit opens the visit
// that was already created instead of creating a duplicate.
$submissionId = bin2hex(random_bytes(16));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $submissionId = post('submission_id');
    $doneVisitId  = $_SESSION['registered_visits'][$submissionId] ?? null;

    if ($doneVisitId) {
        flash('info', 'This visitor was already checked in.');
        redirect('visits/view.php?id=' . $doneVisitId);
    }

    $data   = visit_form_data();
    $errors = validate_visit($pdo, $data);

    if (!$errors) {
        try {
            $visitId = register_visit($pdo, $data, (int) $user['id']);

            // Remember the last few completed forms (see $submissionId above)
            $_SESSION['registered_visits'][$submissionId] = $visitId;
            $_SESSION['registered_visits'] = array_slice($_SESSION['registered_visits'], -20, null, true);

            flash('success', $data['full_name'] . ' is checked in.');
            redirect('visits/view.php?id=' . $visitId);
        } catch (VisitError $e) {
            $errors[$e->field] = $e->getMessage();
        }
    }

    if (!preg_match('/^[a-f0-9]{32}$/', $submissionId)) {
        $submissionId = bin2hex(random_bytes(16));
    }
}

// Loaded after handling the form, so a card taken by someone else meanwhile is not offered again
$departments = $pdo->query('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name')->fetchAll();
$cards = $pdo->query(
    "SELECT id, card_number FROM cards WHERE status = 'available' ORDER BY LENGTH(card_number), card_number"
)->fetchAll();

$pageTitle = 'Register visitor';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h1>Register visitor</h1>
    <p class="muted">Record the visitor's details and check them in. The check-in time is recorded automatically.</p>
</div>

<form class="form panel" method="post" action="<?= e(url('visits/register.php')) ?>" novalidate data-submit-once>
    <?= csrf_field() ?>
    <input type="hidden" name="submission_id" value="<?= e($submissionId) ?>">

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">Please fix the highlighted fields.</div>
    <?php endif; ?>

    <h2>Visitor</h2>

    <div class="form-grid">
        <div class="field field-full">
            <label for="full_name">Full name <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="full_name" name="full_name" class="input" value="<?= e($data['full_name']) ?>"
                   maxlength="150" autocomplete="off" required autofocus<?= invalid_attrs($errors, 'full_name') ?>>
            <?= field_error($errors, 'full_name') ?>
        </div>

        <div class="field">
            <label for="organization">Company / organization <span class="optional">optional</span></label>
            <input type="text" id="organization" name="organization" class="input" value="<?= e($data['organization']) ?>"
                   maxlength="150" autocomplete="off"<?= invalid_attrs($errors, 'organization') ?>>
            <?= field_error($errors, 'organization') ?>
        </div>

        <div class="field">
            <label for="contact_number">Phone number <span class="optional">optional</span></label>
            <input type="tel" id="contact_number" name="contact_number" class="input" value="<?= e($data['contact_number']) ?>"
                   maxlength="20" autocomplete="off" inputmode="tel"<?= invalid_attrs($errors, 'contact_number') ?>>
            <?= field_error($errors, 'contact_number') ?>
            <p class="field-hint">Returning visitors are recognised by name and phone number.</p>
        </div>
    </div>

    <h2 class="form-section">Visit</h2>

    <div class="form-grid">
        <div class="field">
            <label for="department_id">Department <span class="required" aria-hidden="true">*</span></label>
            <select id="department_id" name="department_id" class="input" required<?= invalid_attrs($errors, 'department_id') ?>>
                <option value="">Choose a department</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= (int) $department['id'] ?>"<?= (string) $data['department_id'] === (string) $department['id'] ? ' selected' : '' ?>>
                        <?= e($department['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'department_id') ?>
        </div>

        <div class="field">
            <label for="host_name">Person visiting <span class="optional">optional</span></label>
            <input type="text" id="host_name" name="host_name" class="input" value="<?= e($data['host_name']) ?>"
                   maxlength="100" autocomplete="off"<?= invalid_attrs($errors, 'host_name') ?>>
            <?= field_error($errors, 'host_name') ?>
        </div>

        <div class="field field-full">
            <label for="purpose">Purpose of visit <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="purpose" name="purpose" class="input" value="<?= e($data['purpose']) ?>"
                   maxlength="255" autocomplete="off" placeholder="e.g. Job interview, Delivery, Meeting" required<?= invalid_attrs($errors, 'purpose') ?>>
            <?= field_error($errors, 'purpose') ?>
        </div>

        <div class="field field-full">
            <label for="notes">Notes <span class="optional">optional</span></label>
            <textarea id="notes" name="notes" class="input" rows="2" maxlength="500"<?= invalid_attrs($errors, 'notes') ?>><?= e($data['notes']) ?></textarea>
            <?= field_error($errors, 'notes') ?>
            <p class="field-hint">Operational notes only. Do not record sensitive personal information.</p>
        </div>
    </div>

    <h2 class="form-section">Visitor card</h2>

    <div class="form-grid">
        <div class="field">
            <label for="card">Card to issue <span class="required" aria-hidden="true">*</span></label>
            <select id="card" name="card" class="input mono" required<?= invalid_attrs($errors, 'card') ?>>
                <option value="">Choose a card</option>
                <?php foreach ($cards as $card): ?>
                    <option value="<?= (int) $card['id'] ?>"<?= $data['card'] === (string) $card['id'] ? ' selected' : '' ?>>
                        <?= e($card['card_number']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="none"<?= $data['card'] === 'none' ? ' selected' : '' ?>>No card</option>
            </select>
            <?= field_error($errors, 'card') ?>
            <p class="field-hint">
                <?= count($cards) ?> card<?= count($cards) === 1 ? '' : 's' ?> available.
                <?php if (!$cards): ?>Ask an administrator to free up or add cards.<?php endif; ?>
            </p>
        </div>
    </div>

    <div class="form-actions">
        <a class="pill-button" href="<?= e(url('dashboard.php')) ?>">Cancel</a>
        <button type="submit" class="pill-button pill-primary">Check in visitor</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
