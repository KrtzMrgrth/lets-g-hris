<?php
session_start();

date_default_timezone_set('Asia/Manila');

const DATA_DIR = __DIR__ . '/../data';
const EMPLOYEES_FILE = DATA_DIR . '/employees.json';
const LEAVE_FILE = DATA_DIR . '/leave_applications.json';
const SICK_LEAVE_FILE = DATA_DIR . '/sick_leave_applications.json';
const PAYROLL_FILE = DATA_DIR . '/payroll_records.json';
const ATTENDANCE_FILE = DATA_DIR . '/attendance_logs.json';
const ATTENDANCE_REQUEST_FILE = DATA_DIR . '/attendance_requests.json';

function ensureDataFiles(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }

    if (!file_exists(EMPLOYEES_FILE)) {
        $employees = [
            [
                'id' => 1000,
                'name' => 'HR Admin',
                'email' => 'admin@hrs.com',
                'password' => 'admin123',
                'department' => 'Administration',
                'role' => 'Administrator',
                'manager' => 'Board',
                'location' => 'Remote',
                'employment_type' => 'Full-time',
                'status' => 'Active',
                'phone' => '+1 (555) 010-0000',
                'join_date' => '2020-01-01',
                'avatar' => 'AD',
                'is_admin' => true
            ],
            [
                'id' => 1001,
                'name' => 'Alicia Morgan',
                'email' => 'alicia@hrs.com',
                'password' => '123456',
                'department' => 'Human Resources',
                'role' => 'HR Generalist',
                'manager' => 'Daniel Lee',
                'location' => 'New York',
                'employment_type' => 'Full-time',
                'status' => 'Active',
                'phone' => '+1 (212) 555-0148',
                'join_date' => '2022-06-10',
                'birthday' => '1995-06-15',
                'salary' => 6500.00,
                'avatar' => 'AM',
                'is_admin' => false
            ],
            [
                'id' => 1002,
                'name' => 'Marcus Hill',
                'email' => 'marcus@hrs.com',
                'password' => 'demo123',
                'department' => 'Engineering',
                'role' => 'Frontend Engineer',
                'manager' => 'Sophia Chen',
                'location' => 'Austin',
                'employment_type' => 'Full-time',
                'status' => 'Active',
                'phone' => '+1 (512) 555-0172',
                'join_date' => '2021-09-14',
                'birthday' => '1993-11-22',
                'salary' => 7200.00,
                'avatar' => 'MH',
                'is_admin' => false
            ],
            [
                'id' => 1003,
                'name' => 'Priya Nair',
                'email' => 'priya@hrs.com',
                'password' => 'hrpass',
                'department' => 'Operations',
                'role' => 'Operations Lead',
                'manager' => 'Alicia Morgan',
                'location' => 'San Diego',
                'employment_type' => 'Contract',
                'status' => 'Active',
                'phone' => '+1 (619) 555-0194',
                'join_date' => '2023-02-05',
                'birthday' => '1990-09-10',
                'salary' => 6800.00,
                'avatar' => 'PN',
                'is_admin' => false
            ]
        ];
        saveJson(EMPLOYEES_FILE, $employees);
    }

    if (!file_exists(LEAVE_FILE)) {
        saveJson(LEAVE_FILE, []);
    }

    if (!file_exists(SICK_LEAVE_FILE)) {
        saveJson(SICK_LEAVE_FILE, []);
    }

    if (!file_exists(PAYROLL_FILE)) {
        saveJson(PAYROLL_FILE, []);
    }

    if (!file_exists(ATTENDANCE_FILE)) {
        saveJson(ATTENDANCE_FILE, []);
    }

    if (!file_exists(ATTENDANCE_REQUEST_FILE)) {
        saveJson(ATTENDANCE_REQUEST_FILE, []);
    }
}

