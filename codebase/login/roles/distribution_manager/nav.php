<?php
$dmNavItems = [
    "dashboard" => ["Dashboard", "distribution_manager_dashboard.php"],
    "confirmed" => ["Confirmed Orders", "confirmed_orders.php"],
    "deliveries" => ["Deliveries", "deliveries.php"],
    "delayed" => ["Delayed Deliveries", "delayed_deliveries.php"],
    "reports" => ["Reports", "reports.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($dmNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
