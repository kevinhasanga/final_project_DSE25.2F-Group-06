<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Supervisor');

$activePage = "dashboard";

$presentToday = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND clock_in IS NOT NULL"
))[0];

$pendingLeaves = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM leave_request WHERE status = 'pending'"
))[0];

$payrollTotal = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(net_pay), 0) FROM payroll WHERE period = DATE_FORMAT(CURDATE(), '%Y-%m')"
))[0];

$averagePerformance = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(AVG(rating), 0) FROM performance_review"
))[0];
$averagePerformancePercent = round(($averagePerformance / 5) * 100);

$recentAttendance = mysqli_query(
    $connection,
    "SELECT a.attendance_id, e.full_name, a.date, a.clock_in, a.clock_out, a.overtime_hours
     FROM attendance a
     JOIN employee e ON e.employee_id = a.employee_id
     ORDER BY a.date DESC, a.attendance_id DESC
     LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Supervisor Dashboard</title>
  <link rel="stylesheet" href="css/supervisor_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Supervisor</h1>
    <p>Attendance, leave, payroll, salary slips, and employee performance</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of supervisor activities.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Present Today</h3>
          <p class="number"><?= $presentToday ?></p>
          <p>Employees marked present</p>
        </div>
        <div class="card">
          <h3>Leave Requests</h3>
          <p class="number"><?= $pendingLeaves ?></p>
          <p>Waiting for approval</p>
        </div>
        <div class="card">
          <h3>Payroll Total</h3>
          <p class="number">Rs. <?= number_format($payrollTotal, 2) ?></p>
          <p>Current month</p>
        </div>
        <div class="card">
          <h3>Performance</h3>
          <p class="number"><?= $averagePerformancePercent ?>%</p>
          <p>Average score</p>
        </div>
      </section>

      <section class="panel">
        <h3>Recent Attendance Records</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Date</th>
                <th>Clock In</th>
                <th>Clock Out</th>
                <th>Overtime</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($recentAttendance) === 0): ?>
                <tr><td colspan="5">No attendance records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($recentAttendance)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["full_name"]) ?></td>
                  <td><?= htmlspecialchars($row["date"]) ?></td>
                  <td><?= htmlspecialchars($row["clock_in"] ?? "—") ?></td>
                  <td><?= htmlspecialchars($row["clock_out"] ?? "—") ?></td>
                  <td><?= htmlspecialchars($row["overtime_hours"]) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
