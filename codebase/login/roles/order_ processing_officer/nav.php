<?php
$opoNavItems = [
    "dashboard" => ["Dashboard", "order_processing_officer_dashboard.php"],
    "orders" => ["Sales Orders", "sales_orders.php"],
    "stock" => ["Stock Availability", "stock_availability.php"],
    "credit" => ["Credit Approval", "credit_approval.php"],
    "invoices" => ["Invoices", "invoices.php"],
    "reports" => ["Reports", "reports.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($opoNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
