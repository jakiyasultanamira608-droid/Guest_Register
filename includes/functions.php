<?php
// General helper functions used across the app.

// Escape text before printing it in HTML (prevents XSS).
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// The app's folder in the URL, e.g. "/Guest_Register_App".
// Worked out automatically so the app runs from any folder name.
function base_path(): string
{
    static $base = null;

    if ($base === null) {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $appDir  = str_replace('\\', '/', dirname(__DIR__));

        $base = ($docRoot !== '' && stripos($appDir, $docRoot) === 0)
            ? substr($appDir, strlen($docRoot))
            : '';
    }

    return $base;
}

// Build a link to a page in the app, e.g. url('dashboard.php').
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

// Send the browser to another page in the app and stop this script.
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// Read a text value from a submitted form. Returns '' if missing or not text.
function post(string $key, bool $trim = true): string
{
    $value = $_POST[$key] ?? '';

    if (!is_string($value)) {
        return '';
    }

    return $trim ? trim($value) : $value;
}

// Save a one-time message to show on the next page (e.g. "Visitor registered").
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// Get all saved messages and remove them so they show only once.
function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function role_label(string $role): string
{
    $labels = [
        'admin'        => 'Administrator',
        'receptionist' => 'Receptionist',
        'security'     => 'Security Officer',
    ];

    return $labels[$role] ?? $role;
}

// Up to two initials for the avatar, e.g. "System Administrator" -> "SA".
function initials(string $name): string
{
    $words    = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }

    return $initials;
}

// Marks the current page's link in the sidebar.
function nav_attrs(string $path): string
{
    return ($_SERVER['SCRIPT_NAME'] ?? '') === url($path)
        ? ' class="active" aria-current="page"'
        : '';
}

// Guest Register logo: a visitor badge inside a circle.
function logo_svg(string $class = 'logo'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 32 32" aria-hidden="true">'
        . '<circle cx="16" cy="16" r="16" fill="currentColor"/>'
        . '<rect x="10" y="7.5" width="12" height="17" rx="2.5" fill="none" stroke="var(--logo-inner)" stroke-width="2"/>'
        . '<circle cx="16" cy="14" r="2.3" fill="var(--logo-inner)"/>'
        . '<path d="M12.6 20.4c.8-1.7 2-2.5 3.4-2.5s2.6.8 3.4 2.5" fill="none" stroke="var(--logo-inner)" stroke-width="2" stroke-linecap="round"/>'
        . '</svg>';
}

// Small line icons for the sidebar and buttons.
function icon(string $name): string
{
    $paths = [
        'home'   => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h5v-6h4v6h5V9.5"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5"/><path d="M5 12h11"/>',
        'menu'   => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
    ];

    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}
