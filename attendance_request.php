<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (isAdmin()) {
    redirect('admin_dashboard.php');
}

$employee = currentEmployee();
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestType = trim((string) ($_POST['request_type'] ?? ''));
    $requestDate = trim((string) ($_POST['request_date'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $timeFrom = trim((string) ($_POST['time_from'] ?? ''));
    $timeTo = trim((string) ($_POST['time_to'] ?? ''));

    if ($requestType === '' || $requestDate === '' || $reason === '') {
        $_SESSION['flash_message'] = 'Please complete all fields for your attendance request.';
        redirect('attendance_request.php');
    }

    if ($requestType === 'Official Business') {
        if ($location === '' || $timeFrom === '' || $timeTo === '') {
            $_SESSION['flash_message'] = 'Official Business requires a location and a time range from and to.';
            redirect('attendance_request.php');
        }
    }

    addAttendanceRequest([
        'id' => generateRecordId(),
        'employee_id' => (int) $employee['id'],
        'employee_name' => $employee['name'],
        'department' => $employee['department'],
        'request_type' => $requestType,
        'request_date' => $requestDate,
        'location' => $location,
        'time_from' => $timeFrom,
        'time_to' => $timeTo,
        'reason' => $reason,
        'status' => 'Pending',
        'submitted_at' => date('Y-m-d H:i:s')
    ]);

    $_SESSION['flash_message'] = 'Your attendance request has been submitted successfully. It will be reviewed by HR.';
    redirect('attendance_request.php');
}

$attendanceRequests = array_values(array_filter(getAttendanceRequests(), fn($request) => (int) ($request['employee_id'] ?? 0) === (int) $employee['id']));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance Request | HRIS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">H</span>
                <span>HRIS</span>
            </div>

            <nav class="nav-menu">
                <?php if (isManager()): ?>
                    <a href="manager_dashboard.php" class="nav-link">Manager Dashboard</a>
                <?php endif; ?>
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="employee_details.php" class="nav-link">Employee Details</a>
                <a href="leave_application.php" class="nav-link">Leave Application</a>
                <a href="attendance_request.php" class="nav-link active">My Attendance Request</a>
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
                    <h1>My Attendance Request</h1>
                </div>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($flashMessage); ?></div>
            <?php endif; ?>

            <section class="content-grid two-columns">
                <div class="panel form-panel">
                    <div class="panel-header">
                        <h3>Request form</h3>
                    </div>

                    <form method="POST" action="attendance_request.php">
                        <div class="field-row">
                            <div>
                                <label for="request_type">Request type</label>
                                <select id="request_type" name="request_type" required>
                                    <option value="">Select request</option>
                                    <option>Certificate of Attendance</option>
                                    <option>Schedule Adjustment</option>
                                    <option>Official Business</option>
                                    <option>Overtime</option>
                                    <option>Undertime</option>
                                </select>
                            </div>
                            <div>
                                <label for="request_date">Request date</label>
                                <input id="request_date" name="request_date" type="date" required>
                            </div>
                        </div>

                        <div id="official_business_fields" style="display:none;">
                            <div class="field-row">
                                <div>
                                    <label for="time_from">Time from</label>
                                    <input id="time_from" name="time_from" type="time">
                                </div>
                                <div>
                                    <label for="time_to">Time to</label>
                                    <input id="time_to" name="time_to" type="time">
                                </div>
                            </div>

                            <div class="field-row">
                                <div>
                                    <label for="location">Location</label>
                                    <input id="location" name="location" type="text" placeholder="e.g. Makati City Office">
                                </div>
                                <div></div>
                            </div>
                        </div>

                        <label for="reason">Reason / details</label>
                        <textarea id="reason" name="reason" rows="5" placeholder="Explain why you are submitting this attendance request" required></textarea>

                        <button type="submit" class="primary-btn">Submit request</button>
                    </form>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Recent attendance requests</h3>
                        <span class="badge"><?php echo count($attendanceRequests); ?></span>
                    </div>

                    <div class="list-table">
                        <?php if (empty($attendanceRequests)): ?>
                            <p class="empty-state">No attendance requests submitted yet.</p>
                        <?php else: ?>
                            <?php foreach (array_slice(array_reverse($attendanceRequests), 0, 5) as $request): ?>
                                <div class="list-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['request_type']); ?></strong>
                                        <small><?php echo htmlspecialchars($request['request_date']); ?> • <?php echo htmlspecialchars($request['reason']); ?></small>
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
    <script>
        const requestTypeField = document.getElementById('request_type');
        const officialBusinessFields = document.getElementById('official_business_fields');

        function toggleOfficialBusinessFields() {
            const isOfficialBusiness = requestTypeField.value === 'Official Business';
            officialBusinessFields.style.display = isOfficialBusiness ? 'block' : 'none';

            const fields = officialBusinessFields.querySelectorAll('input');
            fields.forEach((field) => {
                field.required = isOfficialBusiness;
            });
        }

        requestTypeField.addEventListener('change', toggleOfficialBusinessFields);
        toggleOfficialBusinessFields();
    </script>
</body>
</html>