<?php
$ceoNavItems = [
    "dashboard" => ["Dashboard", "ceo_dashboard.php"],
    "profile" => ["Profile", "profile.php"],
    "reports" => ["Reports", "report/reports.php"],
    "approvals" => ["Approvals", "approvals.php"],
    "targets" => ["Department Targets", "department_targets.php"],
    "complaints" => ["Complaints", "complaints.php"],
];
$navBasePath = $navBasePath ?? "";
?>
<nav class="sidebar no-print">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($ceoNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= $navBasePath . htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a class="nav-mail" href="<?= $navBasePath ?>../../communications.php">Internal Mail</a>
  <a class="nav-logout" href="<?= $navBasePath ?>../../logout.php">Log out</a>
</nav>
