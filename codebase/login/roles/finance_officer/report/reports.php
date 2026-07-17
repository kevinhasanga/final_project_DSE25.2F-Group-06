<?php
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../helpers.php';
require_login('Finance Officer', '../../../login.php');

$activePage = "reports";
$navBasePath = "../";

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
        "SELECT type, category, description, amount FROM financial_record WHERE record_date BETWEEN ? AND ? ORDER BY type, category, record_date DESC"
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
            "net" => $row["cash_in"] - $row["cash_out"],
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

// --- Group and summarize each report the way a manager would actually read it ---

// Profit & Loss: nested grouping — type, then category within type, so it reads like a real P&L statement.
$plByType = ["income" => ["total" => 0, "categories" => []], "expense" => ["total" => 0, "categories" => []]];
foreach ($profitLossRows as $row) {
    $type = $row["type"] === "income" ? "income" : "expense";
    $cat = ($row["category"] ?? "") !== "" ? $row["category"] : "Uncategorized";
    if (!isset($plByType[$type]["categories"][$cat])) {
        $plByType[$type]["categories"][$cat] = ["rows" => [], "amount" => 0];
    }
    $plByType[$type]["categories"][$cat]["rows"][] = $row;
    $plByType[$type]["categories"][$cat]["amount"] += $row["amount"];
    $plByType[$type]["total"] += $row["amount"];
}
foreach ($plByType as &$typeGroup) {
    uasort($typeGroup["categories"], fn($a, $b) => $b["amount"] <=> $a["amount"]);
}
unset($typeGroup);

// Cash Flow: flag any day the running balance dips negative — that's the day spending outran what came in.
$cashTotalIn = array_sum(array_column($cashFlowRows, "cash_in"));
$cashTotalOut = array_sum(array_column($cashFlowRows, "cash_out"));
$cashEndingBalance = !empty($cashFlowRows) ? end($cashFlowRows)["balance"] : 0;
$deficitDays = count(array_filter($cashFlowRows, fn($r) => $r["balance"] < 0));

// Financial Summary: keep the period breakdown, add grand totals and the standout period.
$summaryTotalIncome = array_sum(array_column($summaryRows, "income"));
$summaryTotalExpense = array_sum(array_column($summaryRows, "expense"));
$summaryBestPeriod = null;
foreach ($summaryRows as $row) {
    if ($summaryBestPeriod === null || $row["income"] > $summaryBestPeriod["income"]) {
        $summaryBestPeriod = $row;
    }
}

// --- Printable report header content ---

$reportLabels = [
    "profit_loss" => "Profit & Loss",
    "cash_flow" => "Cash Flow",
    "summary" => "Financial Summary",
];
$reportTitle = $reportLabels[$reportType] ?? "";
$filterParts = [];
if ($fromDate !== "" && $toDate !== "") {
    $filterParts[] = "Period: " . $fromDate . " to " . $toDate;
}
if ($reportType === "summary") {
    $filterParts[] = "Grouping: " . ucfirst($summaryGrouping);
}
$generatedBy = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "User";
$generatedOn = date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>
  <link rel="stylesheet" href="../css/fo_style.css?v=<?= filemtime(__DIR__ . '/../css/fo_style.css') ?>">
