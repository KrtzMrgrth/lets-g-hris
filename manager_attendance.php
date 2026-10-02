<?php
require_once __DIR__ . '/includes/manager_context.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Attendance | SmartStaff</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="manager-layout">
    <div class="app-shell">
        <aside class="sidebar"><div class="brand"><span class="brand-mark">SS</span><span>SmartStaff</span></div><nav class="nav-menu" aria-label="Manager workspace"><span class="nav-section-label">Manager workspace</span><a href="manager_overview.php" class="nav-link"><span class="nav-link-icon">OV</span>Overview</a><a href="manager_attendance.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">AT</span>Team attendance</a><a href="manager_approvals.php" class="nav-link"><span class="nav-link-icon">AP</span>Approvals</a><a href="manager_roster.php" class="nav-link"><span class="nav-link-icon">TR</span>Team roster</a></nav><div class="sidebar-footer"><p><?php echo htmlspecialchars($manager['name']); ?></p><a href="logout.php" class="logout-link">Log out</a></div></aside>
        <main class="main-panel"><header class="topbar"><div><p class="eyebrow accent">Manager module</p><h1>Team attendance</h1></div><div class="topbar-user"><div class="avatar-circle"><?php echo htmlspecialchars($manager['avatar']); ?></div><span><?php echo htmlspecialchars($manager['role']); ?></span></div></header>
            <section class="panel manager-module-heading"><p class="eyebrow accent">Today · <?php echo htmlspecialchars(date('F j, Y')); ?></p><h2>Who is on the clock?</h2><p>Monitor attendance for your direct reports without entering HR attendance tools.</p></section>
            <section class="panel attendance-summary-panel"><div class="panel-header"><h3>Live team attendance</h3><span class="badge"><?php echo count($teamAttendanceToday); ?> team members</span></div><div class="list-table"><?php if (empty($teamAttendanceToday)): ?><p class="empty-state">You don't have any direct reports assigned yet.</p><?php else: ?><?php foreach ($teamAttendanceToday as $row): ?><div class="list-row"><div><strong><?php echo htmlspecialchars($row['name']); ?></strong><small><?php echo htmlspecialchars($row['role']); ?> · In: <?php echo htmlspecialchars($row['clock_in']); ?> · Out: <?php echo htmlspecialchars($row['clock_out']); ?></small></div><span class="status-pill <?php echo $row['status'] === 'Completed' ? 'status-approved' : ($row['status'] === 'Approved Time-Off' ? 'status-pending' : 'status-rejected'); ?>"><?php echo htmlspecialchars($row['status']); ?></span></div><?php endforeach; ?><?php endif; ?></div></section>
        </main>
    </div>
</body>
</html>
