<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Supervisor');

$activePage = "payroll";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: payroll.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $payrollId = (int) ($_POST["payroll_id"] ?? 0);
        $employeeId = (int) $_POST["employee_id"];
        $period = $_POST["period"];
        $baseSalary = (float) $_POST["base_salary"];
        $overtimePay = (float) ($_POST["overtime_pay"] ?: 0);
        $deductions = (float) ($_POST["deductions"] ?: 0);
        $netPay = $baseSalary + $overtimePay - $deductions;

        if ($payrollId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE payroll SET employee_id = ?, period = ?, base_salary = ?, overtime_pay = ?, deductions = ?, net_pay = ?
                 WHERE payroll_id = ?"
            );
            mysqli_stmt_bind_param($statement, "isdddi", $employeeId, $period, $baseSalary, $overtimePay, $deductions, $netPay, $payrollId);
            mysqli_stmt_execute($statement);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO payroll (employee_id, generated_by, period, base_salary, overtime_pay, deductions, net_pay, generated_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            mysqli_stmt_bind_param($statement, "iisdddd", $employeeId, $currentEmployeeId, $period, $baseSalary, $overtimePay, $deductions, $netPay);
            mysqli_stmt_execute($statement);
        }

        mysqli_stmt_close($statement);
        setFlash("Payroll record saved.");
        header("Location: payroll.php");
        exit();
    }

    if ($action === "delete") {
        $payrollId = (int) $_POST["payroll_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM payroll WHERE payroll_id = ?");
        mysqli_stmt_bind_param($statement, "i", $payrollId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Payroll record deleted.");
        header("Location: payroll.php");
        exit();
    }
}

$employees = getAllEmployees($connection);

if (($_GET["view"] ?? "") === "slip" && isset($_GET["id"])) {
    $slipId = (int) $_GET["id"];
    $statement = mysqli_prepare(
        $connection,
        "SELECT p.*, e.full_name, e.job_title
         FROM payroll p
         JOIN employee e ON e.employee_id = p.employee_id
         WHERE p.payroll_id = ?"
    );
    mysqli_stmt_bind_param($statement, "i", $slipId);
    mysqli_stmt_execute($statement);
    $slip = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM payroll WHERE payroll_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$perPage = 10;
$totalPayrollRecords = countRows(
    $connection,
    "SELECT COUNT(*) FROM payroll p JOIN employee e ON e.employee_id = p.employee_id"
);
$totalPayrollPages = max(1, (int) ceil($totalPayrollRecords / $perPage));
$currentPage = min(getCurrentPage(), $totalPayrollPages);
$offset = ($currentPage - 1) * $perPage;

$payrollRecords = mysqli_query(
    $connection,
    "SELECT p.payroll_id, p.employee_id, e.full_name, p.period, p.base_salary, p.overtime_pay, p.deductions, p.net_pay
     FROM payroll p
     JOIN employee e ON e.employee_id = p.employee_id
     ORDER BY p.generated_date DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payroll</title>
  <link rel="stylesheet" href="css/supervisor_style.css">
  <?php if (isset($slip)): ?>
  <style>@media print { .sidebar, .topbar, .no-print { display: none; } .content { margin-left: 0; } }</style>
  <?php endif; ?>
</head>
<body>
  <header class="topbar"><h1>Supervisor</h1><p>Calculate salaries, prepare payroll sheets, and generate salary slips</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">

      <?php if (isset($slip)): ?>
        <?php if (!$slip): ?>
          <section class="page-title"><h2>Salary Slip</h2><p>Record not found.</p></section>
          <a class="btn secondary no-print" href="payroll.php">Back to Payroll</a>
        <?php else: ?>
          <section class="page-title"><h2>Salary Slip</h2><p><?= htmlspecialchars($slip["full_name"]) ?> — <?= htmlspecialchars($slip["period"]) ?></p></section>
          <section class="panel">
            <h3>Slip Details</h3>
            <div class="table-wrapper">
              <table>
                <tbody>
                  <tr><th>Employee</th><td><?= htmlspecialchars($slip["full_name"]) ?></td></tr>
                  <tr><th>Job Title</th><td><?= htmlspecialchars($slip["job_title"]) ?></td></tr>
                  <tr><th>Period</th><td><?= htmlspecialchars($slip["period"]) ?></td></tr>
                  <tr><th>Base Salary</th><td><?= number_format($slip["base_salary"], 2) ?></td></tr>
                  <tr><th>Overtime Pay</th><td><?= number_format($slip["overtime_pay"], 2) ?></td></tr>
                  <tr><th>Deductions</th><td><?= number_format($slip["deductions"], 2) ?></td></tr>
                  <tr><th>Net Pay</th><td><strong><?= number_format($slip["net_pay"], 2) ?></strong></td></tr>
                </tbody>
              </table>
            </div>
          </section>
          <div class="button-row no-print">
            <button class="btn" type="button" onclick="window.print();">Print</button>
            <a class="btn secondary" href="payroll.php">Back to Payroll</a>
          </div>
        <?php endif; ?>
      <?php else: ?>

      <section class="page-title"><h2>Payroll</h2><p>Calculate salaries, maintain the payroll sheet, and view salary slips.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Payroll" : "Calculate Payroll" ?></h3>
        <form method="post" action="payroll.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="payroll_id" value="<?= htmlspecialchars($editRecord["payroll_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="employeeId">Employee</label>
              <select id="employeeId" name="employee_id" required>
                <option value="">Select employee</option>
                <?php foreach ($employees as $employee): ?>
                  <option value="<?= $employee["employee_id"] ?>" <?= ($editRecord["employee_id"] ?? null) == $employee["employee_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($employee["full_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="period">Period</label>
              <input type="month" id="period" name="period" value="<?= htmlspecialchars($editRecord["period"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="baseSalary">Base Salary</label>
              <input type="number" id="baseSalary" name="base_salary" min="0" step="0.01" value="<?= htmlspecialchars($editRecord["base_salary"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="overtimePay">Overtime Pay</label>
              <input type="number" id="overtimePay" name="overtime_pay" min="0" step="0.01" value="<?= htmlspecialchars($editRecord["overtime_pay"] ?? "0") ?>">
            </div>
            <div class="form-group">
              <label for="deductions">Deductions</label>
              <input type="number" id="deductions" name="deductions" min="0" step="0.01" value="<?= htmlspecialchars($editRecord["deductions"] ?? "0") ?>">
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Net pay is calculated automatically as base salary + overtime pay − deductions.</p>
          <div class="button-row">
            <button class="btn" type="submit">Save Payroll</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="payroll.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Payroll Sheet</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Employee</th><th>Period</th><th>Basic</th><th>Overtime</th><th>Deductions</th><th>Net Pay</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($payrollRecords) === 0): ?>
                <tr><td colspan="7">No payroll records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($payrollRecords)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["full_name"]) ?></td>
                  <td><?= htmlspecialchars($row["period"]) ?></td>
                  <td><?= number_format($row["base_salary"], 2) ?></td>
                  <td><?= number_format($row["overtime_pay"], 2) ?></td>
                  <td><?= number_format($row["deductions"], 2) ?></td>
                  <td><?= number_format($row["net_pay"], 2) ?></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="payroll.php?view=slip&id=<?= $row["payroll_id"] ?>">View Slip</a>
                      <a class="btn secondary" href="payroll.php?edit=<?= $row["payroll_id"] ?>">Edit</a>
                      <form method="post" action="payroll.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="payroll_id" value="<?= $row["payroll_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this payroll record?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalPayrollRecords, $perPage); ?>
      </section>

      <?php endif; ?>
    </main>
  </div>
</body>
</html>
