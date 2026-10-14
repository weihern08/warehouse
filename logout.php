<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

logout_user();

session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
