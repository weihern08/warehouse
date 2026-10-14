<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = db();
$current = current_user();
$action = trim_str($_GET['action'] ?? 'list');
$id = positive_int($_GET['id'] ?? null);

function fetch_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, full_name, username, role, is_active, created_at, updated_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function username_exists(PDO $pdo, string $username, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u AND id <> :id');
        $stmt->execute(['u' => $username, 'id' => $excludeId]);
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
    }
    return (int) $stmt->fetchColumn() > 0;
}

if (is_post()) {
    require_csrf();
    $postAction = trim_str($_POST['action'] ?? '');

    try {
        if ($postAction === 'create' || $postAction === 'update') {
            $fullName = trim_str($_POST['full_name'] ?? '');
            $username = trim_str($_POST['username'] ?? '');
            $role = trim_str($_POST['role'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $editId = positive_int($_POST['id'] ?? null);

            $errors = [];
            if ($fullName === '') {
                $errors[] = 'Full name is required.';
            }
            if ($username === '') {
                $errors[] = 'Username is required.';
            }
            if (!validate_role($role)) {
                $errors[] = 'Role must be admin or user.';
            }

            if ($postAction === 'create') {
                $pwdError = null;
                if (!validate_password($password, $pwdError)) {
                    $errors[] = $pwdError ?? 'Invalid password.';
                }
                if ($username !== '' && username_exists($pdo, $username)) {
                    $errors[] = 'Username already exists.';
                }
                if ($errors) {
                    flash('error', implode(' ', $errors));
                    redirect('admin/users.php?action=add');
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, username, password_hash, role, is_active)
                     VALUES (:full_name, :username, :password_hash, :role, :is_active)'
                );
                $stmt->execute([
                    'full_name'     => $fullName,
                    'username'      => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => $role,
                    'is_active'     => $isActive,
                ]);

                flash('success', 'User created successfully.');
                redirect('admin/users.php');
            }

            if ($editId === null) {
                flash('error', 'Invalid user.');
                redirect('admin/users.php');
            }

            $existing = fetch_user($pdo, $editId);
            if ($existing === null) {
                flash('error', 'User not found.');
                redirect('admin/users.php');
            }

            if (username_exists($pdo, $username, $editId)) {
                $errors[] = 'Username already exists.';
            }

            // Prevent locking yourself out of admin
            if ($editId === (int) $current['id']) {
                $role = 'admin';
                $isActive = 1;
            }

            if ($password !== '') {
                $pwdError = null;
                if (!validate_password($password, $pwdError)) {
                    $errors[] = $pwdError ?? 'Invalid password.';
                }
            }

            if ($errors) {
                flash('error', implode(' ', $errors));
                redirect('admin/users.php?action=edit&id=' . $editId);
            }

            if ($password !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET full_name = :full_name,
                         username = :username,
                         role = :role,
                         is_active = :is_active,
                         password_hash = :password_hash
                     WHERE id = :id'
                );
                $stmt->execute([
                    'full_name'     => $fullName,
                    'username'      => $username,
                    'role'          => $role,
                    'is_active'     => $isActive,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id'            => $editId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET full_name = :full_name,
                         username = :username,
                         role = :role,
                         is_active = :is_active
                     WHERE id = :id'
                );
                $stmt->execute([
                    'full_name' => $fullName,
                    'username'  => $username,
                    'role'      => $role,
                    'is_active' => $isActive,
                    'id'        => $editId,
                ]);
            }

            flash('success', 'User updated successfully.');
            redirect('admin/users.php');
        }

        if ($postAction === 'reset_password') {
            $resetId = positive_int($_POST['id'] ?? null);
            $password = (string) ($_POST['password'] ?? '');
            $pwdError = null;

            if ($resetId === null) {
                flash('error', 'Invalid user.');
                redirect('admin/users.php');
            }
            if ($resetId === (int) $current['id']) {
                flash('error', 'Use the edit form to change your own password.');
                redirect('admin/users.php');
            }
            if (!validate_password($password, $pwdError)) {
                flash('error', $pwdError ?? 'Invalid password.');
                redirect('admin/users.php?action=reset&id=' . $resetId);
            }

            $stmt = $pdo->prepare(
                'UPDATE users SET password_hash = :hash WHERE id = :id'
            );
            $stmt->execute([
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'id'   => $resetId,
            ]);

            flash('success', 'Password reset successfully.');
            redirect('admin/users.php');
        }

        if ($postAction === 'delete') {
            $deleteId = positive_int($_POST['id'] ?? null);

            if ($deleteId === null) {
                flash('error', 'Invalid user.');
                redirect('admin/users.php');
            }
            if ($deleteId === (int) $current['id']) {
                flash('error', 'You cannot delete your own account.');
                redirect('admin/users.php');
            }

            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);

            flash('success', 'User deleted successfully.');
            redirect('admin/users.php');
        }
    } catch (Throwable $e) {
        log_error('Admin users POST failed', $e);
        flash('error', friendly_error());
        redirect('admin/users.php');
    }
}

