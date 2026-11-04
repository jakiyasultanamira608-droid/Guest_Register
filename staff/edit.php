<?php
// Staff Management: edit, deactivate or reset the password of an account
// (FR-12, administrators only). Accounts are never deleted, because visit
// records keep a link to the staff member who registered them.
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/staff.php';

$user = require_role('admin');

$staffId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

$stmt = $pdo->prepare('SELECT id, name, email, role, is_active FROM staff WHERE id = ?');
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    http_response_code(404);
    $pageTitle = 'Staff not found';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="panel"><h1>Staff member not found</h1>'
        . '<p class="muted">This account does not exist.</p>'
        . '<a class="pill-button pill-primary" href="' . e(url('staff/index.php')) . '">Back to staff</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$isSelf = (int) $staff['id'] === (int) $user['id'];

$data = [
    'name'             => $staff['name'],
    'email'            => $staff['email'],
    'role'             => $staff['role'],
    'is_active'        => (int) $staff['is_active'],
    'password'         => '',
    'password_confirm' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $data = staff_form_data();

    // Administrators cannot change their own role or deactivate themselves.
    // Since only an active administrator can use this page, this also
    // guarantees the system always keeps at least one active administrator.
    if ($isSelf) {
        $data['role']      = $staff['role'];
        $data['is_active'] = 1;
    }

    $errors = validate_staff($pdo, $data, (int) $staff['id']);

    if (!$errors) {
        try {
            $fields = 'name = ?, email = ?, role = ?, is_active = ?';
            $values = [$data['name'], $data['email'], $data['role'], $data['is_active']];

            if ($data['password'] !== '') {
                $fields  .= ', password_hash = ?';
                $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $values[] = $staff['id'];
            $update = $pdo->prepare("UPDATE staff SET $fields WHERE id = ?");
            $update->execute($values);

            flash('success', 'Changes to ' . $data['name'] . ' saved.');
            redirect('staff/index.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                throw $e;
            }
            $errors['email'] = 'Another staff account already uses this email.';
        }
    }

    $data['password'] = $data['password_confirm'] = '';
}

$isEdit      = true;
$formAction  = url('staff/edit.php?id=' . $staff['id']);
$submitLabel = 'Save changes';

$pageTitle = 'Edit ' . $staff['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <a class="back-link" href="<?= e(url('staff/index.php')) ?>"><?= icon('back') ?>Staff</a>
    <h1>Edit <?= e($staff['name']) ?></h1>
    <p class="muted">Update details, change the role, reset the password or deactivate the account.</p>
</div>

<?php require __DIR__ . '/../includes/staff_form.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
