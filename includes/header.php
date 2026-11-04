<?php
// Shared top of every page. Set $pageTitle before including this file.
// Logged-in staff get the dark app layout with a sidebar;
// the login page gets the light, centered layout.
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Guest Register') ?> | Guest Register</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= $user ? 'app' : 'guest' ?>">

<?php if ($user): ?>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= e(url('dashboard.php')) ?>">
            <?= logo_svg() ?>
            <span>Guest Register</span>
        </a>

        <nav class="side-nav" aria-label="Main">
            <a href="<?= e(url('dashboard.php')) ?>"<?= nav_attrs('dashboard.php') ?>>
                <?= icon('home') ?>Dashboard
            </a>
            <?php if (in_array($user['role'], ['admin', 'receptionist'], true)): ?>
                <a href="<?= e(url('visits/register.php')) ?>"<?= nav_attrs('visits/register.php') ?>>
                    <?= icon('user-plus') ?>Register visitor
                </a>
            <?php endif; ?>
            <a href="<?= e(url('visits/current.php')) ?>"<?= nav_attrs('visits/current.php') ?>>
                <?= icon('inside') ?>Current visitors
                <?php if ($activeCount = count_active_visits()): ?>
                    <span class="nav-count"><?= $activeCount ?><span class="sr-only"> inside</span></span>
                <?php endif; ?>
            </a>
            <a href="<?= e(url('visits/index.php')) ?>"<?= nav_attrs('visits/index.php') ?>>
                <?= icon('history') ?>Visit history
            </a>
            <a href="<?= e(url('cards/index.php')) ?>"<?= nav_attrs('cards/') ?>>
                <?= icon('card') ?>Cards
            </a>

            <?php if ($user['role'] === 'admin'): ?>
                <p class="side-nav-label">Administration</p>
                <a href="<?= e(url('staff/index.php')) ?>"<?= nav_attrs('staff/') ?>>
                    <?= icon('users') ?>Staff
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-foot">
            <div class="user-chip">
                <span class="avatar"><?= e(initials($user['name'])) ?></span>
                <span class="user-meta">
                    <strong><?= e($user['name']) ?></strong>
                    <small><?= e(role_label($user['role'])) ?></small>
                </span>
            </div>
            <form method="post" action="<?= e(url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="side-button"><?= icon('logout') ?>Log out</button>
            </form>
        </div>
    </aside>

    <div class="main">
        <header class="mobile-bar">
            <button type="button" class="menu-button" aria-controls="sidebar" aria-expanded="false" data-menu-toggle>
                <?= icon('menu') ?><span class="sr-only">Open menu</span>
            </button>
            <a class="brand" href="<?= e(url('dashboard.php')) ?>">
                <?= logo_svg() ?>
                <span>Guest Register</span>
            </a>
        </header>

        <main class="content">
<?php else: ?>
<main class="auth">
<?php endif; ?>

    <?php foreach (get_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
