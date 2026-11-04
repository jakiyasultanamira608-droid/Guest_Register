<?php
// Staff Management: create a staff account (FR-12, administrators only)
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/staff.php';

require_role('admin');

$data = [
    'name'             => '',
    'email'            => '',
    'role'             => '',
    'is_active'        => 1,
    'password'         => '',
    'password_confirm' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $data   = staff_form_data();
    $errors = validate_staff($pdo, $data, null);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO staff (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $data['name'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'],
            ]);

            flash('success', 'Staff account for ' . $data['name'] . ' created.');
            redirect('staff/index.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                throw $e;
            }
            $errors['email'] = 'Another staff account already uses this email.';
        }
    }

    // Never send passwords back to the browser
    $data['password'] = $data['password_confirm'] = '';
}

$isEdit      = false;
$isSelf      = false;
$formAction  = url('staff/create.php');
$submitLabel = 'Create account';

$pageTitle = 'Add staff';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <a class="back-link" href="<?= e(url('staff/index.php')) ?>"><?= icon('back') ?>Staff</a>
    <h1>Add staff</h1>
    <p class="muted">The new staff member can log in with this email and password.</p>
</div>

<?php require __DIR__ . '/../includes/staff_form.php'; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
