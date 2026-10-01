<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$employee = currentEmployee();
$leaveEntitlements = $employee['leave_entitlements'] ?? getLeaveEntitlements((string) ($employee['employment_type'] ?? ''));
$leaveUsage = getEmployeeLeaveUsage((int) ($employee['id'] ?? 0));
$leaveCredits = [];
foreach (['vacation_leave', 'sick_leave', 'birthday_leave', 'emergency_leave'] as $leaveKey) {
    $leaveCredits[$leaveKey] = max(0, (int) ($leaveEntitlements[$leaveKey] ?? 0) - (int) ($leaveUsage[$leaveKey] ?? 0));
}
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($leaveEntitlements['paid'])) {
        $_SESSION['flash_message'] = 'Leave benefits are not available for probationary, part-time, or contract employees.';
        redirect('leave_application.php');
    }

    $requestedLeaveType = trim((string) ($_POST['leave_type'] ?? 'Vacation Leave'));
    $requestedLeaveKey = normalizeLeaveType($requestedLeaveType);
    $requestedStart = trim((string) ($_POST['start_date'] ?? ''));
    $requestedEnd = trim((string) ($_POST['end_date'] ?? ''));
    $requestedDays = countLeaveDays([
        'start_date' => $requestedStart,
        'end_date' => $requestedEnd,
    ]);

    if ($requestedLeaveKey === '' || $requestedDays < 1 || $requestedDays > ($leaveCredits[$requestedLeaveKey] ?? 0)) {
        $_SESSION['flash_message'] = 'This request exceeds your remaining ' . $requestedLeaveType . ' credits.';
        redirect('leave_application.php');
    }

    $payload = [
        'id' => generateRecordId(),
        'employee_id' => (int) $employee['id'],
        'employee_name' => $employee['name'],
        'department' => $employee['department'],
        'leave_type' => $requestedLeaveType,
        'start_date' => $requestedStart,
        'end_date' => $requestedEnd,
        'reason' => trim((string) ($_POST['reason'] ?? '')),
        'status' => 'Pending',
        'submitted_at' => date('Y-m-d H:i:s')
    ];

    if ($payload['leave_type'] === 'Birthday Leave') {
        $birthday = trim((string) ($employee['birthday'] ?? ''));

        if ($birthday === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            $_SESSION['flash_message'] = 'Birthday Leave cannot be requested because no valid birthday is saved for this employee.';
            redirect('leave_application.php');
        }

        if ($payload['start_date'] !== $payload['end_date']) {
            $_SESSION['flash_message'] = 'Birthday Leave must be requested as a single-day leave on your actual birthday.';
            redirect('leave_application.php');
        }

        $requestedBirthday = date('m-d', strtotime($payload['start_date']));
        $actualBirthday = date('m-d', strtotime($birthday));

        if ($requestedBirthday !== $actualBirthday) {
            $_SESSION['flash_message'] = 'Birthday Leave is only allowed on your actual birthday: ' . date('F j, Y', strtotime($birthday)) . '.';
            redirect('leave_application.php');
        }
    }

    if ($payload['leave_type'] === 'Sick Leave') {
        if ($payload['start_date'] === '' || $payload['end_date'] === '') {
            $_SESSION['flash_message'] = 'Please select both the start and end date for sick leave.';
            redirect('leave_application.php');
        }

        $startDate = new DateTimeImmutable($payload['start_date']);
        $endDate = new DateTimeImmutable($payload['end_date']);
        $hoursDiff = abs($endDate->getTimestamp() - $startDate->getTimestamp()) / 3600;

        if ($startDate->format('Y-m-d') !== $endDate->format('Y-m-d') || $hoursDiff > 24) {
            $_SESSION['flash_message'] = 'Sick Leave can only be filed for the same day and must not exceed 24 hours.';
            redirect('leave_application.php');
        }
    }

    addLeaveApplication($payload);
    $_SESSION['flash_message'] = 'Your leave request has been filed successfully. You will be redirected back to the dashboard.';
    redirect('dashboard.php');
}

