<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Finance Officer');

$activePage = "dashboard";

$dailyIncome = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(amount), 0) FROM financial_record WHERE type = 'income' AND record_date = CURDATE()"
))[0];

$dailyExpenses = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(amount), 0) FROM financial_record WHERE type = 'expense' AND record_date = CURDATE()"
))[0];

$outstandingReceivables = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(total_amount), 0) FROM invoice WHERE payment_status != 'paid'"
))[0];

$budgetTotals = mysqli_fetch_assoc(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(amount), 0) AS total_amount, COALESCE(SUM(used_amount), 0) AS total_used
     FROM budget_plan WHERE status = 'approved'"
));
$budgetUsedPercent = $budgetTotals["total_amount"] > 0 ? round(($budgetTotals["total_used"] / $budgetTotals["total_amount"]) * 100, 1) : 0;

$recentRecords = mysqli_query(
    $connection,
    "SELECT record_id, record_date, type, description, amount FROM financial_record ORDER BY record_date DESC, record_id DESC LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Officer Dashboard</title>
  <link rel="stylesheet" href="css/fo_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Finance Officer</h1>
    <p>Income, expenses, payments, receivables, reports, and tax records</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of daily finance activities.</p>
      </section>

      <section class="cards">
        <div class="card"><h3>Daily Income</h3><p class="number">Rs. <?= number_format($dailyIncome, 2) ?></p><p>Income recorded today</p></div>
        <div class="card"><h3>Daily Expenses</h3><p class="number">Rs. <?= number_format($dailyExpenses, 2) ?></p><p>Expenses recorded today</p></div>
        <div class="card"><h3>Receivables</h3><p class="number">Rs. <?= number_format($outstandingReceivables, 2) ?></p><p>Outstanding amount</p></div>
        <div class="card"><h3>Budget Used</h3><p class="number"><?= $budgetUsedPercent ?>%</p><p>Current utilization</p></div>
      </section>

      <section class="panel">
        <h3>Recent Financial Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Amount</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($recentRecords) === 0): ?>
                <tr><td colspan="4">No financial records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($recentRecords)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["record_date"]) ?></td>
                  <td><?= htmlspecialchars(ucfirst($row["type"])) ?></td>
                  <td><?= htmlspecialchars($row["description"] ?? "") ?></td>
                  <td><?= number_format($row["amount"], 2) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
