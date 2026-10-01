<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (isAdmin()) {
    redirect('admin_dashboard.php');
}

$employee = currentEmployee();
$leaveRequests = getLeaveApplications();
$sickLeaveRequests = getSickLeaveApplications();
$employees = getEmployees();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['attendance_action'])) {
    $action = $_POST['attendance_action'];
    $employeeId = (int) ($employee['id'] ?? 0);
    $today = date('Y-m-d');

    if ($action === 'clock_in') {
        $existing = getTodayAttendanceByEmployee($employeeId);
        $existingClockIn = (string) ($existing['clock_in'] ?? '');

        if ($existingClockIn === 'Time Off') {
            $_SESSION['flash_message'] = 'Today is marked as approved time off, so you cannot clock in.';
        } elseif ($existing !== null && $existingClockIn !== '') {
            $_SESSION['flash_message'] = 'You have already clocked in today.';
        } else {
            addEmployeeAttendanceRecord([
                'id' => generateRecordId(),
                'employee_id' => $employeeId,
                'employee_name' => $employee['name'],
                'date' => $today,
                'day' => date('l'),
                'clock_in' => date('h:i A'),
                'clock_out' => '',
                'status' => 'On time',
            ]);
            $_SESSION['flash_message'] = 'Clock in recorded successfully.';
        }

        redirect('dashboard.php');
    }

    if ($action === 'clock_out') {
        $existing = getTodayAttendanceByEmployee($employeeId);
        $existingClockIn = (string) ($existing['clock_in'] ?? '');

        if ($existingClockIn === 'Time Off') {
            $_SESSION['flash_message'] = 'Today is marked as approved time off, so there is nothing to clock out of.';
        } elseif ($existing === null || $existingClockIn === '') {
            $_SESSION['flash_message'] = 'Please clock in before clocking out.';
        } elseif (!empty($existing['clock_out'])) {
            $_SESSION['flash_message'] = 'You have already clocked out today.';
        } else {
            $updated = updateEmployeeAttendanceOut($employeeId, $today, date('h:i A'));
            $_SESSION['flash_message'] = $updated ? 'Clock out recorded successfully.' : 'Unable to record clock out.';
        }

        redirect('dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['attendance_request_action'])) {
    $reqAction = $_POST['attendance_request_action'];
    $requestId = (string) ($_POST['request_id'] ?? '');

    if ($reqAction === 'cancel_request' && $requestId !== '') {
        updateAttendanceRequestStatus($requestId, 'Cancelled');
        $_SESSION['flash_message'] = 'Attendance request cancelled.';
    }

    redirect('dashboard.php');
}

$myAttendanceRecords = getEmployeeAttendanceRecords((int) ($employee['id'] ?? 0));

$myAttendanceRequests = array_values(array_filter(getAttendanceRequests(), fn($r) => (int) ($r['employee_id'] ?? 0) === (int) ($employee['id'] ?? 0)));

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">HR</span>
                <span>SmartStaff</span>
            </div>

            <nav class="nav-menu">
                <?php if (isManager()): ?>
                    <a href="manager_dashboard.php" class="nav-link">Manager Dashboard</a>
                <?php endif; ?>
                <a href="dashboard.php" class="nav-link active">Dashboard</a>
                <a href="employee_details.php" class="nav-link">Employee Details</a>
                <a href="leave_application.php" class="nav-link">Leave Application</a>
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
                    <p class="eyebrow accent">Welcome back</p>
                    <h1>Good morning, <?php echo htmlspecialchars(explode(' ', $employee['name'])[0]); ?>.</h1>
                </div>
                <div class="topbar-user">
                    <div class="avatar-circle"><?php echo htmlspecialchars($employee['avatar']); ?></div>
                    <span><?php echo htmlspecialchars($employee['role']); ?></span>
                </div>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div>
            <?php endif; ?>

            <?php
                $employeeLeave = array_values(array_filter($leaveRequests, fn($request) => (int) $request['employee_id'] === (int) $employee['id']));
                $employeeSickLeave = array_values(array_filter($sickLeaveRequests, fn($request) => (int) $request['employee_id'] === (int) $employee['id']));
                $employeePendingLeave = count(array_filter($employeeLeave, fn($request) => $request['status'] === 'Pending'));
                $employeeApprovedLeave = count(array_filter($employeeLeave, fn($request) => $request['status'] === 'Approved'));
                $employeePendingSickLeave = count(array_filter($employeeSickLeave, fn($request) => $request['status'] === 'Pending'));
                $employeeApprovedSickLeave = count(array_filter($employeeSickLeave, fn($request) => $request['status'] === 'Approved'));
                $monthlySalary = isset($employee['salary']) ? (float) $employee['salary'] : 0.0;
                $latestEmployeePayroll = getLatestPayrollForEmployee((int) ($employee['id'] ?? 0));
                $paidLeaveDays = (float) ($latestEmployeePayroll['paid_leave_days'] ?? getEmployeePaidLeaveDaysForMonth((int) ($employee['id'] ?? 0), date('Y-m')));
                $lateMinutes = (float) ($latestEmployeePayroll['late_minutes'] ?? 0.0);
                $lateDeduction = (float) ($latestEmployeePayroll['late_deduction'] ?? 0.0);
                $absentDays = (float) ($latestEmployeePayroll['absent_days'] ?? 0.0);
                $absenceDeduction = (float) ($latestEmployeePayroll['absence_deduction'] ?? 0.0);
                $grossMonthlyPay = $monthlySalary;
                $sssEmployeeShare = min($grossMonthlyPay * 0.045, 1125.00);
                $philHealthEmployeeShare = min($grossMonthlyPay * 0.04, 4500.00);
                $pagIbigEmployeeShare = min($grossMonthlyPay * 0.02, 200.00);
                $withholdingTax = max(0, $grossMonthlyPay * 0.05);
                $allowances = 350.00;
                $thirteenthMonthPay = $grossMonthlyPay / 12;
                $netMonthlyPay = $grossMonthlyPay - $sssEmployeeShare - $philHealthEmployeeShare - $pagIbigEmployeeShare - $withholdingTax - $lateDeduction - $absenceDeduction + $allowances;
            ?>

            <section class="stats-grid">
                <div class="stat-card green">
                    <span class="stat-label">Pending Leave</span>
                    <strong><?php echo $employeePendingLeave; ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Approved Leave</span>
                    <strong><?php echo $employeeApprovedLeave; ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Pending Sick Leave</span>
                    <strong><?php echo $employeePendingSickLeave; ?></strong>
                </div>
                <div class="stat-card dark">
                    <span class="stat-label">Approved Sick Leave</span>
                    <strong><?php echo $employeeApprovedSickLeave; ?></strong>
                </div>
            </section>

            <section class="attendance-clock-panel panel">
                <div class="panel-header">
                    <h3>My Attendance</h3>
                    <span class="badge">Today</span>
                </div>

                <div class="attendance-clock-grid">
                    <div class="attendance-time-box">
                        <span class="attendance-label">Day</span>
                        <strong><?php echo htmlspecialchars(date('l', time())); ?></strong>
                    </div>
                    <div class="attendance-time-box">
                        <span class="attendance-label">Date</span>
                        <strong><?php echo htmlspecialchars(date('F j, Y', time())); ?></strong>
                    </div>
                    <div class="attendance-time-box highlight">
                        <span class="attendance-label">Current time</span>
                        <strong><?php echo htmlspecialchars(date('h:i A', time())); ?></strong>
                    </div>
                </div>

                <div class="attendance-clock-actions">
                    <form method="POST" action="dashboard.php" style="display:inline;" data-form>
                        <input type="hidden" name="attendance_action" value="clock_in">
                        <button type="submit" class="primary-btn attendance-btn">Clock In</button>
                    </form>
                    <form method="POST" action="dashboard.php" style="display:inline;" data-form>
                        <input type="hidden" name="attendance_action" value="clock_out">
                        <button type="submit" class="mini-btn attendance-btn">Clock Out</button>
                    </form>
                </div>

                <div class="my-attendance-records">
                    <?php if (empty($myAttendanceRecords)): ?>
                        <div class="my-attendance-record">
                            <div>
                                <strong>No attendance recorded yet</strong>
                                <small>Use Clock In to start your workday.</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($myAttendanceRecords, 0, 4) as $record): ?>
                            <div class="my-attendance-record">
                                <div>
                                    <strong><?php echo !empty($record['clock_out']) ? 'Attendance' : 'Clock In'; ?></strong>
                                    <small>
                                        <?php echo htmlspecialchars((string) ($record['day'] ?? date('l', strtotime((string) $record['date'])))); ?>,
                                        <?php echo htmlspecialchars((string) ($record['date'] ?? '')); ?>
                                        • Clock In: <?php echo htmlspecialchars((string) ($record['clock_in'] ?? 'Not recorded')); ?>
                                        <?php if (!empty($record['clock_out'])): ?>
                                            • Clock Out: <?php echo htmlspecialchars((string) $record['clock_out']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="status-pill status-approved"><?php echo htmlspecialchars((string) ($record['status'] ?? 'Recorded')); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h3>HR & Attendance</h3>
                        <span class="badge"></span>
                    </div>
                    <details class="pay-details">
                        <summary class="pay-summary">
                            <div>
                                <strong>Payroll</strong>
                                <small>Click to view or hide payroll breakdown</small>
                            </div>
                            <span class="status-pill status-approved payroll-amount-hidden">₱<?php echo number_format($monthlySalary, 2); ?></span>
                        </summary>
                        <div class="list-table" style="margin-top: 12px;">
                            <div class="list-row">
                                <div>
                                    <strong>Gross monthly pay</strong>
                                    <small>Monthly salary</small>
                                </div>
                                <span class="status-pill status-approved">₱<?php echo number_format($grossMonthlyPay, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>SSS contribution</strong>
                                    <small>Employee share</small>
                                </div>
                                <span class="status-pill status-pending">-₱<?php echo number_format($sssEmployeeShare, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>PhilHealth contribution</strong>
                                    <small>Employee share</small>
                                </div>
                                <span class="status-pill status-pending">-₱<?php echo number_format($philHealthEmployeeShare, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>Pag-IBIG contribution</strong>
                                    <small>Employee share</small>
                                </div>
                                <span class="status-pill status-pending">-₱<?php echo number_format($pagIbigEmployeeShare, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>Withholding tax</strong>
                                    <small>Estimated tax</small>
                                </div>
                                <span class="status-pill status-pending">-₱<?php echo number_format($withholdingTax, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>Paid leave</strong>
                                    <small>Approved full-time leave days this month</small>
                                </div>
                                <span class="status-pill status-approved"><?php echo number_format($paidLeaveDays, 0); ?> days</span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>Late deduction</strong>
                                    <small><?php echo number_format($lateMinutes, 0); ?> late minutes from latest payslip</small>
                                </div>
                                <span class="status-pill status-pending">-₱<?php echo number_format($lateDeduction, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>Absent without leave</strong>
                                    <small><?php echo number_format($absentDays, 0); ?> absent day<?php echo $absentDays === 1.0 ? '' : 's'; ?> from latest payslip</small>
                                </div>
                                <span class="status-pill status-pending">-₱<?php echo number_format($absenceDeduction, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>Rice/meal allowance</strong>
                                    <small>Additional benefit</small>
                                </div>
                                <span class="status-pill status-approved">+₱<?php echo number_format($allowances, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>13th month pay</strong>
                                    <small>Annual benefit</small>
                                </div>
                                <span class="status-pill status-approved">₱<?php echo number_format($thirteenthMonthPay, 2); ?></span>
                            </div>
                            <div class="list-row">
                                <div>
                                    <strong>Estimated net pay</strong>
                                    <small>Monthly take-home</small>
                                </div>
                                <span class="status-pill status-approved">₱<?php echo number_format($netMonthlyPay, 2); ?></span>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>My leave status</h3>
                        <span class="badge"><?php echo count($employeeLeave); ?> total</span>
                    </div>
                    <div class="list-table">
                        <?php if (empty($employeeLeave)): ?>
                            <p class="empty-state">You have no leave records yet.</p>
                        <?php else: ?>
                            <?php foreach (array_slice(array_reverse($employeeLeave), 0, 4) as $request): ?>
                                <div class="list-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['leave_type']); ?></strong>
                                        <small><?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?></small>
                                    </div>
                                    <span class="status-pill status-<?php echo strtolower(htmlspecialchars($request['status'])); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>My sick leave status</h3>
                        <span class="badge"><?php echo count($employeeSickLeave); ?> total</span>
                    </div>
                    <div class="list-table">
                        <?php if (empty($employeeSickLeave)): ?>
                            <p class="empty-state">You have no sick leave records yet.</p>
                        <?php else: ?>
                            <?php foreach (array_slice(array_reverse($employeeSickLeave), 0, 4) as $request): ?>
                                <div class="list-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['leave_type']); ?></strong>
                                        <small><?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?></small>
                                    </div>
                                    <span class="status-pill status-<?php echo strtolower(htmlspecialchars($request['status'])); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>My attendance requests</h3>
                        <span class="badge"><?php echo count($myAttendanceRequests); ?> total</span>
                    </div>
                    <div class="list-table">
                        <?php if (empty($myAttendanceRequests)): ?>
                            <p class="empty-state">You have no attendance requests yet.</p>
                        <?php else: ?>
                            <?php foreach (array_slice(array_reverse($myAttendanceRequests), 0, 4) as $req): ?>
                                <div class="list-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($req['request_type']); ?></strong>
                                        <small><?php echo htmlspecialchars($req['request_date']); ?> • <?php echo htmlspecialchars($req['reason']); ?></small>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <span class="status-pill status-<?php echo strtolower(htmlspecialchars($req['status'])); ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                                        <?php if ($req['status'] === 'Pending'): ?>
                                            <form method="POST" action="dashboard.php" style="display:inline;">
                                                <input type="hidden" name="attendance_request_action" value="cancel_request">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($req['id'] ?? '')); ?>">
                                                <button type="submit" class="mini-btn reject">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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