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

// Marks the current page's link in the sidebar. Pass a folder such as
// 'staff/' to highlight the link on every page inside that folder.
function nav_attrs(string $path): string
{
    $current = $_SERVER['SCRIPT_NAME'] ?? '';
    $target  = url($path);

    $active = str_ends_with($path, '/')
        ? str_starts_with($current, $target)
        : $current === $target;

    return $active ? ' class="active" aria-current="page"' : '';
}

// Error message under a form field, if that field has one.
function field_error(array $errors, string $field): string
{
    if (empty($errors[$field])) {
        return '';
    }

    return '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
}

// Marks a form field as invalid for screen readers and styling.
function invalid_attrs(array $errors, string $field): string
{
    return empty($errors[$field])
        ? ''
        : ' aria-invalid="true" aria-describedby="' . e($field) . '-error"';
}

// Format a database date/time for display, e.g. "4 Nov 2026".
function format_date(?string $value, string $format = 'j M Y'): string
{
    return $value ? date($format, strtotime($value)) : '';
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
        'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.3-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18 14.8c2 .7 3.2 2.5 3.5 5.2"/>',
        'plus'   => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'back'   => '<path d="M19 12H5"/><path d="M11 18l-6-6 6-6"/>',
    ];

    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}
