<?php
require_once __DIR__ . '/config.php';

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
$teamIds = array_map(static fn (array $employee): int => (int) $employee['id'], $team);
$leaveRequests = array_values(array_filter(getLeaveApplications(), static fn (array $request): bool => in_array((int) ($request['employee_id'] ?? 0), $teamIds, true)));
$sickLeaveRequests = array_values(array_filter(getSickLeaveApplications(), static fn (array $request): bool => in_array((int) ($request['employee_id'] ?? 0), $teamIds, true)));
$attendanceRequests = array_values(array_filter(getAttendanceRequests(), static fn (array $request): bool => in_array((int) ($request['employee_id'] ?? 0), $teamIds, true)));

function managerRequestBelongsToTeam(array $records, string $requestId, array $teamIds): bool
{
    foreach ($records as $record) {
        if ((string) ($record['id'] ?? '') === $requestId) {
            return in_array((int) ($record['employee_id'] ?? 0), $teamIds, true);
        }
    }

    return false;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $requestId = (string) ($_POST['request_id'] ?? '');

    if ($action === 'approve_leave' && managerRequestBelongsToTeam(getLeaveApplications(), $requestId, $teamIds)) {
        updateLeaveStatus($requestId, 'Approved');
        $_SESSION['flash_message'] = 'Leave request approved.';
    } elseif ($action === 'reject_leave' && managerRequestBelongsToTeam(getLeaveApplications(), $requestId, $teamIds)) {
        updateLeaveStatus($requestId, 'Rejected');
        $_SESSION['flash_message'] = 'Leave request rejected.';
    } elseif ($action === 'approve_sick_leave' && managerRequestBelongsToTeam(getSickLeaveApplications(), $requestId, $teamIds)) {
        updateSickLeaveStatus($requestId, 'Approved');
        $_SESSION['flash_message'] = 'Sick leave request approved.';
    } elseif ($action === 'reject_sick_leave' && managerRequestBelongsToTeam(getSickLeaveApplications(), $requestId, $teamIds)) {
        updateSickLeaveStatus($requestId, 'Rejected');
        $_SESSION['flash_message'] = 'Sick leave request rejected.';
    } elseif ($action === 'approve_attendance_request' && managerRequestBelongsToTeam(getAttendanceRequests(), $requestId, $teamIds)) {
        updateAttendanceRequestStatus($requestId, 'Approved');
        $_SESSION['flash_message'] = 'Attendance request approved.';
    } elseif ($action === 'reject_attendance_request' && managerRequestBelongsToTeam(getAttendanceRequests(), $requestId, $teamIds)) {
        updateAttendanceRequestStatus($requestId, 'Rejected');
        $_SESSION['flash_message'] = 'Attendance request rejected.';
    } elseif ($action !== '') {
        $_SESSION['flash_message'] = 'Unable to apply that action.';
    }

    redirect('manager_approvals.php');
}

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

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
        'clock_in' => $clockIn !== '' ? $clockIn : '--',
        'clock_out' => !empty($record['clock_out']) ? $record['clock_out'] : '--',
        'status' => $statusLabel,
    ];
}

$pendingLeave = array_values(array_filter($leaveRequests, fn (array $request): bool => ($request['status'] ?? '') === 'Pending'));
$pendingSick = array_values(array_filter($sickLeaveRequests, fn (array $request): bool => ($request['status'] ?? '') === 'Pending'));
$pendingAttendance = array_values(array_filter($attendanceRequests, fn (array $request): bool => ($request['status'] ?? '') === 'Pending'));
