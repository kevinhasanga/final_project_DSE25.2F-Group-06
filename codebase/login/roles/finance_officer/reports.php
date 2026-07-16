<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Finance Officer');

$activePage = "reports";

$reportType = $_GET["report_type"] ?? "";
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";
$summaryGrouping = $_GET["grouping"] ?? "daily";

$profitLossRows = [];
$totalIncome = 0;
$totalExpense = 0;
$cashFlowRows = [];
$summaryRows = [];

if ($reportType === "profit_loss" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT type, category, description, amount FROM financial_record WHERE record_date BETWEEN ? AND ? ORDER BY record_date DESC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($statement);
    $profitLossRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);

    foreach ($profitLossRows as $row) {
        if ($row["type"] === "income") {
            $totalIncome += $row["amount"];
        } else {
            $totalExpense += $row["amount"];
        }
    }
}

if ($reportType === "cash_flow" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT record_date,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS cash_in,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS cash_out
         FROM financial_record
         WHERE record_date BETWEEN ? AND ?
         GROUP BY record_date
         ORDER BY record_date ASC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($statement);
    $daily = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);

    $runningBalance = 0;
    foreach ($daily as $row) {
        $runningBalance += $row["cash_in"] - $row["cash_out"];
        $cashFlowRows[] = [
            "date" => $row["record_date"],
            "cash_in" => $row["cash_in"],
            "cash_out" => $row["cash_out"],
            "balance" => $runningBalance,
        ];
    }
}

if ($reportType === "summary" && $fromDate !== "" && $toDate !== "") {
    $format = $summaryGrouping === "yearly" ? "%Y" : ($summaryGrouping === "monthly" ? "%Y-%m" : "%Y-%m-%d");
    $statement = mysqli_prepare(
        $connection,
        "SELECT DATE_FORMAT(record_date, '$format') AS period,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense
         FROM financial_record
         WHERE record_date BETWEEN ? AND ?
         GROUP BY period
         ORDER BY period ASC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($statement);
    $summaryRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>
  <link rel="stylesheet" href="css/fo_style.css">
</head>
<body>
  <header class="topbar"><h1>Finance Officer</h1><p>Profit and loss, cash flow, and financial summaries</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Reports</h2><p>Generate profit and loss statements, cash flow reports, and financial summaries.</p></section>

      <section class="panel">
        <h3>Generate Report</h3>
        <form method="get" action="reports.php">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportType">Report Type</label>
              <select id="reportType" name="report_type" required>
                <option value="">Select type</option>
                <option value="profit_loss" <?= $reportType === "profit_loss" ? "selected" : "" ?>>Profit &amp; Loss</option>
                <option value="cash_flow" <?= $reportType === "cash_flow" ? "selected" : "" ?>>Cash Flow</option>
                <option value="summary" <?= $reportType === "summary" ? "selected" : "" ?>>Financial Summary</option>
              </select>
            </div>
            <div class="form-group">
              <label for="grouping">Summary Grouping (Summary report)</label>
              <select id="grouping" name="grouping">
                <option value="daily" <?= $summaryGrouping === "daily" ? "selected" : "" ?>>Daily</option>
                <option value="monthly" <?= $summaryGrouping === "monthly" ? "selected" : "" ?>>Monthly</option>
                <option value="yearly" <?= $summaryGrouping === "yearly" ? "selected" : "" ?>>Yearly</option>
              </select>
            </div>
            <div class="form-group">
              <label for="fromDate">From Date</label>
              <input type="date" id="fromDate" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" required>
            </div>
            <div class="form-group">
              <label for="toDate">To Date</label>
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>" required>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Generate</button></div>
        </form>
      </section>

      <?php if ($reportType === "profit_loss"): ?>
        <section class="cards">
          <div class="card"><h3>Total Income</h3><p class="number"><?= number_format($totalIncome, 2) ?></p><p>For selected period</p></div>
          <div class="card"><h3>Total Expenses</h3><p class="number"><?= number_format($totalExpense, 2) ?></p><p>For selected period</p></div>
          <div class="card"><h3>Net Profit</h3><p class="number"><?= number_format($totalIncome - $totalExpense, 2) ?></p><p>Final result</p></div>
        </section>
        <section class="panel"><h3>Statement Details</h3><div class="table-wrapper"><table>
          <thead><tr><th>Type</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead>
          <tbody>
            <?php if (empty($profitLossRows)): ?><tr><td colspan="4">No records for this period.</td></tr><?php endif; ?>
            <?php foreach ($profitLossRows as $row): ?>
              <tr>
                <td><span class="status <?= $row["type"] === "income" ? "resolved" : "pending" ?>"><?= htmlspecialchars(ucfirst($row["type"])) ?></span></td>
                <td><?= htmlspecialchars($row["category"] ?? "") ?></td>
                <td><?= htmlspecialchars($row["description"] ?? "") ?></td>
                <td><?= number_format($row["amount"], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "cash_flow"): ?>
        <section class="panel"><h3>Cash Flow Details</h3><div class="table-wrapper"><table>
          <thead><tr><th>Date</th><th>Cash In</th><th>Cash Out</th><th>Balance</th></tr></thead>
          <tbody>
            <?php if (empty($cashFlowRows)): ?><tr><td colspan="4">No cash flow data for this period.</td></tr><?php endif; ?>
            <?php foreach ($cashFlowRows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["date"]) ?></td>
                <td><?= number_format($row["cash_in"], 2) ?></td>
                <td><?= number_format($row["cash_out"], 2) ?></td>
                <td><?= number_format($row["balance"], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "summary"): ?>
        <section class="panel"><h3>Financial Summary (<?= htmlspecialchars(ucfirst($summaryGrouping)) ?>)</h3><div class="table-wrapper"><table>
          <thead><tr><th>Period</th><th>Income</th><th>Expense</th><th>Net</th></tr></thead>
          <tbody>
            <?php if (empty($summaryRows)): ?><tr><td colspan="4">No records for this period.</td></tr><?php endif; ?>
            <?php foreach ($summaryRows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["period"]) ?></td>
                <td><?= number_format($row["income"], 2) ?></td>
                <td><?= number_format($row["expense"], 2) ?></td>
                <td><?= number_format($row["income"] - $row["expense"], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
