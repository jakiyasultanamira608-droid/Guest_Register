<?php
// Shared top of every page. Set $pageTitle before including this file.
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Guest Register') ?> | Guest Register</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="<?= $user ? 'app' : 'guest' ?>">

<?php if ($user): ?>
<header class="topbar">
    <a class="brand" href="<?= e(url('dashboard.php')) ?>">Guest Register</a>

    <nav class="nav">
        <a href="<?= e(url('dashboard.php')) ?>">Dashboard</a>
    </nav>

    <div class="user-menu">
        <span class="user-name">
            <?= e($user['name']) ?>
            <small><?= e(role_label($user['role'])) ?></small>
        </span>
        <form method="post" action="<?= e(url('logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button button-secondary">Log out</button>
        </form>
    </div>
</header>
<?php endif; ?>

<main class="container">
    <?php foreach (get_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
