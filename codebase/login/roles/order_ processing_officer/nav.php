<?php
$opoNavItems = [
    "dashboard" => ["Dashboard", "order_processing_officer_dashboard.php"],
    "orders" => ["Sales Orders", "sales_orders.php"],
    "stock" => ["Stock Availability", "stock_availability.php"],
    "credit" => ["Credit Approval", "credit_approval.php"],
    "invoices" => ["Invoices", "invoices.php"],
    "reports" => ["Reports", "report/reports.php"],
];
$navBasePath = $navBasePath ?? "";
?>
<nav class="sidebar no-print">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($opoNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= $navBasePath . htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a class="nav-mail" href="<?= $navBasePath ?>../../communications.php">Internal Mail</a>
  <a class="nav-logout" href="<?= $navBasePath ?>../../logout.php">Log out</a>
</nav>
