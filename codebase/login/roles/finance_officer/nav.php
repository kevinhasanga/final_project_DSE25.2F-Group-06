<?php
$foNavItems = [
    "dashboard" => ["Dashboard", "finance_officer_dashboard.php"],
    "income_expenses" => ["Income & Expenses", "income_expenses.php"],
    "supplier_payments" => ["Supplier Payments", "supplier_payments.php"],
    "receivables" => ["Receivables", "receivables.php"],
    "reports" => ["Reports", "report/reports.php"],
    "budget" => ["Budget Utilization", "budget_utilization.php"],
    "reconciliation" => ["Reconciliation", "reconciliation.php"],
];
$navBasePath = $navBasePath ?? "";
?>
<nav class="sidebar no-print">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($foNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= $navBasePath . htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a class="nav-mail" href="<?= $navBasePath ?>../../communications.php">Internal Mail</a>
  <a class="nav-logout" href="<?= $navBasePath ?>../../logout.php">Log out</a>
</nav>
