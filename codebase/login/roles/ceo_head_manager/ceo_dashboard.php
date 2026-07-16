<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('CEO');

$activePage = "dashboard";

$salesRevenue = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(total_amount), 0) FROM sales_order
     WHERE status != 'cancelled' AND DATE_FORMAT(order_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"
))[0];

$inventoryValue = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(sb.current_quantity * p.selling_price), 0)
     FROM stock_batch sb JOIN product p ON p.product_id = sb.product_id
     WHERE sb.status = 'active'"
))[0];

$thisMonthRevenue = $salesRevenue;
$lastMonthRevenue = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(total_amount), 0) FROM sales_order
     WHERE status != 'cancelled' AND DATE_FORMAT(order_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')"
))[0];
$profitGrowth = $lastMonthRevenue > 0 ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0;

$pendingApprovals = 0;
foreach (["budget_plan" => "status", "purchase_order" => "approval_status", "discount_policy" => "status", "expansion_plan" => "status"] as $table => $column) {
    $pendingApprovals += (int) mysqli_fetch_row(mysqli_query(
        $connection,
        "SELECT COUNT(*) FROM $table WHERE $column = 'pending'"
    ))[0];
}

$businessSummary = [
    ["Sales Revenue (this month)", "Rs. " . number_format($salesRevenue, 2), $salesRevenue > 0 ? "resolved" : "progress"],
    ["Inventory Value", "Rs. " . number_format($inventoryValue, 2), "resolved"],
    ["Profit Growth", $profitGrowth . "%", $profitGrowth >= 0 ? "resolved" : "pending"],
    ["Pending Approvals", $pendingApprovals, $pendingApprovals > 0 ? "pending" : "resolved"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>CEO Dashboard</title><link rel="stylesheet" href="css/ceo_style.css"></head>
<body>
  <header class="topbar"><h1>CEO / Head Manager</h1><p>Centralized business dashboard and approvals</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Centralized Business Dashboard</h2><p>Overview of sales, inventory, finance, delivery, and system activities.</p></section>
      <section class="cards">
        <div class="card"><h3>Sales Revenue</h3><p class="number">Rs. <?= number_format($salesRevenue, 2) ?></p><p>Current period</p></div>
        <div class="card"><h3>Inventory Value</h3><p class="number">Rs. <?= number_format($inventoryValue, 2) ?></p><p>Total stock value</p></div>
        <div class="card"><h3>Revenue Growth</h3><p class="number"><?= $profitGrowth ?>%</p><p>Compared to last month</p></div>
        <div class="card"><h3>Pending Approvals</h3><p class="number"><?= $pendingApprovals ?></p><p>Need decision</p></div>
      </section>
      <section class="panel">
        <h3>Business Summary</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Area</th><th>Current Value</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($businessSummary as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row[0]) ?></td>
                  <td><?= htmlspecialchars((string) $row[1]) ?></td>
                  <td><span class="status <?= $row[2] ?>"><?= $row[2] === "resolved" ? "Healthy" : ($row[2] === "pending" ? "Needs Attention" : "Watch") ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
