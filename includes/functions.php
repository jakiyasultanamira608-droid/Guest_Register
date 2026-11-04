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
