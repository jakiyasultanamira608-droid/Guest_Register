<?php
// Home page: send staff to the dashboard, everyone else to the login page.
require __DIR__ . '/includes/init.php';

redirect(current_user() ? 'dashboard.php' : 'login.php');
