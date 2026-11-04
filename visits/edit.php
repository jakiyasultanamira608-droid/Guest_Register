<?php
// Edit Visit: correct mistakes in visitor or visit details (FR-09).
// Receptionists and administrators only. Check-in/check-out times and the
// card cannot be changed, so card tracking always stays accurate.
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/visits.php';

$user = require_role('admin', 'receptionist');

$visitId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$stmt = $pdo->prepare(
    'SELECT v.id, v.reference, v.status, v.visitor_id, v.department_id, v.host_name, v.purpose, v.notes,
            v.check_in_at, v.check_out_at,
            vi.full_name, vi.organization, vi.contact_number,
            c.card_number,
            (SELECT COUNT(*) FROM visits o WHERE o.visitor_id = v.visitor_id) AS visitor_visit_count
     FROM visits v
     JOIN visitors vi              ON vi.id = v.visitor_id
     LEFT JOIN card_assignments ca ON ca.visit_id = v.id
     LEFT JOIN cards c             ON c.id = ca.card_id
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
        . '<a class="pill-button pill-primary" href="' . e(url('visits/index.php')) . '">Back to visit history</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$data = [
    'full_name'      => $visit['full_name'],
    'organization'   => (string) $visit['organization'],
    'contact_number' => (string) $visit['contact_number'],
    'department_id'  => (string) $visit['department_id'],
    'host_name'      => (string) $visit['host_name'],
    'purpose'        => $visit['purpose'],
    'notes'          => (string) $visit['notes'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $data = visit_form_data();
    unset($data['card']);   // the card can never be changed here
    $data['current_department_id'] = $visit['department_id'];

    $errors = validate_visit_details($pdo, $data);

    if (!$errors) {
        update_visit_details($pdo, (int) $visit['id'], (int) $visit['visitor_id'], $data, (int) $user['id']);

        flash('success', 'Changes to ' . $visit['reference'] . ' saved.');
        redirect('visits/view.php?id=' . $visit['id']);
    }
}

// Active departments, plus this visit's department even if it was deactivated later
$stmt = $pdo->prepare('SELECT id, name FROM departments WHERE is_active = 1 OR id = ? ORDER BY name');
$stmt->execute([$visit['department_id']]);
$departments = $stmt->fetchAll();

$otherVisits = (int) $visit['visitor_visit_count'] - 1;

$pageTitle = 'Edit ' . $visit['reference'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <a class="back-link" href="<?= e(url('visits/view.php?id=' . $visit['id'])) ?>"><?= icon('back') ?><?= e($visit['reference']) ?></a>
    <h1>Edit visit</h1>
    <p class="muted">Correct mistakes in the visitor or visit details.</p>
</div>

<form class="form panel" method="post" action="<?= e(url('visits/edit.php?id=' . $visit['id'])) ?>" novalidate data-submit-once>
    <?= csrf_field() ?>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">Please fix the highlighted fields.</div>
    <?php endif; ?>

    <h2>Visitor</h2>
    <?php if ($otherVisits > 0): ?>
        <p class="hint">
            This visitor has <?= $otherVisits ?> other visit<?= $otherVisits === 1 ? '' : 's' ?>.
            Changes to their name, company or phone apply to all of them.
        </p>
    <?php endif; ?>

    <div class="form-grid">
        <div class="field field-full">
            <label for="full_name">Full name <span class="required" aria-hidden="true">*</span></label>
            <input type="text" id="full_name" name="full_name" class="input" value="<?= e($data['full_name']) ?>"
                   maxlength="150" autocomplete="off" required<?= invalid_attrs($errors, 'full_name') ?>>
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
        </div>
    </div>

    <h2 class="form-section">Visit</h2>

    <div class="form-grid">
        <div class="field">
            <label for="department_id">Department <span class="required" aria-hidden="true">*</span></label>
            <select id="department_id" name="department_id" class="input" required<?= invalid_attrs($errors, 'department_id') ?>>
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
                   maxlength="255" autocomplete="off" required<?= invalid_attrs($errors, 'purpose') ?>>
            <?= field_error($errors, 'purpose') ?>
        </div>

        <div class="field field-full">
            <label for="notes">Notes <span class="optional">optional</span></label>
            <textarea id="notes" name="notes" class="input" rows="2" maxlength="500"<?= invalid_attrs($errors, 'notes') ?>><?= e($data['notes']) ?></textarea>
            <?= field_error($errors, 'notes') ?>
        </div>
    </div>

    <h2 class="form-section">Locked details</h2>
    <p class="hint">These are recorded by the system and cannot be edited, so visit and card records stay accurate.</p>

    <dl class="details details-locked">
        <dt><?= icon('lock') ?>Checked in</dt>
        <dd><?= e(format_date($visit['check_in_at'], 'j M Y, g:i A')) ?></dd>
        <dt><?= icon('lock') ?>Checked out</dt>
        <dd><?= $visit['check_out_at'] ? e(format_date($visit['check_out_at'], 'j M Y, g:i A')) : '<span class="muted">Still inside</span>' ?></dd>
        <dt><?= icon('lock') ?>Card</dt>
        <dd><?= $visit['card_number'] ? '<span class="mono">' . e($visit['card_number']) . '</span>' : '<span class="muted">No card</span>' ?></dd>
    </dl>

    <div class="form-actions">
        <a class="pill-button" href="<?= e(url('visits/view.php?id=' . $visit['id'])) ?>">Cancel</a>
        <button type="submit" class="pill-button pill-primary">Save changes</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
