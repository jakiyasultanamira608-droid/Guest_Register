<?php
// Staff account rules shared by staff/create.php and staff/edit.php (FR-12).

const STAFF_ROLES = ['admin', 'receptionist', 'security'];

// bcrypt only uses the first 72 bytes of a password
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_BYTES  = 72;

// Read the staff form fields from the submitted POST data.
function staff_form_data(): array
{
    return [
        'name'             => post('name'),
        'email'            => mb_strtolower(post('email')),
        'role'             => post('role'),
        'is_active'        => post('is_active') === '0' ? 0 : 1,
        'password'         => post('password', false),
        'password_confirm' => post('password_confirm', false),
    ];
}

// Check the form data. Returns field => message for every problem found.
// $staffId is null when creating a new account.
function validate_staff(PDO $pdo, array $data, ?int $staffId): array
{
    $errors = [];

    if ($data['name'] === '') {
        $errors['name'] = 'Enter the staff member\'s name.';
    } elseif (mb_strlen($data['name']) > 100) {
        $errors['name'] = 'Name must be 100 characters or fewer.';
    }

    if ($data['email'] === '') {
        $errors['email'] = 'Enter an email address.';
    } elseif (mb_strlen($data['email']) > 150 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address, like name@example.com.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM staff WHERE email = ? AND id <> ?');
        $stmt->execute([$data['email'], $staffId ?? 0]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Another staff account already uses this email.';
        }
    }

    if (!in_array($data['role'], STAFF_ROLES, true)) {
        $errors['role'] = 'Choose a role.';
    }

    // A password is required for new accounts; when editing, blank means "keep current"
    if ($staffId === null || $data['password'] !== '' || $data['password_confirm'] !== '') {
        if ($data['password'] === '') {
            $errors['password'] = 'Enter a password.';
        } elseif (mb_strlen($data['password']) < PASSWORD_MIN_LENGTH) {
            $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif (strlen($data['password']) > PASSWORD_MAX_BYTES) {
            $errors['password'] = 'Password is too long.';
        } elseif ($data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }
    }

    return $errors;
}

// True when a database error is a duplicate unique value (e.g. same email
// saved by two admins at the same moment).
function is_duplicate_error(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}
