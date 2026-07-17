<?php
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../helpers.php';
require_login('Supervisor', '../../../login.php');

$activePage = "reports";
$navBasePath = "../";

$employees = getAllEmployees($connection);

$reportFrom = $_GET["report_from"] ?? "";
$reportTo = $_GET["report_to"] ?? "";
$reportEmployee = (int) ($_GET["report_employee"] ?? 0);
$report = [];

if ($reportFrom !== "" && $reportTo !== "") {
    $employeesById = [];
    foreach ($employees as $employee) {
        $employeesById[$employee["employee_id"]] = $employee["full_name"];
        $report[$employee["employee_id"]] = [
            "full_name" => $employee["full_name"],
            "present_days" => 0,
            "absent_days" => 0,
            "leave_days" => 0,
        ];
    }

    $presenceSql = "SELECT employee_id,
                            SUM(clock_in IS NOT NULL) AS present_days,
                            SUM(clock_in IS NULL) AS absent_days
                     FROM attendance
                     WHERE date BETWEEN ? AND ?";
    $params = [$reportFrom, $reportTo];
    $types = "ss";
    if ($reportEmployee > 0) {
        $presenceSql .= " AND employee_id = ?";
        $params[] = $reportEmployee;
        $types .= "i";
    }
    $presenceSql .= " GROUP BY employee_id";

    $statement = mysqli_prepare($connection, $presenceSql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $presenceResult = mysqli_stmt_get_result($statement);
    while ($row = mysqli_fetch_assoc($presenceResult)) {
        $employeeId = (int) $row["employee_id"];
        if (!isset($report[$employeeId])) {
            $report[$employeeId] = [
                "full_name" => $employeesById[$employeeId] ?? "Employee #$employeeId",
                "present_days" => 0,
                "absent_days" => 0,
                "leave_days" => 0,
            ];
        }
        $report[$employeeId]["present_days"] = (int) $row["present_days"];
        $report[$employeeId]["absent_days"] = (int) $row["absent_days"];
    }
    mysqli_stmt_close($statement);

    $leaveSql = "SELECT employee_id, start_date, end_date
                 FROM leave_request
                 WHERE status = 'approved' AND start_date <= ? AND end_date >= ?";
    $leaveParams = [$reportTo, $reportFrom];
    $leaveTypes = "ss";
    if ($reportEmployee > 0) {
        $leaveSql .= " AND employee_id = ?";
        $leaveParams[] = $reportEmployee;
        $leaveTypes .= "i";
    }

    $statement = mysqli_prepare($connection, $leaveSql);
    mysqli_stmt_bind_param($statement, $leaveTypes, ...$leaveParams);
    mysqli_stmt_execute($statement);
    $leaveResult = mysqli_stmt_get_result($statement);
    $rangeStart = new DateTime($reportFrom);
    $rangeEnd = new DateTime($reportTo);
    while ($row = mysqli_fetch_assoc($leaveResult)) {
        $employeeId = (int) $row["employee_id"];
        $leaveStart = max(new DateTime($row["start_date"]), $rangeStart);
        $leaveEnd = min(new DateTime($row["end_date"]), $rangeEnd);
        $overlapDays = (int) $leaveStart->diff($leaveEnd)->days + 1;

        if (!isset($report[$employeeId])) {
            $report[$employeeId] = [
                "full_name" => $employeesById[$employeeId] ?? "Employee #$employeeId",
                "present_days" => 0,
                "absent_days" => 0,
                "leave_days" => 0,
            ];
        }
        $report[$employeeId]["leave_days"] += $overlapDays;
    }
    mysqli_stmt_close($statement);

    $report = array_filter($report, function ($row) {
        return $row["present_days"] > 0 || $row["absent_days"] > 0 || $row["leave_days"] > 0;
    });
}

// --- Group and summarize the way a supervisor would actually read it ---

function attendanceTier($presentDays, $absentDays)
{
    $scheduledDays = $presentDays + $absentDays;
    if ($scheduledDays === 0) {
        return "No Data";
    }
    $rate = $presentDays / $scheduledDays * 100;
    if ($rate >= 90) {
        return "Good";
    }
    if ($rate >= 75) {
        return "Watch";
    }
    return "Concern";
}

$attendanceTierOrder = ["Concern", "Watch", "Good", "No Data"];
$reportByTier = [];
$totalPresent = 0;
$totalAbsent = 0;
$totalLeave = 0;
foreach ($report as $row) {
    $tier = attendanceTier($row["present_days"], $row["absent_days"]);
    $scheduledDays = $row["present_days"] + $row["absent_days"];
    $row["rate"] = $scheduledDays > 0 ? round($row["present_days"] / $scheduledDays * 100, 1) : null;
    $row["tier"] = $tier;
    if (!isset($reportByTier[$tier])) {
        $reportByTier[$tier] = ["rows" => [], "count" => 0];
    }
    $reportByTier[$tier]["rows"][] = $row;
    $reportByTier[$tier]["count"]++;
    $totalPresent += $row["present_days"];
    $totalAbsent += $row["absent_days"];
    $totalLeave += $row["leave_days"];
}
foreach ($reportByTier as &$tierGroup) {
    usort($tierGroup["rows"], fn($a, $b) => ($a["rate"] ?? -1) <=> ($b["rate"] ?? -1));
}
unset($tierGroup);
uksort($reportByTier, fn($a, $b) => array_search($a, $attendanceTierOrder) <=> array_search($b, $attendanceTierOrder));
$overallScheduled = $totalPresent + $totalAbsent;
$overallRate = $overallScheduled > 0 ? round($totalPresent / $overallScheduled * 100, 1) : 0;

// --- Printable report header content ---

$reportTitle = "Attendance Report";
$selectedEmployeeName = "All employees";
if ($reportEmployee > 0) {
    foreach ($employees as $employee) {
        if ((int) $employee["employee_id"] === $reportEmployee) {
            $selectedEmployeeName = $employee["full_name"];
            break;
        }
    }
}
$filterParts = ["Employee: " . $selectedEmployeeName];
if ($reportFrom !== "" && $reportTo !== "") {
    $filterParts[] = "Period: " . $reportFrom . " to " . $reportTo;
}
$generatedBy = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "User";
$generatedOn = date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance Report</title>
  <link rel="stylesheet" href="../css/supervisor_style.css?v=<?= filemtime(__DIR__ . '/../css/supervisor_style.css') ?>">
</head>
<body>
  <header class="topbar no-print"><h1>Supervisor</h1><p>Generate attendance reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/../nav.php'; ?>
    <main class="content">
      <section class="page-title no-print"><h2>Attendance Report</h2><p>Generate attendance reports for your team.</p></section>

      <section class="panel no-print">
        <h3>Attendance Report</h3>
        <form method="get" action="reports.php">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportEmployee">Employee (optional)</label>
              <select id="reportEmployee" name="report_employee">
                <option value="0">All employees</option>
                <?php foreach ($employees as $employee): ?>
                  <option value="<?= $employee["employee_id"] ?>" <?= $reportEmployee == $employee["employee_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($employee["full_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="reportFrom">From</label>
              <input type="date" id="reportFrom" name="report_from" value="<?= htmlspecialchars($reportFrom) ?>" required>
            </div>
            <div class="form-group">
              <label for="reportTo">To</label>
              <input type="date" id="reportTo" name="report_to" value="<?= htmlspecialchars($reportTo) ?>" required>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Generate Report</button></div>
        </form>
      </section>

      <?php if ($reportFrom !== "" && $reportTo !== ""): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Attendance Report <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat"><p class="label">Employees</p><p class="value"><?= count($report) ?></p></div>
            <div class="stat <?= $overallRate >= 90 ? "good" : ($overallRate < 75 ? "warning" : "") ?>"><p class="label">Overall Attendance Rate</p><p class="value"><?= $overallRate ?>%</p></div>
            <div class="stat warning"><p class="label">Total Absent Days</p><p class="value"><?= $totalAbsent ?></p></div>
            <div class="stat"><p class="label">Total Leave Days</p><p class="value"><?= $totalLeave ?></p></div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by attendance tier, worst first — Concern (under 75% present) and Watch (75–89%) are the employees worth a conversation before it shows up as a bigger problem.</p>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Employee</th><th>Present Days</th><th>Absent Days</th><th>Leave Days</th><th>Attendance Rate</th></tr></thead>
              <tbody>
                <?php if (empty($reportByTier)): ?>
                  <tr><td colspan="5">No attendance data for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($reportByTier as $tier => $group): ?>
                  <tr class="group-heading"><td colspan="5"><?= htmlspecialchars($tier) ?> — <?= $group["count"] ?> employees</td></tr>
                  <?php foreach ($group["rows"] as $row): ?>
                    <tr>
                      <td><?= htmlspecialchars($row["full_name"]) ?></td>
                      <td><?= $row["present_days"] ?></td>
                      <td><?= $row["absent_days"] ?></td>
                      <td><?= $row["leave_days"] ?></td>
                      <td><?= $row["rate"] !== null ? $row["rate"] . "%" : "—" ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php else: ?>
        <section class="panel"><p style="padding: 14px 20px; color: #7f93b3;">Select a date range to generate the attendance report.</p></section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
