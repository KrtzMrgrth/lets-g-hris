<?php
require_once __DIR__ . '/includes/manager_context.php';

function renderManagerApproval(array $requests, string $title, string $approveAction, string $rejectAction, string $emptyMessage): void
{
    ?>
    <section class="panel panel-approval">
        <div class="panel-header"><h3><?php echo htmlspecialchars($title); ?></h3><span class="badge"><?php echo count($requests); ?></span></div>
        <div class="list-table">
            <?php if (empty($requests)): ?><p class="empty-state"><?php echo htmlspecialchars($emptyMessage); ?></p><?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="list-row approval-row"><div><strong><?php echo htmlspecialchars($request['employee_name'] ?? 'Employee'); ?></strong><small><?php echo htmlspecialchars((string) ($request['leave_type'] ?? $request['request_type'] ?? 'Request')); ?> · <?php echo htmlspecialchars((string) ($request['start_date'] ?? $request['request_date'] ?? '')); ?><?php if (!empty($request['end_date'])): ?> to <?php echo htmlspecialchars($request['end_date']); ?><?php endif; ?><?php if (!empty($request['reason'])): ?> · <?php echo htmlspecialchars($request['reason']); ?><?php endif; ?></small></div><div class="approval-actions"><form method="POST" action="manager_approvals.php"><input type="hidden" name="action" value="<?php echo htmlspecialchars($approveAction); ?>"><input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>"><button type="submit" class="mini-btn approve">Approve</button></form><form method="POST" action="manager_approvals.php"><input type="hidden" name="action" value="<?php echo htmlspecialchars($rejectAction); ?>"><input type="hidden" name="request_id" value="<?php echo htmlspecialchars((string) ($request['id'] ?? '')); ?>"><button type="submit" class="mini-btn reject">Reject</button></form></div></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manager Approvals | SmartStaff</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="manager-layout">
<div class="app-shell"><aside class="sidebar"><div class="brand"><span class="brand-mark">SS</span><span>SmartStaff</span></div><nav class="nav-menu" aria-label="Manager workspace"><span class="nav-section-label">Manager workspace</span><a href="manager_overview.php" class="nav-link"><span class="nav-link-icon">OV</span>Overview</a><a href="manager_attendance.php" class="nav-link"><span class="nav-link-icon">AT</span>Team attendance</a><a href="manager_approvals.php" class="nav-link active" aria-current="page"><span class="nav-link-icon">AP</span>Approvals</a><a href="manager_roster.php" class="nav-link"><span class="nav-link-icon">TR</span>Team roster</a></nav><div class="sidebar-footer"><p><?php echo htmlspecialchars($manager['name']); ?></p><a href="logout.php" class="logout-link">Log out</a></div></aside>
<main class="main-panel"><header class="topbar"><div><p class="eyebrow accent">Manager module</p><h1>Team approvals</h1></div><div class="topbar-user"><div class="avatar-circle"><?php echo htmlspecialchars($manager['avatar']); ?></div><span><?php echo htmlspecialchars($manager['role']); ?></span></div></header><?php if ($flashMessage !== ''): ?><div class="alert alert-success dashboard-notice"><?php echo htmlspecialchars($flashMessage); ?></div><?php endif; ?><section class="panel manager-module-heading"><p class="eyebrow accent">Direct reports only</p><h2>Requests needing your decision</h2><p>Approve or reject leave and attendance requests submitted by your team.</p></section><div class="approval-column"><?php renderManagerApproval($pendingLeave, 'Leave approval', 'approve_leave', 'reject_leave', 'No pending leave requests from your team.'); ?><?php renderManagerApproval($pendingSick, 'Sick leave approval', 'approve_sick_leave', 'reject_sick_leave', 'No pending sick leave requests from your team.'); ?><?php renderManagerApproval($pendingAttendance, 'Attendance request approval', 'approve_attendance_request', 'reject_attendance_request', 'No pending attendance requests from your team.'); ?></div></main></div>
</body></html>
