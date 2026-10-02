<?php
require_once __DIR__ . '/includes/config.php';

function routeAfterLogin(): void
{
    if (isAdmin()) {
        redirect('admin_dashboard.php');
    }
    if (isManager()) {
        redirect('manager_dashboard.php');
    }
    redirect('dashboard.php');
}

if (isLoggedIn()) {
    routeAfterLogin();
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    $employee = getEmployeeByEmail($email);

    if ($employee && isset($employee['password']) && $employee['password'] === $password) {
        $_SESSION['employee_id'] = (int) $employee['id'];
        routeAfterLogin();
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
                    <p class="eyebrow">Human resources information system</p>
                    <h1>SmartStaff</h1>
                </div>
            </div>

            <h2>One source of truth for your workforce.</h2>
            <p>SmartStaff centralizes employee records, attendance, leave, approvals, and payroll operations in one secure workspace.</p>

            <div class="login-capabilities" aria-label="SmartStaff capabilities">
                <span>Workforce records</span>
                <span>Attendance operations</span>
                <span>Leave and approvals</span>
                <span>Payroll support</span>
            </div>

            <div class="credential-panel">
                <h3>Demo workspace access</h3>
                <ul>
                    <li><strong>Alicia (HR Generalist):</strong> alicia@hrs.com / 123456</li>
                    <li><strong>Sophia (Manager):</strong> sophia.chen@hrs.com / sophia123</li>
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
            <a href="forgot_password.php" class="forgot-password-link">Forgot password?</a>
        </div>
    </div>
</body>
</html>