function saveJson(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function loadJson(string $path, array $default = []): array
{
    if (!file_exists($path)) {
        saveJson($path, $default);
        return $default;
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return $default;
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $default;
}

function ensureAdminExists(): void
{
    $employees = getEmployees();
    $adminIndex = null;

    foreach ($employees as $index => $employee) {
        $email = strtolower((string) ($employee['email'] ?? ''));
            if ($email === 'admin@hrs.com' || strtolower((string) ($employee['role'] ?? '')) === 'administrator') {
            $adminIndex = $index;
            $employees[$index]['is_admin'] = true;
            $employees[$index]['role'] = 'Administrator';
            $employees[$index]['email'] = 'admin@hrs.com';
            $employees[$index]['password'] = 'admin123';
            $employees[$index]['name'] = 'HR Admin';
            break;
        }
    }

    if ($adminIndex === null) {
        $employees[] = [
            'id' => 1000,
            'name' => 'HR Admin',
            'email' => 'admin@hrs.com',
            'password' => 'admin123',
            'department' => 'Administration',
            'role' => 'Administrator',
            'manager' => 'Board',
            'location' => 'Remote',
            'employment_type' => 'Full-time',
            'status' => 'Active',
            'phone' => '+1 (555) 010-0000',
            'join_date' => '2020-01-01',
            'avatar' => 'AD',
            'is_admin' => true
        ];
    }

    saveJson(EMPLOYEES_FILE, $employees);
}

function getLeaveEntitlements(string $employmentType): array
{
    if (strtolower(trim($employmentType)) !== 'full-time') {
        return [
            'vacation_leave' => 0,
            'sick_leave' => 0,
            'birthday_leave' => 0,
            'emergency_leave' => 0,
            'paid' => false,
        ];
    }

    return [
        'vacation_leave' => 15,
        'sick_leave' => 15,
        'birthday_leave' => 1,
        'emergency_leave' => 7,
        'paid' => true,
    ];
}

function normalizeLeaveType(string $leaveType): string
{
    $normalized = strtolower(trim($leaveType));

    if (in_array($normalized, ['annual leave', 'vacation leave'], true)) {
        return 'vacation_leave';
    }

    if ($normalized === 'sick leave') {
        return 'sick_leave';
    }

    if ($normalized === 'birthday leave') {
        return 'birthday_leave';
    }

    if ($normalized === 'emergency leave') {
        return 'emergency_leave';
    }

    return '';
}

function countLeaveDays(array $record, ?int $year = null, ?string $month = null): int
{
    $startDate = (string) ($record['start_date'] ?? '');
    $endDate = (string) ($record['end_date'] ?? $startDate);

    if ($startDate === '' || $endDate === '') {
        return 0;
    }

    try {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
    } catch (Exception $exception) {
        return 0;
    }

    if ($end < $start) {
        return 0;
    }

    $days = 0;
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        if ($year !== null && (int) $date->format('Y') !== $year) {
            continue;
        }

        if ($month !== null && $date->format('Y-m') !== $month) {
            continue;
        }

        $days++;
    }

    return $days;
}

function getEmployeeLeaveUsage(int $employeeId, ?int $year = null): array
{
    $year = $year ?? (int) date('Y');
    $usage = [
        'vacation_leave' => 0,
        'sick_leave' => 0,
        'birthday_leave' => 0,
        'emergency_leave' => 0,
    ];
    $records = array_merge(getLeaveApplications(), getSickLeaveApplications());

    foreach ($records as $record) {
        if ((int) ($record['employee_id'] ?? 0) !== $employeeId || strtolower((string) ($record['status'] ?? '')) !== 'approved') {
            continue;
        }

        $leaveKey = normalizeLeaveType((string) ($record['leave_type'] ?? ''));
        if ($leaveKey !== '') {
            $usage[$leaveKey] += countLeaveDays($record, $year);
        }
    }

    return $usage;
}

function getEmployeePaidLeaveDaysForMonth(int $employeeId, string $month): int
{
    $employee = getEmployeeById($employeeId);
    if ($employee === null || empty(($employee['leave_entitlements'] ?? [])['paid'])) {
        return 0;
    }

    $days = 0;
    $records = array_merge(getLeaveApplications(), getSickLeaveApplications());
    foreach ($records as $record) {
        if ((int) ($record['employee_id'] ?? 0) !== $employeeId || strtolower((string) ($record['status'] ?? '')) !== 'approved') {
            continue;
        }

        if (normalizeLeaveType((string) ($record['leave_type'] ?? '')) !== '') {
            $days += countLeaveDays($record, null, $month);
        }
    }

    return $days;
}

function getEmployees(): array
{
    ensureDataFiles();
    $employees = loadJson(EMPLOYEES_FILE, []);

    foreach ($employees as $index => $employee) {
        $email = strtolower((string) ($employee['email'] ?? ''));

        $employees[$index]['name'] = $employee['name'] ?? 'Employee';
        $employees[$index]['email'] = $employee['email'] ?? '';
        $employees[$index]['password'] = $employee['password'] ?? '';
        $employees[$index]['department'] = $employee['department'] ?? 'General';
        $employees[$index]['role'] = $employee['role'] ?? 'Employee';
        $employees[$index]['manager'] = $employee['manager'] ?? 'Pending Assignment';
        $employees[$index]['location'] = $employee['location'] ?? 'Office';
        $employees[$index]['employment_type'] = $employee['employment_type'] ?? 'Full-time';
        $employees[$index]['status'] = $employee['status'] ?? 'Active';
        $employees[$index]['phone'] = $employee['phone'] ?? '+1 (000) 000-0000';
        $employees[$index]['join_date'] = $employee['join_date'] ?? date('Y-m-d');
        $employees[$index]['avatar'] = $employee['avatar'] ?? strtoupper(substr($employees[$index]['name'], 0, 2));
        $employees[$index]['is_admin'] = !empty($employee['is_admin']);
        $employees[$index]['account_role'] = $employee['account_role'] ?? (!empty($employee['is_admin']) ? 'admin' : 'employee');
        $employees[$index]['manager_id'] = isset($employee['manager_id']) ? (int) $employee['manager_id'] : 0;
        // Keep is_admin in sync with account_role so every existing isAdmin() check still works.
        $employees[$index]['is_admin'] = $employees[$index]['is_admin'] || $employees[$index]['account_role'] === 'admin';
        $employees[$index]['leave_entitlements'] = getLeaveEntitlements((string) $employees[$index]['employment_type']);

        $defaultBirthdays = [
            'alicia@hrs.com' => '1995-06-15',
            'marcus@hrs.com' => '1993-11-22',
            'priya@hrs.com' => '1990-09-10',
        ];

        $defaultSalaries = [
            'alicia@hrs.com' => 6500.00,
            'marcus@hrs.com' => 7200.00,
            'priya@hrs.com' => 6800.00,
            'admin@hrs.com' => 0.00,
        ];

        $placeholderBirthday = ($employee['birthday'] ?? '') === '1990-01-01';
        $employees[$index]['birthday'] = (!empty($employee['birthday']) && !$placeholderBirthday) ? $employee['birthday'] : ($defaultBirthdays[$email] ?? '1990-01-01');
        $employees[$index]['salary'] = (array_key_exists($email, $defaultSalaries) && ((float) ($employee['salary'] ?? 0.0) === 0.0 || !array_key_exists('salary', $employee))) ? (float) $defaultSalaries[$email] : ((isset($employee['salary']) && $employee['salary'] !== '') ? (float) $employee['salary'] : ($defaultSalaries[$email] ?? 0.0));
    }

    usort($employees, static function (array $a, array $b): int {
        $nameA = strtolower((string) ($a['name'] ?? ''));
        $nameB = strtolower((string) ($b['name'] ?? ''));

        return strcmp($nameA, $nameB);
    });

    if ($employees !== loadJson(EMPLOYEES_FILE, [])) {
        saveJson(EMPLOYEES_FILE, $employees);
    }

    return $employees;
}

function getEmployeeById(int $id): ?array
{
    foreach (getEmployees() as $employee) {
        if ((int) $employee['id'] === $id) {
            return $employee;
        }
    }

    return null;
}

function getEmployeeByEmail(string $email): ?array
{
    foreach (getEmployees() as $employee) {
        if (strtolower((string) $employee['email']) === strtolower(trim($email))) {
            return $employee;
        }
    }

    return null;
}

function currentEmployee(): ?array
{
    if (empty($_SESSION['employee_id'])) {
        return null;
    }

    return getEmployeeById((int) $_SESSION['employee_id']);
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['employee_id']);
}

