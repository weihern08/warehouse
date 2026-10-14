<?php
declare(strict_types=1);

/**
 * Authentication and authorization helpers.
 */

function current_user(): ?array
{
    if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }

    return $_SESSION['user'];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();

    $user = current_user();
    $role = $user['role'] ?? '';

    if (!in_array($role, $roles, true)) {
        flash('error', 'You do not have permission to access that page.');
        if ($role === 'admin') {
            redirect('admin/dashboard.php');
        }
        redirect('user/dashboard.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'full_name' => (string) $user['full_name'],
        'username'  => (string) $user['username'],
        'role'      => (string) $user['role'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function find_user_by_username(string $username): ?array
{
    $stmt = db()->prepare(
        'SELECT id, full_name, username, password_hash, role, is_active
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function record_login_attempt(string $username): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (username, ip_address) VALUES (:username, :ip)'
    );
    $stmt->execute([
        'username' => $username,
        'ip'       => client_ip(),
    ]);
}

function clear_login_attempts(string $username): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE username = :username');
    $stmt->execute(['username' => $username]);
}

function is_login_locked(string $username): bool
{
    $windowStart = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SECONDS);

    $stmt = db()->prepare(
        'SELECT COUNT(*) AS attempt_count
         FROM login_attempts
         WHERE username = :username
           AND attempted_at >= :window_start'
    );
    $stmt->execute([
        'username'     => $username,
        'window_start' => $windowStart,
    ]);

    $count = (int) ($stmt->fetch()['attempt_count'] ?? 0);
    return $count >= LOGIN_MAX_ATTEMPTS;
}

function authenticate(string $username, string $password): array
{
    $username = trim($username);

    if ($username === '' || $password === '') {
        return ['success' => false, 'message' => 'Username and password are required.'];
    }

    if (is_login_locked($username)) {
        return [
            'success' => false,
            'message' => 'Too many failed login attempts. Please try again in a few minutes.',
        ];
    }

    $user = find_user_by_username($username);

    if ($user === null || !(int) $user['is_active']) {
        record_login_attempt($username);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        record_login_attempt($username);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    clear_login_attempts($username);
    login_user($user);

    return ['success' => true, 'user' => current_user()];
}

function redirect_after_login(): never
{
    $user = current_user();
    if (($user['role'] ?? '') === 'admin') {
        redirect('admin/dashboard.php');
    }
    redirect('user/dashboard.php');
}
