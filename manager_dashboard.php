<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (isAdmin()) {
    redirect('admin_dashboard.php');
}

if (isManager()) {
    redirect('manager_overview.php');
}

redirect('dashboard.php');
