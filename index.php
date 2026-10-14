<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect_after_login();
}

redirect('login.php');
