<?php
require_once __DIR__ . '/includes/manager_context.php';
$activeModule = 'overview';
$pendingCount = count($pendingLeave) + count($pendingSick) + count($pendingAttendance);
$completedToday = count(array_filter($teamAttendanceToday, fn (array $row): bool => $row['status'] === 'Completed'));
$timeOffToday = count(array_filter($teamAttendanceToday, fn (array $row): bool => $row['status'] === 'Approved Time-Off'));
$clockedInToday = count(array_filter($teamAttendanceToday, fn (array $row): bool => $row['status'] === 'Clocked in'));
$notClockedInToday = count(array_filter($teamAttendanceToday, fn (array $row): bool => $row['status'] === 'Not clocked in'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Overview | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="manager-layout">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand"><span class="brand-mark">SS</span><span>SmartStaff</span></div>
            <nav class="nav-menu" aria-label="Manager workspace">
                <span class="nav-section-label">Manager workspace</span>
                <a href="manager_overview.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">OV</span>Overview</a>
                <a href="manager_attendance.php" class="nav-link"><span class="nav-link-icon">AT</span>Team attendance</a>
                <a href="manager_approvals.php" class="nav-link"><span class="nav-link-icon">AP</span>Approvals</a>
                <a href="manager_roster.php" class="nav-link"><span class="nav-link-icon">TR</span>Team roster</a>
            </nav>
            <div class="sidebar-footer"><p><?php echo htmlspecialchars($manager['name']); ?></p><a href="logout.php" class="logout-link">Log out</a></div>
        </aside>
        <main class="main-panel">
            <header class="topbar"><div><p class="eyebrow accent">Team management</p><h1>Manager overview</h1></div><div class="topbar-user"><div class="avatar-circle"><?php echo htmlspecialchars($manager['avatar']); ?></div><span><?php echo htmlspecialchars($manager['role']); ?></span></div></header>
            <?php if ($flashMessage !== ''): ?><div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div><?php endif; ?>
            <section class="manager-welcome"><div><p class="eyebrow accent">Today at a glance</p><h2>Keep your team moving.</h2><p>Review team presence, outstanding requests, and your direct reports from one manager workspace.</p></div><a href="manager_approvals.php" class="primary-btn">Review approvals</a></section>
            <section class="stats-grid">
                <div class="stat-card green"><span class="stat-label">Team members</span><strong><?php echo count($team); ?></strong></div>
                <div class="stat-card"><span class="stat-label">Completed today</span><strong><?php echo $completedToday; ?></strong></div>
                <div class="stat-card"><span class="stat-label">Time off today</span><strong><?php echo $timeOffToday; ?></strong></div>
                <div class="stat-card dark"><span class="stat-label">Pending approvals</span><strong><?php echo $pendingCount; ?></strong></div>
            </section>
            <section class="panel manager-attendance-dashboard">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow accent">Today · <?php echo htmlspecialchars(date('F j, Y')); ?></p>
                        <h2>Team attendance dashboard</h2>
                    </div>
                    <a href="manager_attendance.php" class="mini-btn approve">Full attendance</a>
                </div>
                <div class="attendance-status-overview">
                    <div class="attendance-status-card status-completed"><span>Completed</span><strong><?php echo $completedToday; ?></strong><small>Clocked in and out</small></div>
                    <div class="attendance-status-card status-clocked-in"><span>Clocked in</span><strong><?php echo $clockedInToday; ?></strong><small>Currently working</small></div>
                    <div class="attendance-status-card status-time-off"><span>Time off</span><strong><?php echo $timeOffToday; ?></strong><small>Approved today</small></div>
                    <div class="attendance-status-card status-not-clocked"><span>Not clocked in</span><strong><?php echo $notClockedInToday; ?></strong><small>Needs follow-up</small></div>
                </div>
                <div class="attendance-dashboard-list">
                    <div class="attendance-list-heading"><strong>Today’s team status</strong><span><?php echo count($teamAttendanceToday); ?> direct reports</span></div>
                    <?php if (empty($teamAttendanceToday)): ?>
                        <p class="empty-state">No direct reports assigned yet.</p>
                    <?php else: ?>
                        <?php foreach ($teamAttendanceToday as $row): ?>
                            <div class="attendance-dashboard-row"><div><strong><?php echo htmlspecialchars($row['name']); ?></strong><small><?php echo htmlspecialchars($row['role']); ?></small></div><div class="attendance-row-times"><small>In <?php echo htmlspecialchars($row['clock_in']); ?></small><small>Out <?php echo htmlspecialchars($row['clock_out']); ?></small></div><span class="status-pill <?php echo $row['status'] === 'Completed' ? 'status-approved' : ($row['status'] === 'Approved Time-Off' ? 'status-pending' : ($row['status'] === 'Not clocked in' ? 'status-rejected' : 'status-approved')); ?>"><?php echo htmlspecialchars($row['status']); ?></span></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            <section class="manager-module-grid">
                <a href="manager_attendance.php" class="manager-module-card"><span class="nav-link-icon">AT</span><strong>Team attendance</strong><small>See who is in, out, late, or on approved time off.</small><span class="module-link">Open module</span></a>
                <a href="manager_approvals.php" class="manager-module-card"><span class="nav-link-icon">AP</span><strong>Approvals</strong><small>Review leave and attendance requests from your direct reports.</small><span class="module-link">Open module</span></a>
                <a href="manager_roster.php" class="manager-module-card"><span class="nav-link-icon">TR</span><strong>Team roster</strong><small>View manager-only summaries for every direct report.</small><span class="module-link">Open module</span></a>
            </section>
        </main>
    </div>
</body>
</html>
