<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect_after_login();
}

$error = '';

if (is_post()) {
    if (!verify_csrf_token()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim_str($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $result = authenticate($username, $password);

        if ($result['success']) {
            redirect_after_login();
        }

        $error = $result['message'];
    }
}

$pageTitle = 'Login';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-card">
    <h1><?= e(APP_NAME) ?></h1>
    <p class="auth-subtitle">Sign in to continue</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>" class="form" autocomplete="off">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="" required autofocus autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" value="" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>

    <div class="alert alert-info auth-accounts" role="status">
        <p><strong>Admin</strong> — Username: <code>admin</code> / Password: <code>admin123</code></p>
        <p><strong>User</strong> — Username: <code>demo</code> / Password: <code>user123</code></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
