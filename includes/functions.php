<?php
declare(strict_types=1);

/**
 * Shared helper functions.
 */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(BASE_PATH, '/');
    $path = '/' . ltrim($path, '/');

    if ($path === '/') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . $path;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['_flash'])) {
        return null;
    }

    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);

    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY]) || !is_string($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }

    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $token = null): bool
{
    $token ??= $_POST['_csrf'] ?? null;
    $sessionToken = $_SESSION[CSRF_TOKEN_KEY] ?? '';

    if (!is_string($token) || $token === '' || !is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function require_csrf(): void
{
    if (!verify_csrf_token()) {
        flash('error', 'Invalid security token. Please try again.');

        $user = current_user();
        if ($user && ($user['role'] ?? '') === 'admin') {
            redirect('admin/dashboard.php');
        }
        if ($user) {
            redirect('user/dashboard.php');
        }
        redirect('login.php');
    }
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return is_string($ip) ? $ip : '0.0.0.0';
}

function positive_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $int = (int) $value;
    return $int > 0 ? $int : null;
}

function parse_non_negative_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_string($value)) {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d+$/', $value)) {
            return null;
        }
    } elseif (!is_int($value) && !(is_float($value) && $value == (int) $value && $value >= 0)) {
        if (!is_numeric($value)) {
            return null;
        }
        if (str_contains((string) $value, '.')) {
            return null;
        }
    }

    $int = (int) $value;
    return $int >= 0 ? $int : null;
}

function parse_positive_int(mixed $value): ?int
{
    $int = parse_non_negative_int($value);
    return ($int !== null && $int > 0) ? $int : null;
}

function trim_str(mixed $value): string
{
    return trim((string) ($value ?? ''));
}

function validate_role(string $role): bool
{
    return in_array($role, ['admin', 'user'], true);
}

function validate_movement_type(string $type): bool
{
    return in_array($type, ['IN', 'OUT'], true);
}

function validate_password(string $password, ?string &$error = null): bool
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        return false;
    }

    return true;
}

function paginate(int $total, int $page, int $perPage = 20): array
{
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'page'        => $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

function format_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    $ts = strtotime($date);
    return $ts ? date('Y-m-d', $ts) : e($date);
}

function format_datetime(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    $ts = strtotime($datetime);
    return $ts ? date('Y-m-d H:i', $ts) : e($datetime);
}

function log_error(string $message, ?Throwable $e = null): void
{
    $detail = $message;
    if ($e !== null) {
        $detail .= ' | ' . $e->getMessage();
    }
    error_log($detail);
}

function friendly_error(): string
{
    return 'Something went wrong. Please try again.';
}
