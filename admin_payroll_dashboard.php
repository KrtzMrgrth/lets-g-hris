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
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

$selectedEmployeeId = (int) ($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
$selectedCutoff = (string) ($_GET['cutoff'] ?? $_POST['cutoff'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_payroll') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $selectedEmployee = getEmployeeById($employeeId);

        if ($selectedEmployee === null) {
            $_SESSION['flash_message'] = 'Please select a valid employee.';
            redirect('admin_payroll_dashboard.php');
        }

        $cutoffDate = trim((string) ($_POST['cutoff'] ?? date('Y-m-d')));
        $baseSalary = max(0.0, (float) ($_POST['base_salary'] ?? 0));
        $allowance = max(0.0, (float) ($_POST['allowance'] ?? 0));
        $lateMinutes = max(0.0, (float) ($_POST['late_minutes'] ?? 0));
        $lateDeduction = ($lateMinutes / 60.0) * 200.0;
        $absentDays = max(0.0, (float) ($_POST['absent_days'] ?? 0));
        $absenceDeduction = $absentDays * 260.0;
        $sss = max(0.0, (float) ($_POST['sss'] ?? 0));
        $philHealth = max(0.0, (float) ($_POST['philhealth'] ?? 0));
        $pagIbig = max(0.0, (float) ($_POST['pagibig'] ?? 0));
        $overtimeHours = max(0.0, (float) ($_POST['overtime_hours'] ?? 0));
        $overtimeRate = (float) ($_POST['overtime_rate'] ?? 250.0);
        $overtimePay = $overtimeHours * $overtimeRate;
        $paidLeaveDays = getEmployeePaidLeaveDaysForMonth($employeeId, substr($cutoffDate, 0, 7));
        $netPay = $baseSalary + $allowance + $overtimePay - $sss - $philHealth - $pagIbig - $lateDeduction - $absenceDeduction;

        savePayrollRecord([
            'employee_id' => $employeeId,
            'employee_name' => $selectedEmployee['name'],
            'department' => $selectedEmployee['department'] ?? 'General',
            'role' => $selectedEmployee['role'] ?? 'Employee',
            'base_salary' => $baseSalary,
            'allowance' => $allowance,
            'late_minutes' => $lateMinutes,
            'late_deduction' => $lateDeduction,
            'absent_days' => $absentDays,
            'absence_deduction' => $absenceDeduction,
            'sss' => $sss,
            'philhealth' => $philHealth,
            'pagibig' => $pagIbig,
            'overtime_hours' => $overtimeHours,
            'overtime_rate' => $overtimeRate,
            'overtime_pay' => $overtimePay,
            'paid_leave_days' => $paidLeaveDays,
            'net_pay' => $netPay,
            'cutoff' => $cutoffDate,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['flash_message'] = 'Payslip saved successfully for ' . $selectedEmployee['name'] . ' for cutoff ' . $cutoffDate . '.';
        redirect('admin_payroll_dashboard.php?employee_id=' . $employeeId . '&cutoff=' . urlencode($cutoffDate));
    }
}

$payrollRecords = getPayrollRecords();
$payrollRows = [];
$payrollByEmployee = [];

foreach ($payrollRecords as $record) {
    $recordEmployeeId = (int) ($record['employee_id'] ?? 0);

    if (!isset($payrollByEmployee[$recordEmployeeId])) {
        $payrollByEmployee[$recordEmployeeId] = [
            'employee_id' => $recordEmployeeId,
            'employee_name' => (string) ($record['employee_name'] ?? 'Employee'),
            'department' => (string) ($record['department'] ?? 'General'),
            'role' => (string) ($record['role'] ?? 'Employee'),
            'records' => []
        ];
    }

    $payrollByEmployee[$recordEmployeeId]['records'][] = $record;
}

foreach ($payrollByEmployee as &$employeePayroll) {
    usort($employeePayroll['records'], static function (array $first, array $second): int {
        return strcmp((string) ($second['cutoff'] ?? ''), (string) ($first['cutoff'] ?? ''));
    });
}
unset($employeePayroll);

uasort($payrollByEmployee, static function (array $first, array $second): int {
    return strcasecmp($first['employee_name'], $second['employee_name']);
});

foreach ($employees as $person) {
    $latestRecord = getLatestPayrollForEmployee((int) ($person['id'] ?? 0));
    $baseSalary = isset($person['salary']) ? (float) $person['salary'] : 0.0;

    if ($latestRecord !== null) {
        $baseSalary = isset($latestRecord['base_salary']) ? (float) $latestRecord['base_salary'] : $baseSalary;
    }

    $payrollRows[] = [
        'id' => (int) ($person['id'] ?? 0),
        'name' => $person['name'] ?? 'Employee',
        'department' => $person['department'] ?? 'General',
        'role' => $person['role'] ?? 'Employee',
        'base_salary' => $baseSalary,
        'sss' => $latestRecord['sss'] ?? min($baseSalary * 0.045, 1125.00),
        'philhealth' => $latestRecord['philhealth'] ?? min($baseSalary * 0.04, 4500.00),
        'pagibig' => $latestRecord['pagibig'] ?? min($baseSalary * 0.02, 200.00),
        'allowance' => $latestRecord['allowance'] ?? 350.00,
        'overtime_hours' => $latestRecord['overtime_hours'] ?? 0,
        'overtime_rate' => $latestRecord['overtime_rate'] ?? 250.00,
        'overtime_pay' => $latestRecord['overtime_pay'] ?? 0.0,
        'late_minutes' => $latestRecord['late_minutes'] ?? 0,
        'late_deduction' => $latestRecord['late_deduction'] ?? 0.0,
        'absent_days' => $latestRecord['absent_days'] ?? 0,
        'absence_deduction' => $latestRecord['absence_deduction'] ?? 0.0,
        'paid_leave_days' => $latestRecord['paid_leave_days'] ?? getEmployeePaidLeaveDaysForMonth((int) ($person['id'] ?? 0), date('Y-m')),
        'net_pay' => $latestRecord['net_pay'] ?? ($baseSalary + ($latestRecord['allowance'] ?? 350.00) + (($latestRecord['overtime_hours'] ?? 0) * ($latestRecord['overtime_rate'] ?? 250.00)) - ($latestRecord['sss'] ?? min($baseSalary * 0.045, 1125.00)) - ($latestRecord['philhealth'] ?? min($baseSalary * 0.04, 4500.00)) - ($latestRecord['pagibig'] ?? min($baseSalary * 0.02, 200.00)) - ($latestRecord['late_deduction'] ?? 0.0) - ($latestRecord['absence_deduction'] ?? 0.0)),
        'cutoff' => $latestRecord['cutoff'] ?? date('Y-m-d'),
    ];
}

$selectedPayroll = null;
if ($selectedEmployeeId > 0) {
    $selectedPayroll = getPayrollForEmployeeAndCutoff($selectedEmployeeId, $selectedCutoff);
}

if ($selectedPayroll === null && $selectedEmployeeId > 0) {
    $selectedEmployee = getEmployeeById($selectedEmployeeId);

    if ($selectedEmployee !== null) {
        $baseSalary = (float) ($selectedEmployee['salary'] ?? 0.0);
        $allowance = 350.0;
        $sss = min($baseSalary * 0.045, 1125.00);
        $philHealth = min($baseSalary * 0.04, 4500.00);
        $pagIbig = min($baseSalary * 0.02, 200.00);
        $overtimeHours = 0.0;
        $overtimeRate = 250.0;
        $overtimePay = 0.0;
        $lateMinutes = 0.0;
        $lateDeduction = 0.0;
        $absentDays = 0.0;
        $absenceDeduction = 0.0;
        $paidLeaveDays = getEmployeePaidLeaveDaysForMonth($selectedEmployeeId, substr($selectedCutoff, 0, 7));
        $netPay = $baseSalary + $allowance + $overtimePay - $sss - $philHealth - $pagIbig - $lateDeduction - $absenceDeduction;

        $selectedPayroll = [
            'employee_id' => $selectedEmployeeId,
            'employee_name' => (string) ($selectedEmployee['name'] ?? 'Employee'),
            'department' => (string) ($selectedEmployee['department'] ?? 'General'),
            'role' => (string) ($selectedEmployee['role'] ?? 'Employee'),
            'base_salary' => $baseSalary,
            'allowance' => $allowance,
            'late_minutes' => $lateMinutes,
            'late_deduction' => $lateDeduction,
            'absent_days' => $absentDays,
            'absence_deduction' => $absenceDeduction,
            'sss' => $sss,
            'philhealth' => $philHealth,
            'pagibig' => $pagIbig,
            'overtime_hours' => $overtimeHours,
            'overtime_rate' => $overtimeRate,
            'overtime_pay' => $overtimePay,
            'paid_leave_days' => $paidLeaveDays,
            'net_pay' => $netPay,
            'cutoff' => $selectedCutoff,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if ($selectedPayroll === null && $selectedEmployeeId === 0 && !empty($payrollRecords)) {
    $selectedEmployeeId = (int) ($payrollRecords[0]['employee_id'] ?? 0);
    $selectedCutoff = (string) ($payrollRecords[0]['cutoff'] ?? date('Y-m-d'));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Dashboard | SmartStaff</title>
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
                <a href="admin_employee_dashboard.php" class="nav-link">Employee Dashboard</a>
                <a href="admin_payroll_dashboard.php" class="nav-link active">Payslip Dashboard</a>
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
                    <h1>Payslip dashboard</h1>
                </div>
                <div class="topbar-user">
                    <div class="avatar-circle"><?php echo htmlspecialchars($employee['avatar']); ?></div>
                    <span><?php echo htmlspecialchars($employee['role']); ?></span>
                </div>
            </header>

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($flashMessage); ?></div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card green">
                    <span class="stat-label">Employees</span>
                    <strong><?php echo count($employees); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Pay slips saved</span>
                    <strong><?php echo count($payrollRecords); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Latest net pay</span>
                    <strong>₱<?php echo number_format((float) ($selectedPayroll['net_pay'] ?? 0.0), 2); ?></strong>
                </div>
                <div class="stat-card dark">
                    <span class="stat-label">Cutoff</span>
                    <strong><?php echo htmlspecialchars($selectedCutoff ?: date('Y-m-d')); ?></strong>
                </div>
            </section>

            <section class="content-grid two-columns" style="margin-top: 24px;">
                <div class="panel">
                    <div class="panel-header">
                        <h3>Employee payslip list</h3>
                    </div>

                    <div class="list-table">
                        <?php if (empty($payrollByEmployee)): ?>
                            <p class="empty-state">No payslip records saved yet.</p>
                        <?php else: ?>
                            <?php foreach ($payrollByEmployee as $employeePayroll): ?>
                                <details class="pay-details employee-account-details">
                                    <summary class="pay-summary employee-account-summary">
                                        <div>
                                            <strong><?php echo htmlspecialchars($employeePayroll['employee_name']); ?></strong>
                                            <small><?php echo htmlspecialchars($employeePayroll['department']); ?> · <?php echo htmlspecialchars($employeePayroll['role']); ?></small>
                                        </div>
                                        <span class="status-pill status-approved"><?php echo count($employeePayroll['records']); ?> payslip record<?php echo count($employeePayroll['records']) === 1 ? '' : 's'; ?></span>
                                    </summary>

                                    <div class="employee-cutoff-list">
                                        <?php foreach ($employeePayroll['records'] as $record): ?>
                                            <?php
                                                $recordBaseSalary = (float) ($record['base_salary'] ?? 0.0);
                                                $recordAllowance = (float) ($record['allowance'] ?? 0.0);
                                                $recordGrossPay = $recordBaseSalary + $recordAllowance;
                                                $recordPaidLeaveDays = (float) ($record['paid_leave_days'] ?? getEmployeePaidLeaveDaysForMonth((int) ($record['employee_id'] ?? 0), substr((string) ($record['cutoff'] ?? date('Y-m-d')), 0, 7)));
                                                $recordOvertimePay = (float) ($record['overtime_pay'] ?? ((float) ($record['overtime_hours'] ?? 0.0) * (float) ($record['overtime_rate'] ?? 250.0)));
                                                $recordTotalDeductions = (float) (($record['sss'] ?? 0.0) + ($record['philhealth'] ?? 0.0) + ($record['pagibig'] ?? 0.0) + ($record['late_deduction'] ?? 0.0) + ($record['absence_deduction'] ?? 0.0));
                                                $recordNetPay = (float) ($record['net_pay'] ?? ($recordGrossPay + $recordOvertimePay - $recordTotalDeductions));
                                            ?>
                                            <details class="pay-details employee-cutoff-details">
                                                <summary class="pay-summary payroll-summary">
                                                    <div>
                                                        <strong>Cutoff: <?php echo htmlspecialchars((string) ($record['cutoff'] ?? date('Y-m-d'))); ?></strong>
                                                        <small>Saved <?php echo htmlspecialchars((string) ($record['created_at'] ?? '')); ?></small>
                                                    </div>
                                                    <div class="payroll-details">
                                                        <span class="status-pill status-approved">₱<?php echo number_format($recordNetPay, 2); ?></span>
                                                        <small>Click to view payslip</small>
                                                    </div>
                                                </summary>

                                                <div class="payslip-summary-box payslip-list-breakdown">
                                                    <div class="payslip-summary-header">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($employeePayroll['employee_name']); ?></strong>
                                                            <small><?php echo htmlspecialchars($employeePayroll['department']); ?> · <?php echo htmlspecialchars($employeePayroll['role']); ?></small>
                                                        </div>
                                                        <span class="status-pill status-approved">Net pay: ₱<?php echo number_format($recordNetPay, 2); ?></span>
                                                    </div>

                                                    <div class="details-grid">
                                                        <div class="detail-box"><span>Payroll cutoff</span><strong><?php echo htmlspecialchars((string) ($record['cutoff'] ?? date('Y-m-d'))); ?></strong></div>
                                                        <div class="detail-box"><span>Gross pay</span><strong>₱<?php echo number_format($recordGrossPay, 2); ?></strong></div>
                                                        <div class="detail-box"><span>Basic salary</span><strong>₱<?php echo number_format($recordBaseSalary, 2); ?></strong></div>
                                                        <div class="detail-box"><span>Benefits / allowance</span><strong>₱<?php echo number_format($recordAllowance, 2); ?></strong></div>
                                                        <div class="detail-box"><span>Overtime pay</span><strong>₱<?php echo number_format($recordOvertimePay, 2); ?></strong></div>
                                                        <div class="detail-box"><span>Overtime hours</span><strong><?php echo number_format((float) ($record['overtime_hours'] ?? 0), 2); ?> hrs</strong></div>
                                                        <div class="detail-box"><span>Paid leave days</span><strong><?php echo number_format($recordPaidLeaveDays, 0); ?> days</strong></div>
                                                        <div class="detail-box"><span>Late minutes</span><strong><?php echo number_format((float) ($record['late_minutes'] ?? 0), 0); ?></strong></div>
                                                        <div class="detail-box"><span>Late deduction</span><strong>-₱<?php echo number_format((float) ($record['late_deduction'] ?? 0.0), 2); ?></strong></div>
                                                        <div class="detail-box"><span>Absent days</span><strong><?php echo number_format((float) ($record['absent_days'] ?? 0), 0); ?></strong></div>
                                                        <div class="detail-box"><span>Absent deduction</span><strong>-₱<?php echo number_format((float) ($record['absence_deduction'] ?? 0.0), 2); ?></strong></div>
                                                        <div class="detail-box"><span>SSS contribution</span><strong>-₱<?php echo number_format((float) ($record['sss'] ?? 0.0), 2); ?></strong></div>
                                                        <div class="detail-box"><span>PhilHealth</span><strong>-₱<?php echo number_format((float) ($record['philhealth'] ?? 0.0), 2); ?></strong></div>
                                                        <div class="detail-box"><span>Pag-IBIG</span><strong>-₱<?php echo number_format((float) ($record['pagibig'] ?? 0.0), 2); ?></strong></div>
                                                        <div class="detail-box"><span>Total deductions</span><strong>-₱<?php echo number_format($recordTotalDeductions, 2); ?></strong></div>
                                                        <div class="detail-box"><span>Net pay</span><strong>₱<?php echo number_format($recordNetPay, 2); ?></strong></div>
                                                    </div>
                                                </div>
                                            </details>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selectedPayroll !== null): ?>
                    <div class="panel">
                        <div class="panel-header">
                            <h3>Payslip details</h3>
                        </div>

                        <?php
                            $selectedEmployeeName = (string) (($selectedPayroll['employee_name'] ?? '') ?: ($employees[0]['name'] ?? 'Employee'));
                            $grossPay = (float) (($selectedPayroll['base_salary'] ?? 0.0) + ($selectedPayroll['allowance'] ?? 0.0));
                            $selectedOvertimePay = (float) ($selectedPayroll['overtime_pay'] ?? ((float) ($selectedPayroll['overtime_hours'] ?? 0.0) * (float) ($selectedPayroll['overtime_rate'] ?? 250.0)));
                            $selectedPaidLeaveDays = (float) ($selectedPayroll['paid_leave_days'] ?? getEmployeePaidLeaveDaysForMonth((int) ($selectedPayroll['employee_id'] ?? 0), substr((string) ($selectedPayroll['cutoff'] ?? $selectedCutoff), 0, 7)));
                            $totalDeductions = (float) (($selectedPayroll['sss'] ?? 0.0) + ($selectedPayroll['philhealth'] ?? 0.0) + ($selectedPayroll['pagibig'] ?? 0.0) + ($selectedPayroll['late_deduction'] ?? 0.0) + ($selectedPayroll['absence_deduction'] ?? 0.0));
                            $netPay = (float) ($selectedPayroll['net_pay'] ?? ($grossPay + $selectedOvertimePay - $totalDeductions));
                        ?>
                        <div class="payslip-summary-box">
                            <div class="payslip-summary-header">
                                <div>
                                    <strong><?php echo htmlspecialchars($selectedEmployeeName); ?></strong>
                                    <small>Cutoff: <?php echo htmlspecialchars((string) ($selectedPayroll['cutoff'] ?? $selectedCutoff)); ?></small>
                                </div>
                                <span class="status-pill status-approved">Net pay: ₱<?php echo number_format($netPay, 2); ?></span>
                            </div>

                            <div class="details-grid">
                                <div class="detail-box">
                                    <span>Gross pay</span>
                                    <strong>₱<?php echo number_format($grossPay, 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>Basic salary</span>
                                    <strong>₱<?php echo number_format((float) ($selectedPayroll['base_salary'] ?? 0.0), 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>Benefits / allowance</span>
                                    <strong>₱<?php echo number_format((float) ($selectedPayroll['allowance'] ?? 0.0), 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>Overtime pay</span>
                                    <strong>₱<?php echo number_format($selectedOvertimePay, 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>Overtime hours</span>
                                    <strong><?php echo number_format((float) ($selectedPayroll['overtime_hours'] ?? 0), 2); ?> hrs</strong>
                                </div>
                                <div class="detail-box">
                                    <span>Paid leave days</span>
                                    <strong><?php echo number_format($selectedPaidLeaveDays, 0); ?> days</strong>
                                </div>
                                <div class="detail-box">
                                    <span>Late deduction</span>
                                    <strong>₱<?php echo number_format((float) ($selectedPayroll['late_deduction'] ?? 0.0), 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>Absent deduction</span>
                                    <strong>₱<?php echo number_format((float) ($selectedPayroll['absence_deduction'] ?? 0.0), 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>SSS</span>
                                    <strong>₱<?php echo number_format((float) ($selectedPayroll['sss'] ?? 0.0), 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>PhilHealth</span>
                                    <strong>₱<?php echo number_format((float) ($selectedPayroll['philhealth'] ?? 0.0), 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>Pag-IBIG</span>
                                    <strong>₱<?php echo number_format((float) ($selectedPayroll['pagibig'] ?? 0.0), 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>Total deductions</span>
                                    <strong>₱<?php echo number_format($totalDeductions, 2); ?></strong>
                                </div>
                                <div class="detail-box">
                                    <span>Net pay</span>
                                    <strong>₱<?php echo number_format($netPay, 2); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Edit payslip</h3>
                    </div>

                    <form method="POST" action="admin_payroll_dashboard.php" data-payroll-form>
                        <input type="hidden" name="action" value="save_payroll">
                        <div class="field-row">
                            <div>
                                <label for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id">
                                    <?php foreach ($employees as $person): ?>
                                        <option value="<?php echo (int) ($person['id'] ?? 0); ?>" <?php echo ((int) ($person['id'] ?? 0) === $selectedEmployeeId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($person['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="cutoff">Payroll cutoff</label>
                                <input id="cutoff" name="cutoff" type="date" value="<?php echo htmlspecialchars($selectedCutoff ?: date('Y-m-d')); ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div>
                                <label for="base_salary">Base salary</label>
                                <input id="base_salary" name="base_salary" type="number" min="0" step="0.01" value="<?php echo number_format((float) (($selectedPayroll['base_salary'] ?? 0.0)), 2, '.', ''); ?>">
                            </div>
                            <div>
                                <label for="allowance">Benefits / allowance</label>
                                <input id="allowance" name="allowance" type="number" min="0" step="0.01" value="<?php echo number_format((float) (($selectedPayroll['allowance'] ?? 0.0)), 2, '.', ''); ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div>
                                <label for="late_minutes">Late minutes</label>
                                <input id="late_minutes" name="late_minutes" type="number" min="0" step="1" value="<?php echo (int) (($selectedPayroll['late_minutes'] ?? 0)); ?>">
                            </div>
                            <div>
                                <label for="absent_days">Absent without leave</label>
                                <input id="absent_days" name="absent_days" type="number" min="0" step="1" value="<?php echo (int) (($selectedPayroll['absent_days'] ?? 0)); ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div>
                                <label for="sss">SSS contribution</label>
                                <input id="sss" name="sss" type="number" min="0" step="0.01" value="<?php echo number_format((float) (($selectedPayroll['sss'] ?? 0.0)), 2, '.', ''); ?>">
                            </div>
                            <div>
                                <label for="philhealth">PhilHealth</label>
                                <input id="philhealth" name="philhealth" type="number" min="0" step="0.01" value="<?php echo number_format((float) (($selectedPayroll['philhealth'] ?? 0.0)), 2, '.', ''); ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div>
                                <label for="pagibig">Pag-IBIG</label>
                                <input id="pagibig" name="pagibig" type="number" min="0" step="0.01" value="<?php echo number_format((float) (($selectedPayroll['pagibig'] ?? 0.0)), 2, '.', ''); ?>">
                            </div>
                            <div>
                                <label for="overtime_rate">Overtime rate</label>
                                <input id="overtime_rate" name="overtime_rate" type="number" min="0" step="0.01" value="<?php echo number_format((float) (($selectedPayroll['overtime_rate'] ?? 250.0)), 2, '.', ''); ?>">
                            </div>
                        </div>

                        <div class="field-row">
                            <div>
                                <label for="overtime_hours">Overtime hours</label>
                                <input id="overtime_hours" name="overtime_hours" type="number" min="0" step="0.01" value="<?php echo number_format((float) (($selectedPayroll['overtime_hours'] ?? 0.0)), 2, '.', ''); ?>">
                            </div>
                            <div></div>
                        </div>

                        <div class="field-row">
                            <div>
                                <label>Net payslip</label>
                                <div class="status-pill status-approved" style="display:inline-flex; margin-top:10px;">₱<?php echo number_format((float) (($selectedPayroll['net_pay'] ?? 0.0)), 2); ?></div>
                            </div>
                            <div></div>
                        </div>

                        <div class="action-row" style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                            <button type="submit" class="primary-btn">Save payslip for this cutoff</button>
                            <button type="button" class="mini-btn" id="clearPayslipFormBtn">Clear all</button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const clearButton = document.getElementById('clearPayslipFormBtn');
            const payrollForm = document.querySelector('[data-payroll-form]');
            const employeeSelect = document.getElementById('employee_id');
            const cutoffInput = document.getElementById('cutoff');

            if (clearButton && payrollForm) {
                clearButton.addEventListener('click', () => {
                    const fields = payrollForm.querySelectorAll('input, select');
                    fields.forEach((field) => {
                        if (field.type === 'hidden') {
                            return;
                        }

                        if (field.tagName === 'SELECT') {
                            field.selectedIndex = 0;
                            return;
                        }

                        field.value = '';
                    });
                });
            }

            if (employeeSelect) {
                employeeSelect.addEventListener('change', () => {
                    const params = new URLSearchParams(window.location.search);
                    params.set('employee_id', employeeSelect.value);
                    if (cutoffInput) {
                        params.set('cutoff', cutoffInput.value || '<?php echo htmlspecialchars($selectedCutoff ?: date('Y-m-d')); ?>');
                    }
                    window.location.href = 'admin_payroll_dashboard.php?' + params.toString();
                });
            }

            if (cutoffInput) {
                cutoffInput.addEventListener('change', () => {
                    const params = new URLSearchParams(window.location.search);
                    params.set('cutoff', cutoffInput.value || '<?php echo htmlspecialchars($selectedCutoff ?: date('Y-m-d')); ?>');
                    if (employeeSelect) {
                        params.set('employee_id', employeeSelect.value);
                    }
                    window.location.href = 'admin_payroll_dashboard.php?' + params.toString();
                });
            }

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
