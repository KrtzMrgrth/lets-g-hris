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

    if (applyAttendanceOverride($overrideEmployeeId, $overrideDate, $overrideAction, $overrideClockIn)) {
        $overrideMessages = [
            'clock_in' => 'The missed clock-in was corrected.',
            'clock_out' => 'The missed clock-out was corrected.',
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
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">SS</span>
                <span>SmartStaff</span>
            </div>

            <nav class="nav-menu">
                <a href="admin_dashboard.php" class="nav-link">Admin Dashboard</a>
                <a href="admin_employee_dashboard.php" class="nav-link active">Employee Dashboard</a>
                <a href="admin_payroll_dashboard.php" class="nav-link">Payslip Dashboard</a>
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

            <section class="panel headcount-panel">
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
                    <div class="attendance-override-actions">
                        <button type="submit" name="attendance_override_action" value="clock_in" class="mini-btn approve">Clock In</button>
                        <button type="submit" name="attendance_override_action" value="clock_out" class="mini-btn reject">Clock Out</button>
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
                            <a href="employee_details.php?id=<?php echo (int) $person['id']; ?>" class="employee-row-link">
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
        });
    </script>
</body>
</html>
