<?php
// Dashboard - visitor counts and quick actions are added in Step 7 (FR-07).
require __DIR__ . '/includes/init.php';

$user = require_login();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Dashboard</h1>
    <p class="muted">Welcome back, <?= e($user['name']) ?></p>
</div>

<section class="panel">
    <h2>Your account</h2>
    <dl class="details">
        <dt>Name</dt>
        <dd><?= e($user['name']) ?></dd>
        <dt>Email</dt>
        <dd><?= e($user['email']) ?></dd>
        <dt>Role</dt>
        <dd><span class="badge"><?= e(role_label($user['role'])) ?></span></dd>
    </dl>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