function isAdmin(): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    $employee = currentEmployee();
    if (!$employee) {
        return false;
    }

    return !empty($employee['is_admin']) || strtolower((string) ($employee['account_role'] ?? '')) === 'admin' || strtolower((string) $employee['role']) === 'administrator';
}

function isManager(): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    $employee = currentEmployee();
    if (!$employee) {
        return false;
    }

    return strtolower((string) ($employee['account_role'] ?? '')) === 'manager';
}

/**
 * Direct reports of the given manager, i.e. employees whose manager_id
 * points at this manager's employee id.
 */
function getTeamMembers(int $managerId): array
{
    if ($managerId <= 0) {
        return [];
    }

    return array_values(array_filter(getEmployees(), static fn (array $e): bool => (int) ($e['manager_id'] ?? 0) === $managerId));
}

function getManagerOptions(): array
{
    return array_values(array_filter(getEmployees(), static function (array $e): bool {
        $role = strtolower((string) ($e['account_role'] ?? ''));
        return $role === 'manager' || $role === 'admin';
    }));
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function getLeaveApplications(): array
{
    ensureDataFiles();
    $records = loadJson(LEAVE_FILE, []);

    foreach ($records as $index => $record) {
        if (empty($record['id'])) {
            $records[$index]['id'] = generateRecordId();
        }
    }

    if ($records !== loadJson(LEAVE_FILE, [])) {
        saveJson(LEAVE_FILE, $records);
    }

    return $records;
}

function getSickLeaveApplications(): array
{
    ensureDataFiles();
    $records = loadJson(SICK_LEAVE_FILE, []);

    foreach ($records as $index => $record) {
        if (empty($record['id'])) {
            $records[$index]['id'] = generateRecordId();
        }
    }

    if ($records !== loadJson(SICK_LEAVE_FILE, [])) {
        saveJson(SICK_LEAVE_FILE, $records);
    }

    return $records;
}

function getPayrollRecords(): array
{
    ensureDataFiles();
    $records = loadJson(PAYROLL_FILE, []);

    foreach ($records as $index => $record) {
        if (empty($record['id'])) {
            $records[$index]['id'] = generateRecordId();
        }
    }

    if ($records !== loadJson(PAYROLL_FILE, [])) {
        saveJson(PAYROLL_FILE, $records);
    }

    return $records;
}

function savePayrollRecord(array $record): void
{
    $records = getPayrollRecords();

    if (empty($record['id'])) {
        $record['id'] = generateRecordId();
    }

    $record['created_at'] = $record['created_at'] ?? date('Y-m-d H:i:s');
    $record['cutoff'] = $record['cutoff'] ?? date('Y-m-d');

    $matchedIndex = null;
    foreach ($records as $index => $existingRecord) {
        if ((int) ($existingRecord['employee_id'] ?? 0) === (int) ($record['employee_id'] ?? 0) && (string) ($existingRecord['cutoff'] ?? '') === (string) $record['cutoff']) {
            $matchedIndex = $index;
            break;
        }
    }

    if ($matchedIndex !== null) {
        $records[$matchedIndex] = $record;
    } else {
        $records[] = $record;
    }

    saveJson(PAYROLL_FILE, $records);
}

function getPayrollForEmployeeAndCutoff(int $employeeId, string $cutoff): ?array
{
    foreach (getPayrollRecords() as $record) {
        if ((int) ($record['employee_id'] ?? 0) === $employeeId && (string) ($record['cutoff'] ?? '') === $cutoff) {
            return $record;
        }
    }

    return null;
}

function getLatestPayrollForEmployee(int $employeeId): ?array
{
    $records = getPayrollRecords();
    $latest = null;

    foreach ($records as $record) {
        if ((int) ($record['employee_id'] ?? 0) === $employeeId) {
            if ($latest === null || strtotime((string) ($record['created_at'] ?? '1970-01-01')) > strtotime((string) ($latest['created_at'] ?? '1970-01-01')) ) {
                $latest = $record;
            }
        }
    }

    return $latest;
}

function getEmployeeAttendanceRecords(int $employeeId): array
{
    ensureDataFiles();
    $records = loadJson(ATTENDANCE_FILE, []);
    $filtered = [];

    foreach ($records as $record) {
        if ((int) ($record['employee_id'] ?? 0) === $employeeId) {
            $filtered[] = $record;
        }
    }

    usort($filtered, static function ($a, $b): int {
        $dateA = (string) ($a['date'] ?? '1970-01-01');
        $dateB = (string) ($b['date'] ?? '1970-01-01');

        $cmp = strcmp($dateB, $dateA);
        if ($cmp !== 0) {
            return $cmp;
        }

        return attendanceTimeToMinutes((string) ($b['clock_in'] ?? '')) <=> attendanceTimeToMinutes((string) ($a['clock_in'] ?? ''));
    });

    return $filtered;
}

function getTodayAttendanceByEmployee(int $employeeId): ?array
{
    $today = date('Y-m-d');

    foreach (getEmployeeAttendanceRecords($employeeId) as $record) {
        if ((string) ($record['date'] ?? '') === $today) {
            return $record;
        }
    }

    return null;
}

function addEmployeeAttendanceRecord(array $record): void
{
    $records = loadJson(ATTENDANCE_FILE, []);
    $records[] = $record;
    saveJson(ATTENDANCE_FILE, $records);
}

function formatClockTime(string $rawTime, string $default = '09:00 AM'): string
{
    $rawTime = trim($rawTime);
    if ($rawTime === '') {
        return $default;
    }

    foreach (['H:i', 'h:i A', 'h:i a'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $rawTime);
        if ($parsed !== false) {
            return $parsed->format('h:i A');
        }
    }

    return $default;
}

/**
 * Converts a stored clock time ("09:00 AM", "Time Off", "", etc.) into
 * minutes-since-midnight so attendance records can be sorted chronologically
 * instead of alphabetically (plain string comparison puts "01:00 PM" before
 * "11:00 AM" because "0" < "1", which is wrong).
 */
function attendanceTimeToMinutes(string $time): int
{
    $parsed = DateTimeImmutable::createFromFormat('h:i A', trim($time));
    if ($parsed === false) {
        return -1;
    }

    return ((int) $parsed->format('H')) * 60 + (int) $parsed->format('i');
}

function applyAttendanceOverride(int $employeeId, string $date, string $action, string $clockIn = '', string $clockOut = ''): bool
{
    $employee = getEmployeeById($employeeId);
    if ($employee === null || $date === '') {
        return false;
    }

    $records = loadJson(ATTENDANCE_FILE, []);
    $recordIndex = null;
    foreach ($records as $index => $record) {
        if ((int) ($record['employee_id'] ?? 0) === $employeeId && (string) ($record['date'] ?? '') === $date) {
            $recordIndex = $index;
            break;
        }
    }

    $record = $recordIndex !== null ? $records[$recordIndex] : [
        'id' => generateRecordId(),
        'employee_id' => $employeeId,
        'employee_name' => $employee['name'],
        'date' => $date,
        'day' => date('l', strtotime($date)),
        'clock_in' => '',
        'clock_out' => '',
    ];

    if ($action === 'fix_missed_clock_in') {
        $record['clock_in'] = formatClockTime($clockIn, '09:00 AM');
        $record['clock_out'] = $record['clock_out'] ?? '';
        $record['status'] = !empty($record['clock_out']) ? 'Completed' : 'Adjusted - Present';
    } elseif ($action === 'fix_missed_clock_out') {
        if (empty($record['clock_in']) || $record['clock_in'] === 'Time Off') {
            return false;
        }
        $record['clock_out'] = formatClockTime($clockOut, '06:00 PM');
        $record['status'] = 'Completed';
    } elseif ($action === 'approve_time_off') {
        $record['clock_in'] = 'Time Off';
        $record['clock_out'] = 'Time Off';
        $record['status'] = 'Approved Time-Off';
    } else {
        return false;
    }

    if ($recordIndex === null) {
        $records[] = $record;
    } else {
        $records[$recordIndex] = $record;
    }

    saveJson(ATTENDANCE_FILE, $records);
    return true;
}

function updateEmployeeAttendanceOut(int $employeeId, string $date, string $clockOutTime): bool
{
    $records = loadJson(ATTENDANCE_FILE, []);
    $updated = false;

    foreach ($records as &$record) {
        if ((int) ($record['employee_id'] ?? 0) === $employeeId && (string) ($record['date'] ?? '') === $date && empty($record['clock_out'])) {
            $record['clock_out'] = $clockOutTime;
            $record['status'] = 'Completed';
            $updated = true;
            break;
        }
    }

    if ($updated) {
        saveJson(ATTENDANCE_FILE, $records);
    }

    return $updated;
}

function getAttendanceRequests(): array
{
    ensureDataFiles();
    $records = loadJson(ATTENDANCE_REQUEST_FILE, []);

    foreach ($records as $index => $record) {
        if (empty($record['id'])) {
            $records[$index]['id'] = generateRecordId();
        }
    }

    if ($records !== loadJson(ATTENDANCE_REQUEST_FILE, [])) {
        saveJson(ATTENDANCE_REQUEST_FILE, $records);
    }

    return $records;
}

function addAttendanceRequest(array $request): void
{
    $records = getAttendanceRequests();
    $request['id'] = $request['id'] ?? generateRecordId();
    $records[] = $request;
    saveJson(ATTENDANCE_REQUEST_FILE, $records);
}

function generateRecordId(): string
{
    return (string) (time() . '-' . bin2hex(random_bytes(4)));
}

function addLeaveApplication(array $application): void
{
    $records = getLeaveApplications();
    if (empty($application['id'])) {
        $application['id'] = generateRecordId();
    }
    $records[] = $application;
    saveJson(LEAVE_FILE, $records);
}

function addSickLeaveApplication(array $application): void
{
    $records = getSickLeaveApplications();
    if (empty($application['id'])) {
        $application['id'] = generateRecordId();
    }
    $records[] = $application;
    saveJson(SICK_LEAVE_FILE, $records);
}

function updateLeaveStatus(string $requestId, string $status): bool
{
    $records = getLeaveApplications();
    $updated = false;

    foreach ($records as &$record) {
        if ((string) ($record['id'] ?? '') === $requestId || ($requestId === '' && !empty($record['employee_name']) && !empty($record['start_date']) && !empty($record['end_date']))) {
            $record['status'] = $status;
            $updated = true;
        }
    }

    if ($updated) {
        saveJson(LEAVE_FILE, $records);
    }

    return $updated;
}

function updateSickLeaveStatus(string $requestId, string $status): bool
{
    $records = getSickLeaveApplications();
    $updated = false;

    foreach ($records as &$record) {
        if ((string) ($record['id'] ?? '') === $requestId || ($requestId === '' && !empty($record['employee_name']) && !empty($record['start_date']) && !empty($record['end_date']))) {
            $record['status'] = $status;
            $updated = true;
        }
    }

    if ($updated) {
        saveJson(SICK_LEAVE_FILE, $records);
    }

    return $updated;
}

function updateAttendanceRequestStatus(string $requestId, string $status): bool
{
    $records = getAttendanceRequests();
    $updated = false;

    foreach ($records as &$record) {
        if ((string) ($record['id'] ?? '') === $requestId) {
            $record['status'] = $status;
            $updated = true;
        }
    }

    if ($updated) {
        saveJson(ATTENDANCE_REQUEST_FILE, $records);
    }

    return $updated;
}

ensureDataFiles();
ensureAdminExists();