</head>
<body>
  <header class="topbar no-print"><h1>Finance Officer</h1><p>Profit and loss, cash flow, and financial summaries</p></header>
  <div class="layout">
    <?php include __DIR__ . '/../nav.php'; ?>
    <main class="content">
      <section class="page-title no-print"><h2>Reports</h2><p>Generate profit and loss statements, cash flow reports, and financial summaries.</p></section>

      <section class="panel no-print">
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
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Statement Details <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat good"><p class="label">Total Income</p><p class="value"><?= number_format($totalIncome, 2) ?></p></div>
            <div class="stat warning"><p class="label">Total Expenses</p><p class="value"><?= number_format($totalExpense, 2) ?></p></div>
            <div class="stat <?= ($totalIncome - $totalExpense) >= 0 ? "good" : "warning" ?>"><p class="label">Net Profit</p><p class="value"><?= number_format($totalIncome - $totalExpense, 2) ?></p></div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped as a statement — Income then Expense, each broken down by category — instead of one mixed chronological list.</p>
          <div class="table-wrapper"><table>
            <thead><tr><th>Description</th><th>Amount</th></tr></thead>
            <tbody>
              <?php if (empty($profitLossRows)): ?><tr><td colspan="2">No records for this period.</td></tr><?php endif; ?>
              <?php foreach (["income" => "Income", "expense" => "Expense"] as $typeKey => $typeLabel): ?>
                <?php if (empty($plByType[$typeKey]["categories"])) continue; ?>
                <tr class="group-heading"><td colspan="2"><?= $typeLabel ?> — total <?= number_format($plByType[$typeKey]["total"], 2) ?></td></tr>
                <?php foreach ($plByType[$typeKey]["categories"] as $cat => $group): ?>
                  <tr class="subgroup-heading"><td colspan="2"><?= htmlspecialchars($cat) ?> — <?= number_format($group["amount"], 2) ?></td></tr>
                  <?php foreach ($group["rows"] as $row): ?>
                    <tr><td><?= htmlspecialchars($row["description"] ?? "") ?></td><td><?= number_format($row["amount"], 2) ?></td></tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
            <?php if (!empty($profitLossRows)): ?>
              <tfoot><tr class="grand-total"><td>Net Profit</td><td><?= number_format($totalIncome - $totalExpense, 2) ?></td></tr></tfoot>
            <?php endif; ?>
          </table></div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "cash_flow"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Cash Flow Details <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat good"><p class="label">Total Cash In</p><p class="value"><?= number_format($cashTotalIn, 2) ?></p></div>
            <div class="stat warning"><p class="label">Total Cash Out</p><p class="value"><?= number_format($cashTotalOut, 2) ?></p></div>
            <div class="stat <?= $cashEndingBalance >= 0 ? "good" : "warning" ?>"><p class="label">Ending Balance</p><p class="value"><?= number_format($cashEndingBalance, 2) ?></p></div>
            <div class="stat <?= $deficitDays > 0 ? "warning" : "" ?>"><p class="label">Deficit Days</p><p class="value"><?= $deficitDays ?></p></div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Running balance day by day — Deficit Days counts how many days the balance dipped below zero, i.e. spending outran what came in up to that point.</p>
          <div class="table-wrapper"><table>
            <thead><tr><th>Date</th><th>Cash In</th><th>Cash Out</th><th>Daily Net</th><th>Balance</th></tr></thead>
            <tbody>
              <?php if (empty($cashFlowRows)): ?><tr><td colspan="5">No cash flow data for this period.</td></tr><?php endif; ?>
              <?php foreach ($cashFlowRows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row["date"]) ?></td>
                  <td><?= number_format($row["cash_in"], 2) ?></td>
                  <td><?= number_format($row["cash_out"], 2) ?></td>
                  <td><?= number_format($row["net"], 2) ?></td>
                  <td><?= $row["balance"] < 0 ? '<span class="status pending">' . number_format($row["balance"], 2) . '</span>' : number_format($row["balance"], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <?php if (!empty($cashFlowRows)): ?>
              <tfoot><tr class="grand-total"><td colspan="4">Ending Balance</td><td><?= number_format($cashEndingBalance, 2) ?></td></tr></tfoot>
            <?php endif; ?>
          </table></div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "summary"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Financial Summary (<?= htmlspecialchars(ucfirst($summaryGrouping)) ?>) <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat good"><p class="label">Total Income</p><p class="value"><?= number_format($summaryTotalIncome, 2) ?></p></div>
            <div class="stat warning"><p class="label">Total Expense</p><p class="value"><?= number_format($summaryTotalExpense, 2) ?></p></div>
            <div class="stat <?= ($summaryTotalIncome - $summaryTotalExpense) >= 0 ? "good" : "warning" ?>"><p class="label">Net</p><p class="value"><?= number_format($summaryTotalIncome - $summaryTotalExpense, 2) ?></p></div>
            <div class="stat"><p class="label">Best Period</p><p class="value" style="font-size: 15px;"><?= $summaryBestPeriod ? htmlspecialchars($summaryBestPeriod["period"]) : "—" ?></p></div>
          </div>
          <div class="table-wrapper"><table>
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
            <?php if (!empty($summaryRows)): ?>
              <tfoot><tr class="grand-total"><td>Grand Total</td><td><?= number_format($summaryTotalIncome, 2) ?></td><td><?= number_format($summaryTotalExpense, 2) ?></td><td><?= number_format($summaryTotalIncome - $summaryTotalExpense, 2) ?></td></tr></tfoot>
            <?php endif; ?>
          </table></div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
