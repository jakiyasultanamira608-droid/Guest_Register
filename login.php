<?php
// Staff login (FR-01)
require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('dashboard.php');
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = post('email');
    $password = post('password', false);

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password_hash, is_active FROM staff WHERE email = ?');
        $stmt->execute([$email]);
        $staff = $stmt->fetch();

        // Always check a password, even for unknown emails (see DUMMY_PASSWORD_HASH)
        $passwordOk = password_verify($password, $staff ? $staff['password_hash'] : DUMMY_PASSWORD_HASH);

        if ($staff && $passwordOk && (int) $staff['is_active'] === 1) {
            // Upgrade the stored hash if PHP's default algorithm has changed
            if (password_needs_rehash($staff['password_hash'], PASSWORD_DEFAULT)) {
                $update = $pdo->prepare('UPDATE staff SET password_hash = ? WHERE id = ?');
                $update->execute([password_hash($password, PASSWORD_DEFAULT), $staff['id']]);
            }

            login_user((int) $staff['id']);
            redirect('dashboard.php');
        }

        // Same message for every failure: never reveal which part was wrong
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-box">
    <?= logo_svg('auth-logo') ?>
    <h1>Log in to Guest Register</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="<?= e(url('login.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="email" class="sr-only">Email address</label>
        <input type="email" id="email" name="email" class="pill-input" value="<?= e($email) ?>"
               placeholder="Email address" maxlength="150" autocomplete="username" required autofocus>

        <label for="password" class="sr-only">Password</label>
        <input type="password" id="password" name="password" class="pill-input"
               placeholder="Password" autocomplete="current-password" required>

        <button type="submit" class="pill-button pill-primary">Log in</button>
    </form>

    <p class="auth-note">Staff accounts are created by your administrator.</p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
