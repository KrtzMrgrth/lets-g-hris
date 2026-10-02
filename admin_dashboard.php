<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isAdmin()) {
    redirect('dashboard.php');
}

$employee = currentEmployee();
$employees = getEmployees();
$leaveRequests = getLeaveApplications();
$sickLeaveRequests = getSickLeaveApplications();
$attendanceRequests = getAttendanceRequests();
$attendanceLogs = loadJson(ATTENDANCE_FILE, []);
$today = date('Y-m-d');
$todayAttendanceByEmployee = [];

foreach ($attendanceLogs as $attendanceLog) {
    if ((string) ($attendanceLog['date'] ?? '') !== $today) {
        continue;
    }

    $logEmployeeId = (int) ($attendanceLog['employee_id'] ?? 0);
    if ($logEmployeeId === 0 || empty($attendanceLog['clock_in'])) {
        continue;
    }

    $todayAttendanceByEmployee[$logEmployeeId] = $attendanceLog;
}

$approvedLeaveToday = [];
foreach (array_merge($leaveRequests, $sickLeaveRequests) as $leaveRequest) {
    if (strtolower((string) ($leaveRequest['status'] ?? '')) !== 'approved') {
        continue;
    }

    $startDate = (string) ($leaveRequest['start_date'] ?? '');
    $endDate = (string) ($leaveRequest['end_date'] ?? $startDate);
    if ($startDate !== '' && $endDate !== '' && $today >= $startDate && $today <= $endDate) {
        $approvedLeaveToday[(int) ($leaveRequest['employee_id'] ?? 0)] = true;
    }
}

$todayAttendanceSummary = [
    'present' => [],
    'late' => [],
    'absent' => [],
];

foreach ($employees as $person) {
    if (strtolower((string) ($person['status'] ?? 'Active')) !== 'active') {
        continue;
    }

    $personId = (int) ($person['id'] ?? 0);
    $todayLog = $todayAttendanceByEmployee[$personId] ?? null;
    $employeeSummary = [
        'name' => (string) ($person['name'] ?? 'Employee'),
        'department' => (string) ($person['department'] ?? 'General'),
        'time_in' => (string) ($todayLog['clock_in'] ?? ''),
    ];

    if ($todayLog === null) {
        if (!isset($approvedLeaveToday[$personId])) {
            $todayAttendanceSummary['absent'][] = $employeeSummary;
        }
        continue;
    }

    $clockIn = DateTimeImmutable::createFromFormat('h:i A', (string) $todayLog['clock_in']);
    $isLate = stripos((string) ($todayLog['status'] ?? ''), 'late') !== false
        || ($clockIn !== false && $clockIn->format('H:i') > '09:00');

    $todayAttendanceSummary[$isLate ? 'late' : 'present'][] = $employeeSummary;
}
$attendanceRecords = [];

