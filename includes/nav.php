<?php
declare(strict_types=1);

$user = current_user();
$role = $user['role'] ?? '';
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$dir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

function nav_active(string $file, string $dirName = ''): string
{
    global $script, $dir;
    if ($dirName !== '' && $dir !== $dirName) {
        return '';
    }
    return $script === $file ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?= e(url($role === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php')) ?>">
            <?= e(APP_NAME) ?>
        </a>
    </div>
    <nav class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
            <a class="<?= nav_active('dashboard.php', 'admin') ?>" href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a>
            <a class="<?= nav_active('items.php', 'admin') ?>" href="<?= e(url('admin/items.php')) ?>">Items</a>
            <a class="<?= nav_active('movements.php', 'admin') ?>" href="<?= e(url('admin/movements.php')) ?>">Stock Movements</a>
            <a class="<?= nav_active('reports.php', 'admin') ?>" href="<?= e(url('admin/reports.php')) ?>">Reports</a>
            <a class="<?= nav_active('users.php', 'admin') ?>" href="<?= e(url('admin/users.php')) ?>">Users</a>
        <?php else: ?>
            <a class="<?= nav_active('dashboard.php', 'user') ?>" href="<?= e(url('user/dashboard.php')) ?>">Dashboard</a>
            <a class="<?= nav_active('items.php', 'user') ?>" href="<?= e(url('user/items.php')) ?>">Items</a>
        <?php endif; ?>
        <a class="nav-logout" href="<?= e(url('logout.php')) ?>">Logout</a>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
