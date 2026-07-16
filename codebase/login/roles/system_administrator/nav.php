<?php
$saNavItems = [
    "dashboard" => ["Dashboard", "system_administrator_dashboard.php"],
    "users" => ["Users", "users.php"],
    "privileges" => ["Access Privileges", "access_privileges.php"],
    "settings" => ["Settings", "settings.php"],
    "backups" => ["Backups", "backups.php"],
    "errors" => ["System Errors", "system_errors.php"],
    "reports" => ["Reports", "reports.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($saNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
