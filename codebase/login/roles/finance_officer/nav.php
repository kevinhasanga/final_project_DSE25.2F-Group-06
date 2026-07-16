<?php
$foNavItems = [
    "dashboard" => ["Dashboard", "finance_officer_dashboard.php"],
    "income_expenses" => ["Income & Expenses", "income_expenses.php"],
    "supplier_payments" => ["Supplier Payments", "supplier_payments.php"],
    "receivables" => ["Receivables", "receivables.php"],
    "reports" => ["Reports", "reports.php"],
    "budget" => ["Budget Utilization", "budget_utilization.php"],
    "reconciliation" => ["Reconciliation", "reconciliation.php"],
];
?>
<nav class="sidebar">
  <h2><?= htmlspecialchars($_SESSION["full_name"] ?? $_SESSION["username"] ?? "User") ?></h2>
  <?php foreach ($foNavItems as $key => $item): ?>
    <a class="<?= ($activePage ?? "") === $key ? "active" : "" ?>" href="<?= htmlspecialchars($item[1]) ?>"><?= htmlspecialchars($item[0]) ?></a>
  <?php endforeach; ?>
  <a href="../../communications.php">Internal Mail</a>
  <a href="../../logout.php">Log out</a>
</nav>
