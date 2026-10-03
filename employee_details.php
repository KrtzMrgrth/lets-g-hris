<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$currentEmployee = currentEmployee();
$selectedEmployeeId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $currentEmployee['id'];
$employee = getEmployeeById($selectedEmployeeId) ?? $currentEmployee;
$isAdminView = isAdmin() && isset($_GET['id']);
$isEmbedded = $isAdminView && (string) ($_GET['embedded'] ?? '') === '1';
$isOwnProfile = $selectedEmployeeId === (int) $currentEmployee['id'];
$isManagerTeamView = isManager() && !$isOwnProfile && in_array($selectedEmployeeId, array_map(
    static fn (array $e): int => (int) $e['id'],
    getTeamMembers((int) $currentEmployee['id'])
), true);
// Admins can maintain every section. Employees can maintain only their
// personal and government information; manager team views are read-only.
$editableSections = $isAdminView
    ? ['basic', 'government', 'work', 'schedule']
    : ($isOwnProfile ? ['basic', 'government'] : []);
$canEdit = !empty($editableSections);

if (!$isOwnProfile && !$isAdminView && !$isManagerTeamView) {
    redirect('employee_details.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['update_section']) && $canEdit) {
    $section = (string) $_POST['update_section'];
    $updates = [];

    if (!in_array($section, $editableSections, true)) {
        $_SESSION['flash_message'] = 'You do not have permission to edit this section.';
        redirect('employee_details.php?id=' . $selectedEmployeeId . ($isEmbedded ? '&embedded=1' : ''));
    }

    if ($section === 'basic') {
        $updates = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'birthday' => trim((string) ($_POST['birthday'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'civil_status' => trim((string) ($_POST['civil_status'] ?? '')),
        ];
    }

    if ($section === 'government') {
        $updates = [
            'sss_number' => trim((string) ($_POST['sss_number'] ?? '')),
            'philhealth_number' => trim((string) ($_POST['philhealth_number'] ?? '')),
            'pagibig_number' => trim((string) ($_POST['pagibig_number'] ?? '')),
            'tin_number' => trim((string) ($_POST['tin_number'] ?? '')),
            'tax_status' => trim((string) ($_POST['tax_status'] ?? '')),
            'emergency_contact' => trim((string) ($_POST['emergency_contact'] ?? '')),
        ];
    }

    if ($section === 'work') {
        $updates = [
            'department' => trim((string) ($_POST['department'] ?? '')),
            'role' => trim((string) ($_POST['role'] ?? '')),
            'manager' => trim((string) ($_POST['manager'] ?? '')),
            'employment_type' => trim((string) ($_POST['employment_type'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'join_date' => trim((string) ($_POST['join_date'] ?? '')),
            'salary' => trim((string) ($_POST['salary'] ?? '')),
        ];
    }

    if ($section === 'schedule') {
        $updates = [
            'schedule_type' => trim((string) ($_POST['schedule_type'] ?? '')),
            'shift_time' => trim((string) ($_POST['shift_time'] ?? '')),
            'work_days' => trim((string) ($_POST['work_days'] ?? '')),
            'rest_day' => trim((string) ($_POST['rest_day'] ?? '')),
            'attendance_notes' => trim((string) ($_POST['attendance_notes'] ?? '')),
        ];
    }

    if (!empty($updates)) {
        $employees = getEmployees();
        foreach ($employees as &$employeeRecord) {
            if ((int) ($employeeRecord['id'] ?? 0) === (int) $selectedEmployeeId) {
                foreach ($updates as $key => $value) {
                    if ($key === 'salary') {
                        $employeeRecord[$key] = (float) $value;
                    } else {
                        $employeeRecord[$key] = $value;
                    }
                }
                break;
            }
        }
        saveJson(EMPLOYEES_FILE, $employees);
        $employee = getEmployeeById($selectedEmployeeId) ?? $currentEmployee;
        $_SESSION['flash_message'] = 'Employee details updated successfully.';
        redirect('employee_details.php?id=' . $selectedEmployeeId . ($isEmbedded ? '&embedded=1' : ''));
    }
}

$detailSections = [
    'Basic Information' => [
        'Full Name' => $employee['name'] ?? 'Not provided',
        'Employee ID' => $employee['id'] ?? 'Not provided',
        'Work Email' => $employee['email'] ?? 'Not provided',
        'Phone Number' => $employee['phone'] ?? 'Not provided',
        'Birthday' => $employee['birthday'] ?? 'Not provided',
        'Address' => $employee['address'] ?? 'Not provided',
        'Civil Status' => $employee['civil_status'] ?? 'Not provided',
    ],
    'Government Information' => [
        'SSS Number' => $employee['sss_number'] ?? 'Not provided',
        'PhilHealth Number' => $employee['philhealth_number'] ?? 'Not provided',
        'Pag-IBIG Number' => $employee['pagibig_number'] ?? 'Not provided',
        'TIN' => $employee['tin_number'] ?? 'Not provided',
        'Tax Status' => $employee['tax_status'] ?? 'Single',
        'Emergency Contact' => $employee['emergency_contact'] ?? 'Not provided',
    ],
    'Work Information' => [
        'Department' => $employee['department'] ?? 'Not provided',
        'Role' => $employee['role'] ?? 'Not provided',
        'Manager' => $employee['manager'] ?? 'Not provided',
        'Employment Type' => $employee['employment_type'] ?? 'Not provided',
        'Location' => $employee['location'] ?? 'Not provided',
        'Join Date' => $employee['join_date'] ?? 'Not provided',
        'Monthly Salary' => '₱' . number_format((float) ($employee['salary'] ?? 0), 2),
    ],
    'Leave Benefits' => [
        'Vacation Leave' => (int) (($employee['leave_entitlements']['vacation_leave'] ?? 0)) . ' days',
        'Sick Leave' => (int) (($employee['leave_entitlements']['sick_leave'] ?? 0)) . ' days',
        'Birthday Leave' => (int) (($employee['leave_entitlements']['birthday_leave'] ?? 0)) . ' paid day',
        'Emergency Leave' => (int) (($employee['leave_entitlements']['emergency_leave'] ?? 0)) . ' days',
        'Leave Status' => !empty($employee['leave_entitlements']['paid']) ? 'Paid leave eligible' : 'No leave entitlement',
    ],
    'Work Schedule' => [
        'Schedule Type' => $employee['schedule_type'] ?? 'Regular Day Shift',
        'Shift Time' => $employee['shift_time'] ?? '09:00 AM - 06:00 PM',
        'Work Days' => $employee['work_days'] ?? 'Mon - Fri',
        'Rest Day' => $employee['rest_day'] ?? 'Saturday',
        'Status' => $employee['status'] ?? 'Active',
        'Attendance Notes' => $employee['attendance_notes'] ?? 'Standard office schedule',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?php echo $isEmbedded ? 'embedded-details-page' : ''; ?>" data-employee='<?php echo json_encode($employee, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">SS</span>
                <span>SmartStaff</span>
            </div>
            <nav class="nav-menu">
                <?php if (isAdmin()): ?>
                    <a href="admin_dashboard.php" class="nav-link">Admin Dashboard</a>
                    <a href="employee_details.php?id=<?php echo (int) $employee['id']; ?>" class="nav-link active">Employee Details</a>
                <?php elseif ($isManagerTeamView): ?>
                    <a href="manager_dashboard.php" class="nav-link">Manager Dashboard</a>
                    <a href="employee_details.php?id=<?php echo (int) $employee['id']; ?>" class="nav-link active">Employee Details</a>
                <?php else: ?>
                    <?php if (isManager()): ?>
                        <a href="manager_dashboard.php" class="nav-link">Manager Dashboard</a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                    <a href="employee_details.php" class="nav-link active">Employee Details</a>
                    <a href="leave_application.php" class="nav-link">Leave Application</a>
                    <a href="attendance_request.php" class="nav-link">My Attendance Request</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <p><?php echo htmlspecialchars($currentEmployee['name']); ?></p>
                <a href="logout.php" class="logout-link">Log out</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow accent">Profile</p>
                    <h1><?php echo $isAdminView ? 'Employee account details' : 'Employee details'; ?></h1>
                </div>
            </header>

            <section class="profile-card">
                <div class="profile-header">
                    <div class="avatar-circle large"><?php echo htmlspecialchars($employee['avatar']); ?></div>
                    <div>
                        <h2><?php echo htmlspecialchars($employee['name']); ?></h2>
                        <p><?php echo htmlspecialchars($employee['role']); ?> • <?php echo htmlspecialchars($employee['department']); ?></p>
                    </div>
                </div>

                <?php foreach ($detailSections as $sectionKey => $details): ?>
                    <?php $isFirstSection = $sectionKey === array_key_first($detailSections); ?>
                    <details class="info-panel" <?php echo $isFirstSection ? 'open' : ''; ?>>
                        <summary>
                            <span><?php echo htmlspecialchars($sectionKey); ?></span>
                            <span class="panel-toggle">+</span>
                        </summary>
                        <div class="details-grid details-grid-accordion">
                            <?php foreach ($details as $label => $value): ?>
                                <div class="detail-box">
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                    <strong><?php echo htmlspecialchars((string) $value); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="section-edit-wrap">
                            <?php if (in_array('basic', $editableSections, true) && $sectionKey === 'Basic Information'): ?>
                                <button type="button" class="mini-btn section-edit-btn" data-section="basic">Edit</button>
                            <?php elseif (in_array('government', $editableSections, true) && $sectionKey === 'Government Information'): ?>
                                <button type="button" class="mini-btn section-edit-btn" data-section="government">Edit</button>
                            <?php elseif (in_array('work', $editableSections, true) && $sectionKey === 'Work Information'): ?>
                                <button type="button" class="mini-btn section-edit-btn" data-section="work">Edit</button>
                            <?php elseif (in_array('schedule', $editableSections, true) && $sectionKey === 'Work Schedule'): ?>
                                <button type="button" class="mini-btn section-edit-btn" data-section="schedule">Edit</button>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>

                <?php if ($canEdit): ?>
                <div id="editSectionModal" class="edit-modal hidden">
                    <div class="edit-modal-content">
                        <div class="edit-modal-header">
                            <h3>Edit Information</h3>
                            <button type="button" class="close-edit-modal">×</button>
                        </div>

                        <form method="POST" action="employee_details.php?id=<?php echo (int) $selectedEmployeeId; ?>">
                            <input type="hidden" name="update_section" id="updateSectionField" value="basic">

                            <div id="editFieldsContainer" class="edit-fields-grid"></div>

                            <div class="edit-modal-actions">
                                <button type="button" class="mini-btn" id="cancelEditBtn">Cancel</button>
                                <button type="submit" class="primary-btn">Save changes</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>