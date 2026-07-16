<?php
$supervisorNavItems = [
    "dashboard" => ["Dashboard", "supervisor_dashboard.php"],
    "attendance" => ["Attendance", "attendance.php"],
    "leave" => ["Leave Requests", "leave_requests.php"],
    "employees" => ["Employees", "employees.php"],
    "payroll" => ["Payroll", "payroll.php"],
    "performance" => ["Performance", "performance.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($supervisorNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
