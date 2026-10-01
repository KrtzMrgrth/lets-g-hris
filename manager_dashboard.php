<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

if (isAdmin()) {
    redirect('admin_dashboard.php');
}

if (!isManager()) {
    redirect('dashboard.php');
}

$manager = currentEmployee();
$managerId = (int) ($manager['id'] ?? 0);

$team = getTeamMembers($managerId);
$teamIds = array_map(static fn (array $e): int => (int) $e['id'], $team);

$leaveRequests = array_values(array_filter(getLeaveApplications(), static fn (array $r): bool => in_array((int) ($r['employee_id'] ?? 0), $teamIds, true)));
$sickLeaveRequests = array_values(array_filter(getSickLeaveApplications(), static fn (array $r): bool => in_array((int) ($r['employee_id'] ?? 0), $teamIds, true)));
$attendanceRequests = array_values(array_filter(getAttendanceRequests(), static fn (array $r): bool => in_array((int) ($r['employee_id'] ?? 0), $teamIds, true)));

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

/**
 * Confirms $requestId's employee_id is actually one of this manager's
 * direct reports before any approve/reject action is applied — otherwise a
 * manager could approve/reject a request for someone outside their team
 * just by knowing (or guessing) a request id.
 */
function requestBelongsToTeam(array $records, string $requestId, array $teamIds): bool
{
    foreach ($records as $record) {
        if ((string) ($record['id'] ?? '') === $requestId) {
            return in_array((int) ($record['employee_id'] ?? 0), $teamIds, true);
        }
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = (string) ($_POST['request_id'] ?? '');

    if ($action === 'approve_leave' && requestBelongsToTeam(getLeaveApplications(), $requestId, $teamIds)) {
        updateLeaveStatus($requestId, 'Approved');
        $_SESSION['flash_message'] = 'Leave request approved.';
    } elseif ($action === 'reject_leave' && requestBelongsToTeam(getLeaveApplications(), $requestId, $teamIds)) {
        updateLeaveStatus($requestId, 'Rejected');
        $_SESSION['flash_message'] = 'Leave request rejected.';
    } elseif ($action === 'approve_sick_leave' && requestBelongsToTeam(getSickLeaveApplications(), $requestId, $teamIds)) {
        updateSickLeaveStatus($requestId, 'Approved');
        $_SESSION['flash_message'] = 'Sick leave request approved.';
    } elseif ($action === 'reject_sick_leave' && requestBelongsToTeam(getSickLeaveApplications(), $requestId, $teamIds)) {
        updateSickLeaveStatus($requestId, 'Rejected');
        $_SESSION['flash_message'] = 'Sick leave request rejected.';
    } elseif ($action === 'approve_attendance_request' && requestBelongsToTeam(getAttendanceRequests(), $requestId, $teamIds)) {
        updateAttendanceRequestStatus($requestId, 'Approved');
        $_SESSION['flash_message'] = 'Attendance request approved.';
    } elseif ($action === 'reject_attendance_request' && requestBelongsToTeam(getAttendanceRequests(), $requestId, $teamIds)) {
        updateAttendanceRequestStatus($requestId, 'Rejected');
        $_SESSION['flash_message'] = 'Attendance request rejected.';
    } elseif ($action !== '') {
        $_SESSION['flash_message'] = 'Unable to apply that action.';
    }

    redirect('manager_dashboard.php');
}

$today = date('Y-m-d');
$teamAttendanceToday = [];
foreach ($team as $person) {
    $record = getTodayAttendanceByEmployee((int) $person['id']);
    $clockIn = (string) ($record['clock_in'] ?? '');

    if ($clockIn === 'Time Off') {
        $statusLabel = 'Approved Time-Off';
    } elseif ($clockIn === '') {
        $statusLabel = 'Not clocked in';
    } elseif (empty($record['clock_out'])) {
        $statusLabel = 'Clocked in';
    } else {
        $statusLabel = 'Completed';
    }

    $teamAttendanceToday[] = [
        'name' => $person['name'],
        'role' => $person['role'],
        'clock_in' => $clockIn !== '' ? $clockIn : '—',
        'clock_out' => !empty($record['clock_out']) ? $record['clock_out'] : '—',
        'status' => $statusLabel,
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard | SmartStaff</title>
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
                <a href="manager_dashboard.php" class="nav-link active">Manager Dashboard</a>
                <a href="dashboard.php" class="nav-link">My Dashboard</a>
                <a href="employee_details.php" class="nav-link">My Details</a>
                <a href="leave_application.php" class="nav-link">My Leave Application</a>
                <a href="attendance_request.php" class="nav-link">My Attendance Request</a>
            </nav>

            <div class="sidebar-footer">
                <p><?php echo htmlspecialchars($manager['name']); ?></p>
                <a href="logout.php" class="logout-link">Log out</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="eyebrow accent">Team management</p>
                    <h1>Manager dashboard</h1>
                </div>
                <div class="topbar-user">
                    <div class="avatar-circle"><?php echo htmlspecialchars($manager['avatar']); ?></div>
                    <span><?php echo htmlspecialchars($manager['role']); ?></span>
                </div>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card green">
                    <span class="stat-label">Team Members</span>
                    <strong><?php echo count($team); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Pending Leave</span>
                    <strong><?php echo count(array_filter($leaveRequests, fn($r) => $r['status'] === 'Pending')); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Pending Sick Leave</span>
                    <strong><?php echo count(array_filter($sickLeaveRequests, fn($r) => $r['status'] === 'Pending')); ?></strong>
                </div>
                <div class="stat-card dark">
                    <span class="stat-label">Pending Attendance Requests</span>
                    <strong><?php echo count(array_filter($attendanceRequests, fn($r) => $r['status'] === 'Pending')); ?></strong>
                </div>
            </section>

            <section class="panel attendance-summary-panel">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow accent">Today · <?php echo htmlspecialchars(date('F j, Y')); ?></p>
                        <h3>Team attendance</h3>
                    </div>
                    <span class="badge">Live snapshot</span>
                </div>

                <div class="list-table">
                    <?php if (empty($teamAttendanceToday)): ?>
                        <p class="empty-state">You don't have any direct reports assigned yet.</p>
                    <?php else: ?>
                        <?php foreach ($teamAttendanceToday as $row): ?>
                            <div class="list-row">
                                <div>
                                    <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                    <small><?php echo htmlspecialchars($row['role']); ?> • In: <?php echo htmlspecialchars($row['clock_in']); ?> • Out: <?php echo htmlspecialchars($row['clock_out']); ?></small>
                                </div>
                                <span class="status-pill"><?php echo htmlspecialchars($row['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="approval-column">
                <div class="panel panel-approval">
                    <div class="panel-header">
                        <h3>Leave approval</h3>
                        <span class="badge"><?php echo count(array_filter($leaveRequests, fn($r) => $r['status'] === 'Pending')); ?></span>
                    </div>

                    <div class="list-table">
                        <?php $pendingLeave = array_filter($leaveRequests, fn($r) => $r['status'] === 'Pending'); ?>
                        <?php if (empty($pendingLeave)): ?>
                            <p class="empty-state">No pending leave requests from your team.</p>
                        <?php else: ?>
                            <?php foreach ($pendingLeave as $request): ?>
                                <div class="list-row approval-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($request['leave_type']); ?> • <?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?></small>
                                    </div>
                                    <div class="approval-actions">
                                        <form method="POST" action="manager_dashboard.php" style="display:inline;">
                                            <input type="hidden" name="action" value="approve_leave">
                                            <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                            <button type="submit" class="mini-btn approve">Approve</button>
                                        </form>
                                        <form method="POST" action="manager_dashboard.php" style="display:inline;">
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
                        <span class="badge"><?php echo count(array_filter($sickLeaveRequests, fn($r) => $r['status'] === 'Pending')); ?></span>
                    </div>

                    <div class="list-table">
                        <?php $pendingSick = array_filter($sickLeaveRequests, fn($r) => $r['status'] === 'Pending'); ?>
                        <?php if (empty($pendingSick)): ?>
                            <p class="empty-state">No pending sick leave requests from your team.</p>
                        <?php else: ?>
                            <?php foreach ($pendingSick as $request): ?>
                                <div class="list-row approval-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($request['leave_type']); ?> • <?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?></small>
                                    </div>
                                    <div class="approval-actions">
                                        <form method="POST" action="manager_dashboard.php" style="display:inline;">
                                            <input type="hidden" name="action" value="approve_sick_leave">
                                            <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                            <button type="submit" class="mini-btn approve">Approve</button>
                                        </form>
                                        <form method="POST" action="manager_dashboard.php" style="display:inline;">
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
                        <h3>Attendance request approval</h3>
                        <span class="badge"><?php echo count(array_filter($attendanceRequests, fn($r) => $r['status'] === 'Pending')); ?></span>
                    </div>

                    <div class="list-table">
                        <?php $pendingAttendance = array_filter($attendanceRequests, fn($r) => $r['status'] === 'Pending'); ?>
                        <?php if (empty($pendingAttendance)): ?>
                            <p class="empty-state">No pending attendance requests from your team.</p>
                        <?php else: ?>
                            <?php foreach ($pendingAttendance as $request): ?>
                                <div class="list-row approval-row">
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
                                        <small><?php echo htmlspecialchars($request['request_type']); ?> • <?php echo htmlspecialchars($request['request_date']); ?> • <?php echo htmlspecialchars($request['reason']); ?></small>
                                    </div>
                                    <div class="approval-actions">
                                        <form method="POST" action="manager_dashboard.php" style="display:inline;">
                                            <input type="hidden" name="action" value="approve_attendance_request">
                                            <input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>">
                                            <button type="submit" class="mini-btn approve">Approve</button>
                                        </form>
                                        <form method="POST" action="manager_dashboard.php" style="display:inline;">
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
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3>Team roster</h3>
                    <span class="badge"><?php echo count($team); ?></span>
                </div>

                <div class="list-table">
                    <?php if (empty($team)): ?>
                        <p class="empty-state">No direct reports assigned yet.</p>
                    <?php else: ?>
                        <?php foreach ($team as $person): ?>
                            <div class="list-row">
                                <div>
                                    <strong><?php echo htmlspecialchars($person['name']); ?></strong>
                                    <small><?php echo htmlspecialchars($person['role']); ?> • <?php echo htmlspecialchars($person['department']); ?></small>
                                </div>
                                <a href="employee_details.php?id=<?php echo (int) $person['id']; ?>" class="mini-btn">View details</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>