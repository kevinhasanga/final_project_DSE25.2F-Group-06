<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Supervisor');

$activePage = "attendance";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: attendance.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $attendanceId = (int) ($_POST["attendance_id"] ?? 0);
        $employeeId = (int) $_POST["employee_id"];
        $date = $_POST["date"];
        $clockIn = $_POST["clock_in"] !== "" ? $_POST["clock_in"] : null;
        $clockOut = $_POST["clock_out"] !== "" ? $_POST["clock_out"] : null;
        $overtimeHours = $_POST["overtime_hours"] !== "" ? (float) $_POST["overtime_hours"] : 0;

        if ($attendanceId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE attendance SET employee_id = ?, date = ?, clock_in = ?, clock_out = ?, overtime_hours = ?
                 WHERE attendance_id = ?"
            );
            mysqli_stmt_bind_param($statement, "isssdi", $employeeId, $date, $clockIn, $clockOut, $overtimeHours, $attendanceId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO attendance (employee_id, recorded_by, date, clock_in, clock_out, overtime_hours)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "iisssd", $employeeId, $currentEmployeeId, $date, $clockIn, $clockOut, $overtimeHours);
        }

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Attendance record saved.");
        header("Location: attendance.php");
        exit();
    }

    if ($action === "delete") {
        $attendanceId = (int) $_POST["attendance_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM attendance WHERE attendance_id = ?");
        mysqli_stmt_bind_param($statement, "i", $attendanceId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Attendance record deleted.");
        header("Location: attendance.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM attendance WHERE attendance_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$employees = getAllEmployees($connection);

$perPage = 10;
$totalAttendanceRecords = countRows(
    $connection,
    "SELECT COUNT(*) FROM attendance a JOIN employee e ON e.employee_id = a.employee_id"
);
$totalAttendancePages = max(1, (int) ceil($totalAttendanceRecords / $perPage));
$currentPage = min(getCurrentPage(), $totalAttendancePages);
$offset = ($currentPage - 1) * $perPage;

$attendanceRecords = mysqli_query(
    $connection,
    "SELECT a.attendance_id, a.employee_id, e.full_name, a.date, a.clock_in, a.clock_out, a.overtime_hours
     FROM attendance a
     JOIN employee e ON e.employee_id = a.employee_id
     ORDER BY a.date DESC, a.attendance_id DESC
     LIMIT $perPage OFFSET $offset"
);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance</title>
  <link rel="stylesheet" href="css/supervisor_style.css">
</head>
<body>
  <header class="topbar"><h1>Supervisor</h1><p>Attendance, clock times, overtime, and reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title">
        <h2>Attendance</h2>
        <p>Record daily attendance, clock times, and overtime, and generate attendance reports.</p>
      </section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Attendance" : "Add Attendance" ?></h3>
        <form method="post" action="attendance.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="attendance_id" value="<?= htmlspecialchars($editRecord["attendance_id"] ?? "") ?>">
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
              <label for="date">Date</label>
              <input type="date" id="date" name="date" value="<?= htmlspecialchars($editRecord["date"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="clockIn">Clock In</label>
              <input type="time" id="clockIn" name="clock_in" value="<?= htmlspecialchars($editRecord["clock_in"] ?? "") ?>">
            </div>
            <div class="form-group">
              <label for="clockOut">Clock Out</label>
              <input type="time" id="clockOut" name="clock_out" value="<?= htmlspecialchars($editRecord["clock_out"] ?? "") ?>">
            </div>
            <div class="form-group">
              <label for="overtimeHours">Overtime Hours</label>
              <input type="number" id="overtimeHours" name="overtime_hours" min="0" step="0.25" value="<?= htmlspecialchars($editRecord["overtime_hours"] ?? "0") ?>">
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Attendance</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="attendance.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Attendance Reports</h3>
        <form method="get" action="attendance.php">
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
        <?php if (!empty($report)): ?>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Employee</th><th>Present Days</th><th>Absent Days</th><th>Leave Days</th></tr></thead>
              <tbody>
                <?php foreach ($report as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row["full_name"]) ?></td>
                    <td><?= $row["present_days"] ?></td>
                    <td><?= $row["absent_days"] ?></td>
                    <td><?= $row["leave_days"] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <h3>Attendance Records</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Employee</th><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Overtime</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($attendanceRecords) === 0): ?>
                <tr><td colspan="7">No attendance records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($attendanceRecords)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["full_name"]) ?></td>
                  <td><?= htmlspecialchars($row["date"]) ?></td>
                  <td><?= htmlspecialchars($row["clock_in"] ?? "—") ?></td>
                  <td><?= htmlspecialchars($row["clock_out"] ?? "—") ?></td>
                  <td><?= htmlspecialchars($row["overtime_hours"]) ?></td>
                  <td><span class="status <?= $row["clock_in"] ? "resolved" : "pending" ?>"><?= $row["clock_in"] ? "Present" : "Absent" ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="attendance.php?edit=<?= $row["attendance_id"] ?>">Edit</a>
                      <form method="post" action="attendance.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="attendance_id" value="<?= $row["attendance_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this attendance record?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalAttendanceRecords, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
