<?php
// Staff account form, shared by staff/create.php and staff/edit.php.
// Expects: $data, $errors, $isEdit, $isSelf, $formAction, $submitLabel
?>
<form class="form panel" method="post" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">Please fix the highlighted fields.</div>
    <?php endif; ?>

    <h2>Account details</h2>

    <div class="form-grid">
        <div class="field">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" class="input" value="<?= e($data['name']) ?>"
                   maxlength="100" autocomplete="off" required<?= invalid_attrs($errors, 'name') ?>>
            <?= field_error($errors, 'name') ?>
        </div>

        <div class="field">
            <label for="email">Email (used to log in)</label>
            <input type="email" id="email" name="email" class="input" value="<?= e($data['email']) ?>"
                   maxlength="150" autocomplete="off" required<?= invalid_attrs($errors, 'email') ?>>
            <?= field_error($errors, 'email') ?>
        </div>

        <div class="field">
            <label for="role">Role</label>
            <select id="role" name="role" class="input" required<?= $isSelf ? ' disabled' : '' ?><?= invalid_attrs($errors, 'role') ?>>
                <option value="">Choose a role</option>
                <?php foreach (STAFF_ROLES as $role): ?>
                    <option value="<?= e($role) ?>"<?= $data['role'] === $role ? ' selected' : '' ?>><?= e(role_label($role)) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'role') ?>
        </div>

        <?php if ($isEdit): ?>
            <div class="field">
                <label for="is_active">Account status</label>
                <select id="is_active" name="is_active" class="input"<?= $isSelf ? ' disabled' : '' ?><?= invalid_attrs($errors, 'is_active') ?>>
                    <option value="1"<?= $data['is_active'] === 1 ? ' selected' : '' ?>>Active - can log in</option>
                    <option value="0"<?= $data['is_active'] === 0 ? ' selected' : '' ?>>Deactivated - cannot log in</option>
                </select>
                <?= field_error($errors, 'is_active') ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isSelf): ?>
        <p class="hint">You cannot change your own role or deactivate your own account.</p>
    <?php endif; ?>

    <h2 class="form-section">Password</h2>
    <p class="hint">
        <?= $isEdit ? 'Leave blank to keep the current password. ' : '' ?>At least <?= PASSWORD_MIN_LENGTH ?> characters.
    </p>

    <div class="form-grid">
        <div class="field">
            <label for="password"><?= $isEdit ? 'New password' : 'Password' ?></label>
            <input type="password" id="password" name="password" class="input"
                   autocomplete="new-password"<?= $isEdit ? '' : ' required' ?><?= invalid_attrs($errors, 'password') ?>>
            <?= field_error($errors, 'password') ?>
        </div>

        <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" class="input"
                   autocomplete="new-password"<?= $isEdit ? '' : ' required' ?><?= invalid_attrs($errors, 'password_confirm') ?>>
            <?= field_error($errors, 'password_confirm') ?>
        </div>
    </div>

    <div class="form-actions">
        <a class="pill-button" href="<?= e(url('staff/index.php')) ?>">Cancel</a>
        <button type="submit" class="pill-button pill-primary"><?= e($submitLabel) ?></button>
    </div>
</form>