foreach ($employees as $index => $person) {
    $attendanceRecords[] = [
        'id' => (int) ($person['id'] ?? 0),
        'name' => $person['name'] ?? 'Employee',
        'role' => $person['role'] ?? 'Employee',
        'department' => $person['department'] ?? 'General',
        'status' => ($index % 2 === 0) ? 'In office' : 'Out of office',
        'time_in' => ['08:15 AM', '08:45 AM', '09:00 AM', '08:30 AM'][$index % 4],
        'time_out' => ['05:30 PM', '06:00 PM', '04:45 PM', '05:15 PM'][$index % 4],
    ];
}

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_employee') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $birthday = trim((string) ($_POST['birthday'] ?? ''));
        $salary = (float) ($_POST['salary'] ?? 0);
        $address = trim((string) ($_POST['address'] ?? ''));
        $civil_status = trim((string) ($_POST['civil_status'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $manager = trim((string) ($_POST['manager'] ?? ''));
        $accountRole = trim((string) ($_POST['account_role'] ?? 'employee'));
        $accountRole = in_array($accountRole, ['admin', 'manager', 'employee'], true) ? $accountRole : 'employee';
        $managerId = (int) ($_POST['manager_id'] ?? 0);
        $managerRecord = $managerId > 0 ? getEmployeeById($managerId) : null;
        $manager = $managerRecord !== null ? (string) $managerRecord['name'] : 'Pending Assignment';
        $location = trim((string) ($_POST['location'] ?? ''));
        $employment_type = trim((string) ($_POST['employment_type'] ?? 'Full-time'));
        $employment_type = $employment_type !== '' ? $employment_type : 'Full-time';
        $sss_number = trim((string) ($_POST['sss_number'] ?? ''));
        $philhealth_number = trim((string) ($_POST['philhealth_number'] ?? ''));
        $pagibig_number = trim((string) ($_POST['pagibig_number'] ?? ''));
        $tin_number = trim((string) ($_POST['tin_number'] ?? ''));
        $tax_status = trim((string) ($_POST['tax_status'] ?? 'Single'));
        $emergency_contact = trim((string) ($_POST['emergency_contact'] ?? ''));
        $join_date = trim((string) ($_POST['join_date'] ?? ''));
        $leaveEntitlements = getLeaveEntitlements($employment_type);
        $schedule_type = trim((string) ($_POST['schedule_type'] ?? 'Regular Day Shift'));
        $shift_time = trim((string) ($_POST['shift_time'] ?? '09:00 AM - 06:00 PM'));
        $work_days = trim((string) ($_POST['work_days'] ?? 'Mon - Fri'));
        $rest_day = trim((string) ($_POST['rest_day'] ?? 'Saturday'));
        $attendance_notes = trim((string) ($_POST['attendance_notes'] ?? 'Standard office schedule'));

        if ($name !== '' && $email !== '' && $password !== '' && $department !== '' && $role !== '') {
            if (getEmployeeByEmail($email) === null) {
                $newId = 2000 + count($employees);
                $employees[] = [
                    'id' => $newId,
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'department' => $department,
                    'role' => $role,
                    'manager' => $manager !== '' ? $manager : 'Pending Assignment',
                    'location' => $location !== '' ? $location : 'Office',
                    'employment_type' => $employment_type,
                    'leave_entitlements' => $leaveEntitlements,
                    'status' => 'Active',
                    'phone' => $phone !== '' ? $phone : '+1 (000) 000-0000',
                    'join_date' => $join_date !== '' ? $join_date : date('Y-m-d'),
                    'birthday' => $birthday !== '' ? $birthday : date('Y-m-d'),
                    'address' => $address,
                    'civil_status' => $civil_status,
                    'sss_number' => $sss_number,
                    'philhealth_number' => $philhealth_number,
                    'pagibig_number' => $pagibig_number,
                    'tin_number' => $tin_number,
                    'tax_status' => $tax_status,
                    'emergency_contact' => $emergency_contact,
                    'salary' => $salary,
                    'schedule_type' => $schedule_type,
                    'shift_time' => $shift_time,
                    'work_days' => $work_days,
                    'rest_day' => $rest_day,
                    'attendance_notes' => $attendance_notes,
                    'avatar' => strtoupper(substr($name, 0, 2)),
                    'is_admin' => $accountRole === 'admin',
                    'account_role' => $accountRole,
                    'manager_id' => $managerId,
                ];

                saveJson(EMPLOYEES_FILE, $employees);
                $_SESSION['flash_message'] = 'New employee account added successfully. You can now view all account details from the employee list below.';
            } else {
                $_SESSION['flash_message'] = 'This email is already in use.';
            }
        } else {
            $_SESSION['flash_message'] = 'Please complete all required fields.';
        }

        $returnTo = (string) ($_POST['return_to'] ?? 'admin_dashboard.php');
        $returnTo = in_array($returnTo, ['admin_dashboard.php', 'admin_employee_dashboard.php'], true)
            ? $returnTo
            : 'admin_dashboard.php';
        redirect($returnTo);
    }

    if ($action === 'approve_leave') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateLeaveStatus($requestId, 'Approved');
            $_SESSION['flash_message'] = 'Leave request approved.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'reject_leave') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateLeaveStatus($requestId, 'Rejected');
            $_SESSION['flash_message'] = 'Leave request rejected.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'approve_sick_leave') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateSickLeaveStatus($requestId, 'Approved');
            $_SESSION['flash_message'] = 'Sick leave request approved.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'reject_sick_leave') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateSickLeaveStatus($requestId, 'Rejected');
            $_SESSION['flash_message'] = 'Sick leave request rejected.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'approve_attendance_request') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateAttendanceRequestStatus($requestId, 'Approved');
            $_SESSION['flash_message'] = 'Attendance request approved.';
        }
        redirect('admin_dashboard.php');
    }

    if ($action === 'reject_attendance_request') {
        $requestId = (string) ($_POST['request_id'] ?? '');
        if ($requestId !== '') {
            updateAttendanceRequestStatus($requestId, 'Rejected');
            $_SESSION['flash_message'] = 'Attendance request rejected.';
        }
        redirect('admin_dashboard.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="admin-layout">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">SS</span>
                <span>SmartStaff</span>
            </div>

            <nav class="nav-menu" aria-label="Admin workspace">
                <span class="nav-section-label">Admin workspace</span>
                <a href="admin_dashboard.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">OV</span>Overview</a>
                <a href="admin_employee_dashboard.php" class="nav-link"><span class="nav-link-icon">EM</span>Employees</a>
                <a href="admin_payroll_dashboard.php" class="nav-link"><span class="nav-link-icon">PY</span>Payslips</a>
            </nav>

            <div class="sidebar-footer">
                <p><?php echo htmlspecialchars($employee['name']); ?></p>
                <a href="logout.php" class="logout-link">Log out</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow accent">HR operations</p>
                    <h1>Workforce overview</h1>
                </div>
                <div class="topbar-user">
                    <div class="avatar-circle"><?php echo htmlspecialchars($employee['avatar']); ?></div>
                    <span><?php echo htmlspecialchars($employee['role']); ?></span>
                </div>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card green">
                    <span class="stat-label">Active employees</span>
                    <strong><?php echo count(array_filter($employees, fn($person) => strtolower((string) ($person['status'] ?? 'Active')) === 'active')); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Present today</span>
                    <strong><?php echo count($todayAttendanceSummary['present']); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Late today</span>
                    <strong><?php echo count($todayAttendanceSummary['late']); ?></strong>
                </div>
                <div class="stat-card dark">
                    <span class="stat-label">Pending approvals</span>
                    <strong><?php echo count(array_filter($leaveRequests, fn($request) => $request['status'] === 'Pending')) + count(array_filter($sickLeaveRequests, fn($request) => $request['status'] === 'Pending')) + count(array_filter($attendanceRequests, fn($request) => $request['status'] === 'Pending')); ?></strong>
                </div>
            </section>

            <section class="overview-shortcuts" aria-label="Admin shortcuts">
                <div>
                    <p class="eyebrow accent">Workday at a glance</p>
                    <strong><?php echo count($todayAttendanceSummary['absent']); ?> absent today</strong>
                    <span><?php echo count($approvedLeaveToday); ?> approved time-off record<?php echo count($approvedLeaveToday) === 1 ? '' : 's'; ?> in effect</span>
                </div>
                <div class="overview-shortcut-links">
                    <a href="admin_employee_dashboard.php" class="mini-btn approve">Manage employees</a>
                    <a href="admin_payroll_dashboard.php" class="mini-btn approve">Open payslips</a>
                    <a href="#approval-queue" class="mini-btn time-off">Review approvals</a>
                </div>
            </section>

            <section class="panel attendance-summary-panel" id="attendance-overview">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow accent">Today · <?php echo htmlspecialchars(date('F j, Y')); ?></p>
                        <h3>Attendance &amp; Absenteeism</h3>
                    </div>
                    <span class="badge">Live snapshot</span>
                </div>

                <div class="attendance-summary-grid">
                    <?php foreach ([
                        'present' => ['label' => 'Present', 'class' => 'status-approved'],
                        'late' => ['label' => 'Late', 'class' => 'status-pending'],
                        'absent' => ['label' => 'Absent', 'class' => 'status-rejected'],
                    ] as $statusKey => $statusMeta): ?>
                        <div class="attendance-summary-group">
                            <div class="attendance-summary-heading">
                                <strong><?php echo $statusMeta['label']; ?></strong>
                                <span class="status-pill <?php echo $statusMeta['class']; ?>"><?php echo count($todayAttendanceSummary[$statusKey]); ?></span>
                            </div>
                            <?php if (empty($todayAttendanceSummary[$statusKey])): ?>
                                <p class="empty-state">No employees</p>
                            <?php else: ?>
                                <?php foreach ($todayAttendanceSummary[$statusKey] as $todayEmployee): ?>
                                    <div class="attendance-summary-row">
                                        <div>
                                            <strong><?php echo htmlspecialchars($todayEmployee['name']); ?></strong>
                                            <small><?php echo htmlspecialchars($todayEmployee['department']); ?></small>
                                        </div>
                                        <?php if ($todayEmployee['time_in'] !== ''): ?>
                                            <small><?php echo htmlspecialchars($todayEmployee['time_in']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="dashboard-main-row" id="approval-queue">
                <details class="panel form-panel add-employee-panel overview-add-employee-legacy">
                    <summary class="panel-header add-employee-summary">
                        <span class="add-employee-button">+ Add employee</span>
                    </summary>

                    <form method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="action" value="add_employee">

                        <div class="add-employee-layout">
                            <div class="form-column">
                                <div class="form-section">
                                    <h4 class="form-section-title">Basic Information</h4>
                                    <div class="field-row">
                                        <div>
                                            <label for="name">Full name</label>
                                            <input id="name" name="name" type="text" required>
                                        </div>
                                        <div>
                                            <label for="email">Email</label>
                                            <input id="email" name="email" type="email" required>
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="password">Password</label>
                                            <input id="password" name="password" type="text" required>
                                        </div>
                                        <div>
                                            <label for="phone">Phone number</label>
                                            <input id="phone" name="phone" type="text" placeholder="e.g. +63 912 345 6789">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="birthday">Birthday</label>
                                            <input id="birthday" name="birthday" type="date">
                                        </div>
                                        <div>
                                            <label for="civil_status">Civil status</label>
                                            <select id="civil_status" name="civil_status">
                                                <option value="Single">Single</option>
                                                <option value="Married">Married</option>
                                                <option value="Widowed">Widowed</option>
                                                <option value="Separated">Separated</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="address">Address</label>
                                        <input id="address" name="address" type="text" placeholder="Employee home address">
                                    </div>
                                </div>

                                <div class="form-section government-information-section">
                                    <h4 class="form-section-title">Government Information</h4>
                                    <div class="field-row">
                                        <div>
                                            <label for="sss_number">SSS number</label>
                                            <input id="sss_number" name="sss_number" type="text">
                                        </div>
                                        <div>
                                            <label for="philhealth_number">PhilHealth number</label>
                                            <input id="philhealth_number" name="philhealth_number" type="text">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="pagibig_number">Pag-IBIG number</label>
                                            <input id="pagibig_number" name="pagibig_number" type="text">
                                        </div>
                                        <div>
                                            <label for="tin_number">TIN</label>
                                            <input id="tin_number" name="tin_number" type="text">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="tax_status">Tax status</label>
                                            <select id="tax_status" name="tax_status">
                                                <option value="Single">Single</option>
                                                <option value="Married">Married</option>
                                                <option value="Head of Family">Head of Family</option>
                                                <option value="Qualified Dependent">Qualified Dependent</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="emergency_contact">Emergency contact</label>
                                            <input id="emergency_contact" name="emergency_contact" type="text" placeholder="Name and contact number">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-column">
                                <div class="form-section">
                                    <h4 class="form-section-title">Work Information</h4>
                                    <div class="field-row">
                                        <div>
                                            <label for="department">Department</label>
                                            <input id="department" name="department" type="text" required>
                                        </div>
                                        <div>
                                            <label for="role">Role</label>
                                            <input id="role" name="role" type="text" placeholder="e.g. Developer" required>
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="location">Work location</label>
                                            <input id="location" name="location" type="text" placeholder="e.g. Manila, Philippines">
                                        </div>
                                        <div></div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="account_role">Account role</label>
                                            <select id="account_role" name="account_role">
                                                <option value="employee">Employee</option>
                                                <option value="manager">Manager</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="manager_id">Reports to</label>
                                            <select id="manager_id" name="manager_id">
                                                <option value="0">Pending Assignment</option>
                                                <?php foreach (getManagerOptions() as $managerOption): ?>
                                                    <option value="<?php echo (int) $managerOption['id']; ?>"><?php echo htmlspecialchars($managerOption['name']); ?> (<?php echo htmlspecialchars(ucfirst((string) $managerOption['account_role'])); ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="employment_type">Employment type</label>
                                            <select id="employment_type" name="employment_type">
                                                <option value="Full-time">Full-time</option>
                                                <option value="Part-time">Part-time</option>
                                                <option value="Contract">Contract</option>
                                                <option value="Probationary">Probationary</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="join_date">Join date</label>
                                            <input id="join_date" name="join_date" type="date">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="salary">Monthly salary</label>
                                            <input id="salary" name="salary" type="number" min="0" step="0.01" placeholder="e.g. 5000">
                                        </div>
                                        <div></div>
                                    </div>
                                </div>

                                <div class="form-section work-schedule-section">
                                    <h4 class="form-section-title">Work Schedule</h4>
                                    <div class="field-row">
                                        <div>
                                            <label for="schedule_type">Schedule type</label>
                                            <select id="schedule_type" name="schedule_type">
                                                <option value="Regular Day Shift">Regular Day Shift</option>
                                                <option value="Regular Night Shift">Regular Night Shift</option>
                                                <option value="Flexible Schedule">Flexible Schedule</option>
                                                <option value="Hybrid Schedule">Hybrid Schedule</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="shift_time">Shift time</label>
                                            <input id="shift_time" name="shift_time" type="text" value="09:00 AM - 06:00 PM">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div>
                                            <label for="work_days">Work days</label>
                                            <input id="work_days" name="work_days" type="text" value="Mon - Fri">
                                        </div>
                                        <div>
                                            <label for="rest_day">Rest day</label>
                                            <input id="rest_day" name="rest_day" type="text" value="Saturday">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="attendance_notes">Attendance notes</label>
                                        <textarea id="attendance_notes" name="attendance_notes" rows="3" placeholder="e.g. Standard office schedule">Standard office schedule</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="add-employee-actions">
                            <button type="submit" class="primary-btn">Add employee</button>
                            <button type="button" class="mini-btn add-employee-back-btn" id="closeAddEmployeeBtn">Back to admin dashboard</button>
                        </div>
                    </form>
                </details>

                <div class="approval-column">
                    <div class="panel panel-approval">
                        <div class="panel-header">
                            <h3>Leave approval</h3>
                            <span class="badge"><?php echo count(array_filter($leaveRequests, fn($request) => $request['status'] === 'Pending')); ?></span>
                        </div>

                        <div class="list-table">
                            <?php $pendingLeave = array_filter($leaveRequests, fn($request) => $request['status'] === 'Pending'); ?>
                            <?php if (empty($pendingLeave)): ?>
                                <p class="empty-state">No pending leave requests.</p>
                            <?php else: ?>
                                <?php foreach ($pendingLeave as $request): ?>
                                    <div class="list-row approval-row">
                                        <div>
                                            <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                            <small><?php echo htmlspecialchars($request['leave_type']); ?> • <?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?></small>
                                        </div>
                                        <div class="approval-actions">
                                            <form method="POST" action="admin_dashboard.php" style="display:inline;">
                                                <input type="hidden" name="action" value="approve_leave">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                                <button type="submit" class="mini-btn approve">Approve</button>
                                            </form>
                                            <form method="POST" action="admin_dashboard.php" style="display:inline;">
                                                <input type="hidden" name="action" value="reject_leave">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                                <button type="submit" class="mini-btn reject">Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel panel-approval">
                        <div class="panel-header">
                            <h3>Sick leave approval</h3>
                            <span class="badge"><?php echo count(array_filter($sickLeaveRequests, fn($request) => $request['status'] === 'Pending')); ?></span>
                        </div>

                        <div class="list-table">
                            <?php $pendingSick = array_filter($sickLeaveRequests, fn($request) => $request['status'] === 'Pending'); ?>
                            <?php if (empty($pendingSick)): ?>
                                <p class="empty-state">No pending sick leave requests.</p>
                            <?php else: ?>
                                <?php foreach ($pendingSick as $request): ?>
                                    <div class="list-row approval-row">
                                        <div>
                                            <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                            <small><?php echo htmlspecialchars($request['leave_type']); ?> • <?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?></small>
                                        </div>
                                        <div class="approval-actions">
                                            <form method="POST" action="admin_dashboard.php" style="display:inline;">
                                                <input type="hidden" name="action" value="approve_sick_leave">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                                <button type="submit" class="mini-btn approve">Approve</button>
                                            </form>
                                            <form method="POST" action="admin_dashboard.php" style="display:inline;">
                                                <input type="hidden" name="action" value="reject_sick_leave">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                                <button type="submit" class="mini-btn reject">Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel panel-approval">
                        <div class="panel-header">
                            <h3>My Attendance Request approval</h3>
                            <span class="badge"><?php echo count(array_filter($attendanceRequests, fn($request) => $request['status'] === 'Pending')); ?></span>
                        </div>

                        <div class="list-table">
                            <?php $pendingAttendance = array_filter($attendanceRequests, fn($request) => $request['status'] === 'Pending'); ?>
                            <?php if (empty($pendingAttendance)): ?>
                                <p class="empty-state">No pending attendance requests.</p>
                            <?php else: ?>
                                <?php foreach ($pendingAttendance as $request): ?>
                                    <div class="list-row approval-row">
                                        <div>
                                            <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                            <small><?php echo htmlspecialchars($request['request_type']); ?> • <?php echo htmlspecialchars($request['request_date']); ?> • <?php echo htmlspecialchars($request['reason']); ?></small>
                                        </div>
                                        <div class="approval-actions">
                                            <form method="POST" action="admin_dashboard.php" style="display:inline;">
                                                <input type="hidden" name="action" value="approve_attendance_request">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                                <button type="submit" class="mini-btn approve">Approve</button>
                                            </form>
                                            <form method="POST" action="admin_dashboard.php" style="display:inline;">
                                                <input type="hidden" name="action" value="reject_attendance_request">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                                <button type="submit" class="mini-btn reject">Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script>
        const closeAddEmployeeButton = document.getElementById('closeAddEmployeeBtn');
        const addEmployeePanel = document.querySelector('.add-employee-panel');

        if (closeAddEmployeeButton && addEmployeePanel) {
            closeAddEmployeeButton.addEventListener('click', () => {
                addEmployeePanel.open = false;
            });
        }
    </script>
</body>
</html>