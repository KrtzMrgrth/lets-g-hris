<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin_dashboard.php');
    }
    redirect('dashboard.php');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    $employee = getEmployeeByEmail($email);

    if ($employee && isset($employee['password']) && $employee['password'] === $password) {
        $_SESSION['employee_id'] = (int) $employee['id'];
        if (isAdmin()) {
            redirect('admin_dashboard.php');
        }
        redirect('dashboard.php');
    }

    $errorMessage = 'Invalid email or password. Please try again.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-hero">
            <div class="brand-block">
                <div class="brand-mark">HR</div>
                <div>
                    <p class="eyebrow">People-first platform</p>
                    <h1>SmartStaff</h1>
                </div>
            </div>

            <h2>Work smarter with a better employee experience.</h2>
            <p>Track attendance, manage leave requests, and keep team information in one place.</p>

            <div class="credential-panel">
                <h3>Demo Employee Access</h3>
                <ul>
                    <li><strong>Alicia:</strong> alicia@hrs.com / 123456</li>
                    <li><strong>Marcus:</strong> marcus@hrs.com / demo123</li>
                    <li><strong>Priya:</strong> priya@hrs.com / hrpass</li>
                </ul>
            </div>
        </div>

        <div class="login-card">
            <p class="eyebrow accent">Welcome back</p>
            <h2>Employee Login</h2>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <label for="email">Work email</label>
                <input id="email" name="email" type="email" placeholder="you@company.com" required>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="Enter your password" required>

                <button type="submit" class="primary-btn">Sign in</button>
            </form>
        </div>
    </div>
</body>
</html>
