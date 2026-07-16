<?php
$ceoNavItems = [
    "dashboard" => ["Dashboard", "ceo_dashboard.php"],
    "profile" => ["Profile", "profile.php"],
    "reports" => ["Reports", "reports.php"],
    "approvals" => ["Approvals", "approvals.php"],
    "targets" => ["Department Targets", "department_targets.php"],
    "complaints" => ["Complaints", "complaints.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($ceoNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
