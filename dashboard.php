<?php
// Dashboard - visitor counts and quick actions are added in Step 7 (FR-07).
require __DIR__ . '/includes/init.php';

$user = require_login();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<section class="card">
    <h1>Welcome, <?= e($user['name']) ?></h1>
    <p class="muted">You are logged in as <strong><?= e(role_label($user['role'])) ?></strong>.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
