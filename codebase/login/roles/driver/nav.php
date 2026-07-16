<?php
$driverNavItems = [
    "dashboard" => ["Dashboard", "driver_dashboard.php"],
    "deliveries" => ["My Deliveries", "my_deliveries.php"],
    "issues" => ["Delivery Issues", "delivery_issues.php"],
    "proof" => ["Proof of Delivery", "proof_of_delivery.php"],
    "fuel" => ["Fuel Usage", "fuel_usage.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($driverNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