$leaveRequests = getLeaveApplications();
$recentEmployeeLeave = array_values(array_filter(
    $leaveRequests,
    static fn ($request) => (int) ($request['employee_id'] ?? 0) === (int) ($employee['id'] ?? 0)
));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Application | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">SS</span>
                <span>SmartStaff</span>
            </div>
            <nav class="nav-menu">
                <?php if (isManager()): ?>
                    <a href="manager_dashboard.php" class="nav-link">Manager Dashboard</a>
                <?php endif; ?>
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="employee_details.php" class="nav-link">Employee Details</a>
                <a href="leave_application.php" class="nav-link active">Leave Application</a>
                <a href="attendance_request.php" class="nav-link">My Attendance Request</a>
            </nav>
            <div class="sidebar-footer">
                <p><?php echo htmlspecialchars($employee['name']); ?></p>
                <a href="logout.php" class="logout-link">Log out</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow accent">Request management</p>
                    <h1>Leave Request</h1>
                </div>
            </header>

            <section class="content-grid two-columns">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <h3>Application form</h3>
                    </div>

                    <div class="leave-credits-panel">
                        <div class="panel-header compact-panel-header">
                            <h4>My leave credits</h4>
                        </div>
                        <div class="leave-credit-grid">
                            <div class="leave-credit-box"><span>Vacation Leave</span><strong><?php echo $leaveCredits['vacation_leave']; ?> days left</strong></div>
                            <div class="leave-credit-box"><span>Sick Leave</span><strong><?php echo $leaveCredits['sick_leave']; ?> days left</strong></div>
                            <div class="leave-credit-box"><span>Birthday Leave</span><strong><?php echo $leaveCredits['birthday_leave']; ?> day left</strong></div>
                            <div class="leave-credit-box"><span>Emergency Leave</span><strong><?php echo $leaveCredits['emergency_leave']; ?> days left</strong></div>
                        </div>
                        <p class="leave-credit-note">Approved leave requests are deducted from these credits.</p>
                    </div>

                    <?php if ($flashMessage !== ''): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($flashMessage); ?></div>
                    <?php endif; ?>

                    <?php if ($successMessage !== ''): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="leave_application.php" data-form>
                        <div class="field-row">
                            <div>
                                <label for="employee_name">Employee name</label>
                                <input id="employee_name" type="text" value="<?php echo htmlspecialchars($employee['name']); ?>" disabled>
                            </div>
                            <div>
                                <label for="leave_type">Leave type</label>
                                <select id="leave_type" name="leave_type">
                                    <option>Vacation Leave</option>
                                    <option>Birthday Leave</option>
                                    <option>Sick Leave</option>
                                    <option>Emergency Leave</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-row">
                            <div>
                                <label for="start_date">Start date</label>
                                <input id="start_date" name="start_date" type="date" required>
                            </div>
                            <div>
                                <label for="end_date">End date</label>
                                <input id="end_date" name="end_date" type="date" required>
                            </div>
                        </div>

                        <label for="reason">Reason</label>
                        <textarea id="reason" name="reason" rows="5" placeholder="Briefly explain your leave request" required></textarea>

                        <button type="submit" class="primary-btn">Submit leave request</button>
                    </form>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Recent applications</h3>
                        <span class="badge"><?php echo count($recentEmployeeLeave); ?></span>
                    </div>

                    <div class="list-table">
                        <?php if (empty($recentEmployeeLeave)): ?>
                            <p class="empty-state">No leave requests saved yet.</p>
                        <?php else: ?>
                            <?php foreach (array_slice(array_reverse($recentEmployeeLeave), 0, 5) as $request): ?>
                                <div class="list-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($request['leave_type']); ?> • <?php echo htmlspecialchars($request['start_date']); ?></small>
                                    </div>
                                    <span class="status-pill status-<?php echo strtolower(htmlspecialchars($request['status'])); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>