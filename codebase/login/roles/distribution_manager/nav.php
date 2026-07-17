<?php
$dmNavItems = [
    "dashboard" => ["Dashboard", "distribution_manager_dashboard.php"],
    "confirmed" => ["Confirmed Orders", "confirmed_orders.php"],
    "deliveries" => ["Deliveries", "deliveries.php"],
    "delayed" => ["Delayed Deliveries", "delayed_deliveries.php"],
    "reports" => ["Reports", "report/reports.php"],
];
$navBasePath = $navBasePath ?? "";
?>
<nav class="sidebar no-print">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($dmNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= $navBasePath . htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a class="nav-mail" href="<?= $navBasePath ?>../../communications.php">Internal Mail</a>
  <a class="nav-logout" href="<?= $navBasePath ?>../../logout.php">Log out</a>
</nav>
