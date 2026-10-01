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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['attendance_override_action'])) {
    $overrideAction = (string) $_POST['attendance_override_action'];
    $overrideEmployeeId = (int) ($_POST['employee_id'] ?? 0);
    $overrideDate = trim((string) ($_POST['override_date'] ?? date('Y-m-d')));
    $overrideClockIn = trim((string) ($_POST['override_clock_in'] ?? '09:00 AM'));
    $overrideClockOut = trim((string) ($_POST['override_clock_out'] ?? '06:00 PM'));

    if (applyAttendanceOverride($overrideEmployeeId, $overrideDate, $overrideAction, $overrideClockIn, $overrideClockOut)) {
        $overrideMessages = [
            'fix_missed_clock_in' => 'The missed clock-in was corrected.',
            'fix_missed_clock_out' => 'The missed clock-out was corrected.',
            'approve_time_off' => 'Approved time off was recorded.',
        ];

        $_SESSION['flash_message'] = $overrideMessages[$overrideAction] ?? 'Attendance record updated.';
    } else {
        $_SESSION['flash_message'] = 'Unable to apply the attendance override.';
    }

    redirect('admin_employee_dashboard.php');
}

$attendanceLogs = loadJson(ATTENDANCE_FILE, []);
$attendanceRecords = [];
$attendanceByEmployee = [];

foreach ($employees as $person) {
    $employeeId = (int) ($person['id'] ?? 0);
    $employeeLogs = array_values(array_filter(
        $attendanceLogs,
        static fn (array $log): bool => (int) ($log['employee_id'] ?? 0) === $employeeId
    ));

    usort($employeeLogs, static function (array $first, array $second): int {
        $dateCompare = strcmp((string) ($second['date'] ?? ''), (string) ($first['date'] ?? ''));
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        return strcmp((string) ($second['clock_in'] ?? ''), (string) ($first['clock_in'] ?? ''));
    });

    $attendanceByEmployee[$employeeId] = [
        'id' => $employeeId,
        'name' => $person['name'] ?? 'Employee',
        'role' => $person['role'] ?? 'Employee',
        'department' => $person['department'] ?? 'General',
        'records' => $employeeLogs,
    ];
}

foreach ($employees as $person) {
    $employeeId = (int) ($person['id'] ?? 0);
    $latestLog = null;

    foreach ($attendanceLogs as $log) {
        if ((int) ($log['employee_id'] ?? 0) !== $employeeId) {
            continue;
        }

        if ($latestLog === null || strtotime((string) ($log['date'] ?? '1970-01-01')) > strtotime((string) ($latestLog['date'] ?? '1970-01-01'))) {
            $latestLog = $log;
        }
    }

    $attendanceRecords[] = [
        'id' => $employeeId,
        'name' => $person['name'] ?? 'Employee',
        'role' => $person['role'] ?? 'Employee',
        'department' => $person['department'] ?? 'General',
        'status' => ($latestLog !== null && empty($latestLog['clock_out'])) ? 'In office' : 'Out of office',
        'date' => !empty($latestLog['date']) ? date('l, F j, Y', strtotime((string) $latestLog['date'])) : date('l, F j, Y'),
        'time_in' => !empty($latestLog['clock_in']) ? $latestLog['clock_in'] : '--',
        'time_out' => !empty($latestLog['clock_out']) ? $latestLog['clock_out'] : '--',
    ];
}

$activeEmployees = array_values(array_filter(
    $employees,
    static fn (array $person): bool => strtolower((string) ($person['status'] ?? 'Active')) === 'active'
));

$headcountSegments = [
    'Department' => [],
    'Location' => [],
    'Employment type' => [],
];

foreach ($activeEmployees as $activeEmployee) {
    foreach ([
        'Department' => (string) ($activeEmployee['department'] ?? 'General'),
        'Location' => (string) ($activeEmployee['location'] ?? 'Office'),
        'Employment type' => (string) ($activeEmployee['employment_type'] ?? 'Full-time'),
    ] as $segment => $value) {
        $headcountSegments[$segment][$value] = ($headcountSegments[$segment][$value] ?? 0) + 1;
    }
}

