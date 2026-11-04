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

<section class="card login-card">
    <h1>Guest Register</h1>
    <p class="muted">Staff login</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>"
               maxlength="150" autocomplete="username" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>

        <button type="submit" class="button button-block">Log in</button>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
