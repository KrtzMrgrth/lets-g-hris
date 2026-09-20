<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$_SESSION['flash_message'] = 'Sick leave is now filed from the main Leave Application page. Please use the leave form to submit it.';
redirect('leave_application.php');