foreach ($headcountSegments as &$segmentValues) {
    arsort($segmentValues);
}
unset($segmentValues);

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | SmartStaff</title>
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
                <a href="admin_dashboard.php" class="nav-link"><span class="nav-link-icon">OV</span>Overview</a>
                <a href="admin_employee_dashboard.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">EM</span>Employees</a>
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
                    <p class="eyebrow accent">Administration</p>
                    <h1>Employee dashboard</h1>
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
                    <span class="stat-label">Total employees</span>
                    <strong><?php echo count($employees); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">In office</span>
                    <strong><?php echo count(array_filter($attendanceRecords, fn($record) => $record['status'] === 'In office')); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Out of office</span>
                    <strong><?php echo count(array_filter($attendanceRecords, fn($record) => $record['status'] === 'Out of office')); ?></strong>
                </div>
                <div class="stat-card dark">
                    <span class="stat-label">Active</span>
                    <strong><?php echo count(array_filter($employees, fn($person) => ($person['status'] ?? 'Active') === 'Active')); ?></strong>
                </div>
            </section>

            <section class="panel add-employee-workspace-panel">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow accent">Employee management</p>
                        <h3>Add employee</h3>
                    </div>
                    <span class="badge">New account</span>
                </div>

                <form method="POST" action="admin_dashboard.php" class="add-employee-quick-form">
                    <input type="hidden" name="action" value="add_employee">
                    <input type="hidden" name="return_to" value="admin_employee_dashboard.php">

                    <div class="field-row">
                        <div>
                            <label for="employee_name">Full name</label>
                            <input id="employee_name" name="name" type="text" required>
                        </div>
                        <div>
                            <label for="employee_email">Email</label>
                            <input id="employee_email" name="email" type="email" required>
                        </div>
                    </div>

                    <div class="field-row">
                        <div>
                            <label for="employee_password">Password</label>
                            <input id="employee_password" name="password" type="text" required>
                        </div>
                        <div>
                            <label for="employee_phone">Phone number</label>
                            <input id="employee_phone" name="phone" type="text" placeholder="e.g. +63 912 345 6789">
                        </div>
                    </div>

                    <div class="field-row">
                        <div>
                            <label for="employee_department">Department</label>
                            <input id="employee_department" name="department" type="text" required>
                        </div>
                        <div>
                            <label for="employee_role">Job role</label>
                            <input id="employee_role" name="role" type="text" placeholder="e.g. Developer" required>
                        </div>
                    </div>

                    <div class="field-row">
                        <div>
                            <label for="employee_account_role">Account role</label>
                            <select id="employee_account_role" name="account_role">
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div>
                            <label for="employee_manager_id">Reports to</label>
                            <select id="employee_manager_id" name="manager_id">
                                <option value="0">Pending Assignment</option>
                                <?php foreach (getManagerOptions() as $managerOption): ?>
                                    <option value="<?php echo (int) $managerOption['id']; ?>"><?php echo htmlspecialchars($managerOption['name']); ?> (<?php echo htmlspecialchars(ucfirst((string) $managerOption['account_role'])); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="add-employee-quick-actions">
                        <button type="submit" class="primary-btn">Add employee</button>
                        <a href="#employee-list" class="mini-btn approve">View employee list</a>
                    </div>
                </form>
            </section>

            <section class="panel headcount-panel" id="employee-list">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow accent">Workforce overview</p>
                        <h3>Total Headcount</h3>
                    </div>
                    <strong class="headcount-total"><?php echo count($activeEmployees); ?> active</strong>
                </div>

                <div class="headcount-segments">
                    <?php foreach ($headcountSegments as $segment => $values): ?>
                        <div class="headcount-segment">
                            <h4><?php echo htmlspecialchars($segment); ?></h4>
                            <?php foreach ($values as $value => $count): ?>
                                <div class="headcount-row">
                                    <span><?php echo htmlspecialchars($value); ?></span>
                                    <strong><?php echo $count; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel attendance-overrides-panel">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow accent">Quick actions</p>
                        <h3>Time-Off Overrides</h3>
                        <p class="panel-helper">Correct attendance or approve time off for a selected date.</p>
                    </div>
                    <span class="badge">Attendance fixes</span>
                </div>
                <form method="POST" action="admin_employee_dashboard.php" class="attendance-overrides-form">
                    <div>
                        <label for="override_employee_id">Employee</label>
                        <select id="override_employee_id" name="employee_id" required>
                            <?php foreach ($employees as $person): ?>
                                <option value="<?php echo (int) ($person['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($person['name'] ?? 'Employee')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="override_date">Date</label>
                        <input id="override_date" name="override_date" type="date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
                    </div>
                    <div>
                        <label for="override_clock_in">Clock-in time</label>
                        <input id="override_clock_in" name="override_clock_in" type="text" value="09:00 AM">
                    </div>
                    <div>
                        <label for="override_clock_out">Clock-out time</label>
                        <input id="override_clock_out" name="override_clock_out" type="text" value="06:00 PM">
                    </div>
                    <div class="attendance-override-actions">
                        <button type="submit" name="attendance_override_action" value="fix_missed_clock_in" class="mini-btn approve">Fix clock-in</button>
                        <button type="submit" name="attendance_override_action" value="fix_missed_clock_out" class="mini-btn reject">Fix clock-out</button>
                        <button type="submit" name="attendance_override_action" value="approve_time_off" class="mini-btn time-off">Approve time off</button>
                    </div>
                </form>
            </section>

            <section class="content-grid">
                <details class="panel employee-list-panel">
                    <summary class="panel-header employee-list-summary">
                        <h3>Employee list</h3>
                        <span class="badge"><?php echo count($employees); ?></span>
                    </summary>

                    <div class="list-table">
                        <?php foreach ($employees as $person): ?>
                            <a href="employee_details.php?id=<?php echo (int) $person['id']; ?>&embedded=1" class="employee-row-link employee-details-link" data-employee-details-url="employee_details.php?id=<?php echo (int) $person['id']; ?>&embedded=1">
                                <div class="list-row employee-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($person['name']); ?></strong>
                                        <small><?php echo htmlspecialchars($person['role']); ?> • <?php echo htmlspecialchars($person['department']); ?></small>
                                    </div>
                                    <div class="employee-actions">
                                        <span class="status-pill status-approved"><?php echo htmlspecialchars($person['status'] ?? 'Active'); ?></span>
                                        <span class="mini-btn approve">View details</span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="panel attendance-monitoring-panel">
                    <summary class="panel-header attendance-monitoring-summary">
                        <h3>Attendance monitoring</h3>
                        <span class="badge"><?php echo count($attendanceRecords); ?></span>
                    </summary>

                    <div class="list-table attendance-list">
                        <?php foreach ($attendanceRecords as $record): ?>
                            <?php $employeeAttendance = $attendanceByEmployee[(int) $record['id']] ?? ['records' => []]; ?>
                            <details class="attendance-account-details">
                                <summary class="attendance-account-summary">
                                    <div>
                                        <strong><?php echo htmlspecialchars($record['name']); ?></strong>
                                        <small><?php echo htmlspecialchars($record['role']); ?> · <?php echo htmlspecialchars($record['department']); ?></small>
                                    </div>
                                    <div class="attendance-meta">
                                        <span class="status-pill <?php echo strtolower(str_replace(' ', '-', $record['status'])) === 'in-office' ? 'status-approved' : 'status-pending'; ?>"><?php echo htmlspecialchars($record['status']); ?></span>
                                        <small><?php echo count($employeeAttendance['records']); ?> attendance record<?php echo count($employeeAttendance['records']) === 1 ? '' : 's'; ?></small>
                                    </div>
                                </summary>

                                <div class="attendance-history">
                                    <?php if (empty($employeeAttendance['records'])): ?>
                                        <p class="empty-state">No attendance records yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($employeeAttendance['records'] as $attendance): ?>
                                            <div class="list-row attendance-history-row">
                                                <div>
                                                    <strong><?php echo htmlspecialchars((string) ($attendance['day'] ?? date('l', strtotime((string) ($attendance['date'] ?? 'now'))))); ?></strong>
                                                    <small><?php echo htmlspecialchars((string) ($attendance['date'] ?? '')); ?></small>
                                                </div>
                                                <div class="attendance-meta">
                                                    <span class="status-pill <?php echo strtolower((string) ($attendance['status'] ?? '')) === 'completed' ? 'status-approved' : 'status-pending'; ?>"><?php echo htmlspecialchars((string) ($attendance['status'] ?? 'Recorded')); ?></span>
                                                    <small>In: <?php echo htmlspecialchars((string) ($attendance['clock_in'] ?? 'Not recorded')); ?></small>
                                                    <small>Out: <?php echo htmlspecialchars((string) ($attendance['clock_out'] ?? 'Not recorded')); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </details>
            </section>

            <div class="employee-details-modal" id="employeeDetailsModal" hidden>
                <div class="employee-details-dialog" role="dialog" aria-modal="true" aria-labelledby="employeeDetailsTitle">
                    <div class="employee-details-dialog-header">
                        <h2 id="employeeDetailsTitle">Employee details</h2>
                        <button type="button" class="close-employee-details" id="closeEmployeeDetails" aria-label="Close employee details">&times;</button>
                    </div>
                    <iframe id="employeeDetailsFrame" title="Employee details" loading="lazy"></iframe>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('details').forEach((detail) => {
                detail.addEventListener('toggle', () => {
                    if (detail.open) {
                        detail.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            });

            const detailsModal = document.getElementById('employeeDetailsModal');
            const detailsFrame = document.getElementById('employeeDetailsFrame');
            const closeDetailsButton = document.getElementById('closeEmployeeDetails');

            const closeDetails = () => {
                detailsModal.hidden = true;
                detailsFrame.src = '';
                document.body.classList.remove('modal-open');
            };

            document.querySelectorAll('.employee-details-link').forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    detailsFrame.src = link.dataset.employeeDetailsUrl;
                    detailsModal.hidden = false;
                    document.body.classList.add('modal-open');
                });
            });

            closeDetailsButton.addEventListener('click', closeDetails);
            detailsModal.addEventListener('click', (event) => {
                if (event.target === detailsModal) {
                    closeDetails();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !detailsModal.hidden) {
                    closeDetails();
                }
            });
        });
    </script>
</body>
</html>