if ($action === 'add' || ($action === 'edit' && $id !== null) || ($action === 'reset' && $id !== null)) {
    $userRow = [
        'full_name' => '',
        'username'  => '',
        'role'      => 'user',
        'is_active' => 1,
    ];

    if ($action !== 'add') {
        $found = fetch_user($pdo, $id);
        if ($found === null) {
            flash('error', 'User not found.');
            redirect('admin/users.php');
        }
        $userRow = $found;
    }

    $pageTitle = match ($action) {
        'add' => 'Add User',
        'reset' => 'Reset Password',
        default => 'Edit User',
    };

    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="card">
        <h2><?= e($pageTitle) ?></h2>

        <?php if ($action === 'reset'): ?>
            <form method="post" action="<?= e(url('admin/users.php')) ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                <p>Reset password for <strong><?= e($userRow['full_name']) ?></strong> (<?= e($userRow['username']) ?>)</p>
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                    <small class="muted">Minimum <?= PASSWORD_MIN_LENGTH ?> characters.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                    <a class="btn btn-secondary" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('admin/users.php')) ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $action === 'add' ? 'create' : 'update' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required maxlength="120"
                               value="<?= e((string) $userRow['full_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required maxlength="60"
                               value="<?= e((string) $userRow['username']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required <?= ($action === 'edit' && $id === (int) $current['id']) ? 'disabled' : '' ?>>
                            <option value="user" <?= $userRow['role'] === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= $userRow['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <?php if ($action === 'edit' && $id === (int) $current['id']): ?>
                            <input type="hidden" name="role" value="admin">
                            <small class="muted">You cannot change your own role.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="password">Password<?= $action === 'edit' ? ' (optional)' : '' ?></label>
                        <input type="password" id="password" name="password"
                               <?= $action === 'add' ? 'required' : '' ?>
                               minlength="<?= PASSWORD_MIN_LENGTH ?>">
                        <small class="muted">
                            <?= $action === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : 'Minimum ' . PASSWORD_MIN_LENGTH . ' characters.' ?>
                        </small>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1"
                                <?= (int) $userRow['is_active'] === 1 ? 'checked' : '' ?>
                                <?= ($action === 'edit' && $id === (int) $current['id']) ? 'disabled' : '' ?>>
                            Active
                        </label>
                        <?php if ($action === 'edit' && $id === (int) $current['id']): ?>
                            <input type="hidden" name="is_active" value="1">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a class="btn btn-secondary" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$users = $pdo->query(
    'SELECT id, full_name, username, role, is_active, created_at
     FROM users
     ORDER BY full_name ASC'
)->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <a class="btn btn-primary" href="<?= e(url('admin/users.php?action=add')) ?>">Add User</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['username']) ?></td>
                        <td><?= e(ucfirst($row['role'])) ?></td>
                        <td><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                        <td><?= e(format_datetime($row['created_at'])) ?></td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('admin/users.php?action=edit&id=' . $row['id'])) ?>">Edit</a>
                            <?php if ((int) $row['id'] !== (int) $current['id']): ?>
                                <a class="btn btn-sm btn-secondary" href="<?= e(url('admin/users.php?action=reset&id=' . $row['id'])) ?>">Reset Password</a>
                                <form method="post" action="<?= e(url('admin/users.php')) ?>" style="display:inline;"
                                      onsubmit="return confirm('Delete this user?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
