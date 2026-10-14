<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$flash = get_flash();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if ($user): ?>
<div class="app-shell">
    <?php require __DIR__ . '/nav.php'; ?>
    <div class="main-area">
        <header class="topbar">
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Toggle navigation">☰</button>
            <div class="topbar-title"><?= e($pageTitle) ?></div>
            <div class="topbar-user">
                <span class="user-name"><?= e($user['full_name']) ?></span>
                <span class="user-role"><?= e(ucfirst($user['role'])) ?></span>
            </div>
        </header>
        <main class="content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>
<?php else: ?>
<main class="auth-wrap">
    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
