<?php
// Login sessions and access control (FR-01, FR-02).

// Log out automatically after 30 minutes without activity.
const SESSION_TIMEOUT = 30 * 60;

// Random hash used when an email is not found, so a failed login takes
// the same time whether or not the email exists.
const DUMMY_PASSWORD_HASH = '$2y$10$epK0YpoVI2PtNKDjZheJR.egKw.VtzCftn4WA8KWK8L3rocsClh16';

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_name('guest_register_session');
    session_set_cookie_params([
        'path'     => base_path() . '/',
        'httponly' => true,                     // JavaScript cannot read the cookie
        'samesite' => 'Lax',                    // not sent with forms from other sites
        'secure'   => !empty($_SERVER['HTTPS']), // HTTPS-only when HTTPS is used
    ]);
    session_start();
}

// Mark a staff member as logged in.
function login_user(int $staffId): void
{
    // New session ID after login prevents session fixation attacks
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

    $_SESSION['staff_id']      = $staffId;
    $_SESSION['last_activity'] = time();
}

// End the session completely, then start a fresh empty one for messages.
function logout_user(): void
{
    $_SESSION = [];

    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], '', $params['secure'], $params['httponly']);
    session_destroy();

    session_start();
    session_regenerate_id(true);
}

// The logged-in staff member, or null if nobody is logged in.
// Re-reads the account each request, so a deactivated account is
// logged out immediately (FR-12).
function current_user(): ?array
{
    global $pdo;
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    if (empty($_SESSION['staff_id'])) {
        return null;
    }

    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logout_user();
        flash('info', 'Your session expired. Please log in again.');
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, name, email, role, is_active FROM staff WHERE id = ?');
    $stmt->execute([$_SESSION['staff_id']]);
    $staff = $stmt->fetch();

    if (!$staff || (int) $staff['is_active'] !== 1) {
        logout_user();
        return null;
    }

    $_SESSION['last_activity'] = time();
    $user = $staff;

    return $user;
}

// Put at the top of every protected page.
function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        redirect('login.php');
    }

    return $user;
}

// Allow only the listed roles, e.g. require_role('admin').
// Checked on the server, so changing the URL cannot bypass it (FR-02).
function require_role(string ...$roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<section class="card"><h1>Access denied</h1>'
            . '<p>You do not have permission to open this page.</p>'
            . '<p><a class="button" href="' . e(url('dashboard.php')) . '">Back to dashboard</a></p></section>';
        require __DIR__ . '/footer.php';
        exit;
    }

    return $user;
}
