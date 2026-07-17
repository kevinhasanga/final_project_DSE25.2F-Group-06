<?php
$supervisorNavItems = [
    "dashboard" => ["Dashboard", "supervisor_dashboard.php"],
    "attendance" => ["Attendance", "attendance.php"],
    "reports" => ["Reports", "report/reports.php"],
    "leave" => ["Leave Requests", "leave_requests.php"],
    "employees" => ["Employees", "employees.php"],
    "payroll" => ["Payroll", "payroll.php"],
    "performance" => ["Performance", "performance.php"],
];
$navBasePath = $navBasePath ?? "";
?>
<nav class="sidebar no-print">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($supervisorNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= $navBasePath . htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a class="nav-mail" href="<?= $navBasePath ?>../../communications.php">Internal Mail</a>
  <a class="nav-logout" href="<?= $navBasePath ?>../../logout.php">Log out</a>
</nav>
