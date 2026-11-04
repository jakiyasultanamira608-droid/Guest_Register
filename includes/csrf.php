<?php
// CSRF protection: every POST form carries a secret token that only
// this user's session knows, so other websites cannot submit forms
// on the user's behalf.

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Hidden input to place inside every <form method="post">.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Call at the start of every POST handler. Stops the request if the token is wrong.
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';

    if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(400);
        exit('Your form has expired. Please go back, reload the page and try again.');
    }
}
