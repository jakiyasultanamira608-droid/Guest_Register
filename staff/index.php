<?php
// Staff Management: list of staff accounts (FR-12, administrators only)
require __DIR__ . '/../includes/init.php';

$user = require_role('admin');

$staffList = $pdo->query(
    'SELECT id, name, email, role, is_active, created_at
     FROM staff
     ORDER BY is_active DESC, name ASC'
)->fetchAll();

$pageTitle = 'Staff';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head page-head-actions">
    <div>
        <h1>Staff</h1>
        <p class="muted">Accounts that can log in to Guest Register</p>
    </div>
    <a class="pill-button pill-primary" href="<?= e(url('staff/create.php')) ?>"><?= icon('plus') ?>Add staff</a>
</div>

<section class="panel panel-flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Created</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $staff): ?>
                    <tr<?= (int) $staff['is_active'] === 1 ? '' : ' class="row-muted"' ?>>
                        <td>
                            <div class="cell-person">
                                <span class="avatar avatar-sm"><?= e(initials($staff['name'])) ?></span>
                                <span>
                                    <strong><?= e($staff['name']) ?></strong>
                                    <?php if ((int) $staff['id'] === (int) $user['id']): ?>
                                        <span class="tag">You</span>
                                    <?php endif; ?>
                                    <small><?= e($staff['email']) ?></small>
                                </span>
                            </div>
                        </td>
                        <td><span class="badge"><?= e(role_label($staff['role'])) ?></span></td>
                        <td>
                            <?php if ((int) $staff['is_active'] === 1): ?>
                                <span class="status status-active">Active</span>
                            <?php else: ?>
                                <span class="status status-inactive">Deactivated</span>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap"><?= e(format_date($staff['created_at'])) ?></td>
                        <td class="cell-actions">
                            <a class="link-button" href="<?= e(url('staff/edit.php?id=' . $staff['id'])) ?>">
                                Edit<span class="sr-only"> <?= e($staff['name']) ?></span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
