<?php
$inventoryNavItems = [
    "dashboard" => ["Dashboard", "inventory_manager_dashboard.php"],
    "products" => ["Products", "products.php"],
    "batches" => ["Stock Batches", "stock_batches.php"],
    "movements" => ["Returns & Transfers", "stock_movements.php"],
    "reports" => ["Reports", "reports.php"],
    "alerts" => ["Low Stock Alerts", "low_stock_alerts.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($inventoryNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
