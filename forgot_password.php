<?php
require_once __DIR__ . '/includes/config.php';

$errorMessage = '';
$successMessage = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim((string) ($_POST['email'] ?? ''));
    $newPassword = trim((string) ($_POST['new_password'] ?? ''));
    $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));
    $employee = getEmployeeByEmail($emailValue);

    if ($employee === null) {
        $errorMessage = 'We could not find an account with that work email.';
    } elseif (strlen($newPassword) < 6) {
        $errorMessage = 'Your new password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'The password confirmation does not match.';
    } else {
        $employees = getEmployees();
        foreach ($employees as &$employeeRecord) {
            if ((int) ($employeeRecord['id'] ?? 0) === (int) $employee['id']) {
                $employeeRecord['password'] = $newPassword;
                break;
            }
        }
        unset($employeeRecord);
        saveJson(EMPLOYEES_FILE, $employees);
        $successMessage = 'Your password was reset. You can now sign in with your new password.';
        $emailValue = '';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page password-reset-page">
    <div class="login-wrapper password-reset-wrapper">
        <div class="login-hero">
            <div class="brand-block">
                <div class="brand-mark">HR</div>
                <div>
                    <p class="eyebrow">Human resources information system</p>
                    <h1>SmartStaff</h1>
                </div>
            </div>
            <h2>Get back to your workforce workspace.</h2>
            <p>Reset your SmartStaff password using your registered work email.</p>
            <div class="credential-panel">
                <h3>Demo environment</h3>
                <p>Password reset is handled locally for this demo environment. In production, this step should be replaced with a verified email reset link.</p>
            </div>
        </div>

        <div class="login-card">
            <p class="eyebrow accent">Account recovery</p>
            <h2>Reset password</h2>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php">
                <label for="email">Work email</label>
                <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($emailValue); ?>" placeholder="you@company.com" required>

                <label for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" minlength="6" placeholder="At least 6 characters" required>

                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" minlength="6" placeholder="Enter it again" required>

                <button type="submit" class="primary-btn">Reset password</button>
            </form>

            <a href="index.php" class="login-back-link">Back to login</a>
        </div>
    </div>
</body>
</html>
