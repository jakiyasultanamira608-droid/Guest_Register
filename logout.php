<?php
// Staff logout (FR-01). Only accepts POST with a CSRF token, so another
// website cannot log a user out by linking to this page.
require __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_verify();
logout_user();

flash('success', 'You have been logged out.');
redirect('login.php');
