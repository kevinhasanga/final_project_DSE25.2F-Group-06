<?php
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../helpers.php';
require_login('CEO', '../../../login.php');

$activePage = "reports";
$navBasePath = "../";
$printView = ($_GET["print"] ?? "") === "1";

$reportType = $_GET["report_type"] ?? "";
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";
$category = trim($_GET["category"] ?? "");

$salesRows = [];
$inventoryRows = [];
$movementRows = [];
$deliveryRows = [];
$profitLossRows = [];
$revenueGrowthRows = [];
$employeeRows = [];
$strategicSummary = null;
$auditRows = [];

if ($reportType === "sales" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT DATE(so.order_date) AS sales_date, COUNT(DISTINCT so.order_id) AS order_count,
                   COALESCE(SUM(so.total_amount), 0) AS revenue
            FROM sales_order so";
    $params = [$fromDate, $toDate];
    $types = "ss";
    if ($category !== "") {
        $sql .= " JOIN order_item oi ON oi.order_id = so.order_id JOIN product p ON p.product_id = oi.product_id";
    }
    $sql .= " WHERE so.status != 'cancelled' AND DATE(so.order_date) BETWEEN ? AND ?";
    if ($category !== "") {
        $sql .= " AND p.category LIKE ?";
        $params[] = "%$category%";
        $types .= "s";
    }
    $sql .= " GROUP BY DATE(so.order_date) ORDER BY sales_date ASC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $salesRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "inventory") {
    $sql = "SELECT p.product_id, p.product_name, p.category, p.selling_price,
                   COALESCE(SUM(sb.current_quantity), 0) AS quantity
            FROM product p
            LEFT JOIN stock_batch sb ON sb.product_id = p.product_id AND sb.status = 'active'";
    $params = [];
    $types = "";
    if ($category !== "") {
        $sql .= " WHERE p.category LIKE ?";
        $params[] = "%$category%";
        $types = "s";
    }
    $sql .= " GROUP BY p.product_id, p.product_name, p.category, p.selling_price ORDER BY p.category, p.product_name";
    $statement = mysqli_prepare($connection, $sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }
    mysqli_stmt_execute($statement);
    $inventoryRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "stock_movement" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT sm.movement_id, p.product_name, p.selling_price, sm.movement_type, sm.quantity, sm.movement_date
         FROM stock_movement sm JOIN product p ON p.product_id = sm.product_id
         WHERE sm.movement_date BETWEEN ? AND ?
         ORDER BY sm.movement_type, sm.movement_date DESC"
    );
    $toDateEnd = $toDate . " 23:59:59";
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDateEnd);
    mysqli_stmt_execute($statement);
    $movementRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "delivery" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT d.order_id, e.employee_id AS driver_id, e.full_name AS driver_name, d.scheduled_date, d.status, d.transport_cost
         FROM delivery d JOIN employee e ON e.employee_id = d.driver_id
         WHERE d.scheduled_date BETWEEN ? AND ?
         ORDER BY d.scheduled_date DESC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($statement);
    $deliveryRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "profit_loss" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT type, category, description, amount, record_date
         FROM financial_record
         WHERE record_date BETWEEN ? AND ?
         ORDER BY type, category, record_date DESC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($statement);
    $profitLossRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "revenue_growth") {
    $year = (int) ($_GET["year"] ?? date("Y"));
    $statement = mysqli_prepare(
        $connection,
        "SELECT DATE_FORMAT(order_date, '%Y-%m') AS period, COALESCE(SUM(total_amount), 0) AS revenue
         FROM sales_order
         WHERE status != 'cancelled' AND YEAR(order_date) = ?
         GROUP BY DATE_FORMAT(order_date, '%Y-%m')
         ORDER BY period ASC"
    );
    mysqli_stmt_bind_param($statement, "i", $year);
    mysqli_stmt_execute($statement);
    $monthly = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);

    $previous = null;
    foreach ($monthly as $row) {
        $revenue = (float) $row["revenue"];
        $growthAmount = $previous !== null ? $revenue - $previous : null;
        $growthPercent = ($previous !== null && $previous > 0) ? round(($growthAmount / $previous) * 100, 1) : null;
        $revenueGrowthRows[] = [
            "period" => $row["period"],
            "revenue" => $revenue,
            "previous" => $previous,
            "growth_amount" => $growthAmount,
            "growth_percent" => $growthPercent,
        ];
        $previous = $revenue;
    }
}

if ($reportType === "employee") {
    $sql = "SELECT pr.performance_id, e.full_name, e.job_title, pr.rating, pr.status, pr.review_date
            FROM performance_review pr JOIN employee e ON e.employee_id = pr.employee_id
            WHERE 1 = 1";
    $params = [];
    $types = "";
    if ($category !== "") {
        $sql .= " AND e.job_title LIKE ?";
        $params[] = "%$category%";
        $types .= "s";
    }
    $ratingLevel = $_GET["rating_level"] ?? "";
    if ($ratingLevel !== "") {
        $sql .= " AND pr.status = ?";
        $params[] = $ratingLevel;
        $types .= "s";
    }
    $sql .= " ORDER BY pr.review_date DESC";
    $statement = mysqli_prepare($connection, $sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }
    mysqli_stmt_execute($statement);
    $employeeRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "strategic" && $fromDate !== "" && $toDate !== "") {
    $revenueStatement = mysqli_prepare($connection, "SELECT COALESCE(SUM(total_amount), 0) FROM sales_order WHERE status != 'cancelled' AND DATE(order_date) BETWEEN ? AND ?");
    mysqli_stmt_bind_param($revenueStatement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($revenueStatement);
    $revenue = (float) mysqli_fetch_row(mysqli_stmt_get_result($revenueStatement))[0];
    mysqli_stmt_close($revenueStatement);

    $expenseStatement = mysqli_prepare($connection, "SELECT COALESCE(SUM(amount), 0) FROM financial_record WHERE type = 'expense' AND record_date BETWEEN ? AND ?");
    mysqli_stmt_bind_param($expenseStatement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($expenseStatement);
    $expenses = (float) mysqli_fetch_row(mysqli_stmt_get_result($expenseStatement))[0];
    mysqli_stmt_close($expenseStatement);

    $inventoryValue = (float) mysqli_fetch_row(mysqli_query(
        $connection,
        "SELECT COALESCE(SUM(sb.current_quantity * p.selling_price), 0) FROM stock_batch sb JOIN product p ON p.product_id = sb.product_id WHERE sb.status = 'active'"
    ))[0];

    $deliveryStatement = mysqli_prepare($connection, "SELECT COUNT(*) FROM delivery WHERE status = 'delivered' AND scheduled_date BETWEEN ? AND ?");
    mysqli_stmt_bind_param($deliveryStatement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($deliveryStatement);
    $completedDeliveries = (int) mysqli_fetch_row(mysqli_stmt_get_result($deliveryStatement))[0];
    mysqli_stmt_close($deliveryStatement);

    $ratingStatement = mysqli_prepare($connection, "SELECT COALESCE(AVG(rating), 0) FROM performance_review WHERE review_date BETWEEN ? AND ?");
    mysqli_stmt_bind_param($ratingStatement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($ratingStatement);
    $avgRating = (float) mysqli_fetch_row(mysqli_stmt_get_result($ratingStatement))[0];
    mysqli_stmt_close($ratingStatement);
    $strategicSummary = [
        "revenue" => $revenue,
        "expenses" => $expenses,
        "net" => $revenue - $expenses,
        "margin" => $revenue > 0 ? round(($revenue - $expenses) / $revenue * 100, 1) : 0,
        "inventory_value" => $inventoryValue,
        "completed_deliveries" => $completedDeliveries,
        "avg_employee_rating" => round($avgRating, 2),
    ];
}

if ($reportType === "audit" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT al.log_id, ua.username, ua.role_name, al.action, al.target_table, al.timestamp
            FROM audit_log al JOIN user_account ua ON ua.user_id = al.user_id
            WHERE DATE(al.timestamp) BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";
    $userId = (int) ($_GET["user_id"] ?? 0);
    if ($userId > 0) {
        $sql .= " AND al.user_id = ?";
        $params[] = $userId;
        $types .= "i";
    }
    $sql .= " ORDER BY al.target_table, al.timestamp DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $auditRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

$reportLabels = [
    "sales" => "Sales Performance",
    "inventory" => "Inventory Valuation",
    "stock_movement" => "Stock Movement",
    "delivery" => "Delivery Performance",
    "profit_loss" => "Profit & Loss",
    "revenue_growth" => "Revenue Growth",
    "employee" => "Employee Performance",
    "strategic" => "Strategic Summary",
    "audit" => "System Activities / Audit Log",
];
$statusClasses = ["scheduled" => "progress", "dispatched" => "progress", "in_transit" => "info", "delivered" => "resolved", "delayed" => "pending", "cancelled" => "pending"];

// --- Group and summarize each report the way a manager would actually read it ---

// Sales: keep the daily breakdown but add headline numbers a CEO reads first.
$salesTotalOrders = array_sum(array_column($salesRows, "order_count"));
$salesTotalRevenue = array_sum(array_column($salesRows, "revenue"));
$salesAvgDaily = count($salesRows) > 0 ? $salesTotalRevenue / count($salesRows) : 0;
$salesBestDay = null;
foreach ($salesRows as $row) {
    if ($salesBestDay === null || $row["revenue"] > $salesBestDay["revenue"]) {
        $salesBestDay = $row;
    }
}

// Inventory: group by category, ranked by value, with % of total — same lens as the Inventory Manager report.
$inventoryByCategory = [];
$inventoryGrandTotal = 0;
foreach ($inventoryRows as $row) {
    $cat = ($row["category"] ?? "") !== "" ? $row["category"] : "Uncategorized";
    if (!isset($inventoryByCategory[$cat])) {
        $inventoryByCategory[$cat] = ["rows" => [], "quantity" => 0, "value" => 0];
    }
    $value = $row["quantity"] * $row["selling_price"];
    $inventoryByCategory[$cat]["rows"][] = $row;
    $inventoryByCategory[$cat]["quantity"] += (int) $row["quantity"];
    $inventoryByCategory[$cat]["value"] += $value;
    $inventoryGrandTotal += $value;
}
uasort($inventoryByCategory, fn($a, $b) => $b["value"] <=> $a["value"]);

// Stock movement: group by type in business order, shrinkage called out separately.
$movementTypeOrder = ["in" => "Stock In", "return" => "Customer Return", "out" => "Stock Out (Sold)", "transfer" => "Transferred", "damaged" => "Damaged", "expired" => "Expired"];
$movementByType = [];
$shrinkageQuantity = 0;
$shrinkageValue = 0;
foreach ($movementRows as $row) {
    $type = $row["movement_type"];
    if (!isset($movementByType[$type])) {
        $movementByType[$type] = ["rows" => [], "quantity" => 0];
    }
    $movementByType[$type]["rows"][] = $row;
    $movementByType[$type]["quantity"] += (int) $row["quantity"];
    if (in_array($type, ["damaged", "expired"], true)) {
        $shrinkageQuantity += (int) $row["quantity"];
        $shrinkageValue += $row["quantity"] * $row["selling_price"];
    }
}
uksort($movementByType, function ($a, $b) use ($movementTypeOrder) {
    $order = array_keys($movementTypeOrder);
    return array_search($a, $order) <=> array_search($b, $order);
});

// Delivery: group by driver, ranked by delay count — same lens as the Distribution Manager report.
$deliveryByDriver = [];
foreach ($deliveryRows as $row) {
    $key = $row["driver_id"];
    if (!isset($deliveryByDriver[$key])) {
        $deliveryByDriver[$key] = ["name" => $row["driver_name"], "rows" => [], "count" => 0, "delivered" => 0, "delayed" => 0, "cancelled" => 0, "cost" => 0];
    }
    $deliveryByDriver[$key]["rows"][] = $row;
    $deliveryByDriver[$key]["count"]++;
    $deliveryByDriver[$key]["cost"] += $row["transport_cost"];
    if ($row["status"] === "delivered") {
        $deliveryByDriver[$key]["delivered"]++;
    } elseif ($row["status"] === "delayed") {
        $deliveryByDriver[$key]["delayed"]++;
    } elseif ($row["status"] === "cancelled") {
        $deliveryByDriver[$key]["cancelled"]++;
    }
}
uasort($deliveryByDriver, fn($a, $b) => $b["delayed"] <=> $a["delayed"] ?: $b["count"] <=> $a["count"]);
$deliveryTotalCost = array_sum(array_column($deliveryRows, "transport_cost"));
$deliveryDelayedCount = count(array_filter($deliveryRows, fn($r) => $r["status"] === "delayed"));

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
$plNet = $plByType["income"]["total"] - $plByType["expense"]["total"];

// Revenue growth: keep the monthly trend, add headline stats.
$revenueGrowthTotal = array_sum(array_column($revenueGrowthRows, "revenue"));
$revenueGrowthBestMonth = null;
foreach ($revenueGrowthRows as $row) {
    if ($revenueGrowthBestMonth === null || $row["revenue"] > $revenueGrowthBestMonth["revenue"]) {
        $revenueGrowthBestMonth = $row;
    }
}
$growthPercents = array_filter(array_column($revenueGrowthRows, "growth_percent"), fn($v) => $v !== null);
$revenueGrowthAvgGrowth = count($growthPercents) > 0 ? round(array_sum($growthPercents) / count($growthPercents), 1) : null;

// Employee performance: group by rating tier, best to worst, so it reads as a performance distribution, not a raw list.
$employeeStatusLabels = ["excellent" => "Excellent", "good" => "Good", "average" => "Average", "needs_improvement" => "Needs Improvement"];
$employeeStatusOrder = array_keys($employeeStatusLabels);
$employeeByStatus = [];
foreach ($employeeRows as $row) {
    $status = $row["status"];
    if (!isset($employeeByStatus[$status])) {
        $employeeByStatus[$status] = ["rows" => [], "count" => 0];
    }
    $employeeByStatus[$status]["rows"][] = $row;
    $employeeByStatus[$status]["count"]++;
}
uksort($employeeByStatus, fn($a, $b) => array_search($a, $employeeStatusOrder) <=> array_search($b, $employeeStatusOrder));

// Audit log: group by target table (system area) so governance activity is easy to scan by area, not just chronologically.
$auditByTable = [];
foreach ($auditRows as $row) {
    $table = ($row["target_table"] ?? "") !== "" ? $row["target_table"] : "Other";
    if (!isset($auditByTable[$table])) {
        $auditByTable[$table] = ["rows" => [], "count" => 0];
    }
    $auditByTable[$table]["rows"][] = $row;
    $auditByTable[$table]["count"]++;
}
uasort($auditByTable, fn($a, $b) => $b["count"] <=> $a["count"]);

// --- Printable report header content ---

$reportTitle = $reportLabels[$reportType] ?? "";
$filterParts = [];
if (in_array($reportType, ["sales", "inventory", "employee"], true) && $category !== "") {
    $filterParts[] = "Category/Department: " . $category;
}
if (in_array($reportType, ["sales", "stock_movement", "delivery", "profit_loss", "strategic", "audit"], true) && $fromDate !== "" && $toDate !== "") {
    $filterParts[] = "Period: " . $fromDate . " to " . $toDate;
}
if ($reportType === "revenue_growth") {
    $filterParts[] = "Year: " . ($_GET["year"] ?? date("Y"));
}
if (empty($filterParts)) {
    $filterParts[] = "All records";
}
$generatedBy = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "User";
$generatedOn = date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reports</title><link rel="stylesheet" href="../css/ceo_style.css?v=<?= filemtime(__DIR__ . '/../css/ceo_style.css') ?>"></head>
<body>
<?php if ($printView): ?>
  <main class="content content-embed">
<?php else: ?>
  <header class="topbar no-print"><h1>CEO / Head Manager</h1><p>Sales, inventory, finance, delivery, employee, and system reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/../nav.php'; ?>
    <main class="content">
      <section class="page-title no-print"><h2>Reports</h2><p>All business, financial, and system reports in one place.</p></section>

      <section class="panel no-print">
        <h3>Generate Report</h3>
        <form method="get" action="reports.php" id="reportFilterForm">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportType">Report Type</label>
              <select id="reportType" name="report_type" required>
                <option value="">Select type</option>
                <?php foreach ($reportLabels as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $reportType === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="category">Category / Department (optional)</label>
              <input type="text" id="category" name="category" value="<?= htmlspecialchars($category) ?>">
            </div>
            <div class="form-group">
              <label for="fromDate">From Date</label>
              <input type="date" id="fromDate" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
            </div>
            <div class="form-group">
              <label for="toDate">To Date</label>
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>" data-after="#fromDate">
            </div>
            <div class="form-group">
              <label for="year">Year (Revenue Growth report)</label>
              <input type="number" id="year" name="year" min="2000" value="<?= htmlspecialchars($_GET["year"] ?? date("Y")) ?>">
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Date range is not required for Inventory Valuation. Year applies only to Revenue Growth.</p>
          <div class="button-row"><button class="btn" type="submit">Generate Report</button></div>
        </form>
      </section>
<?php endif; ?>

      <?php if ($reportType === "sales"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Sales Performance <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat"><p class="label">Total Orders</p><p class="value"><?= (int) $salesTotalOrders ?></p></div>
              <div class="stat good"><p class="label">Total Revenue</p><p class="value"><?= number_format($salesTotalRevenue, 2) ?></p></div>
              <div class="stat"><p class="label">Avg. Daily Revenue</p><p class="value"><?= number_format($salesAvgDaily, 2) ?></p></div>
              <div class="stat"><p class="label">Best Day</p><p class="value" style="font-size: 15px;"><?= $salesBestDay ? htmlspecialchars($salesBestDay["sales_date"]) : "—" ?></p></div>
            </div>
            <div class="table-wrapper"><table>
              <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
              <tbody>
                <?php if (empty($salesRows)): ?><tr><td colspan="3">No sales data for this selection.</td></tr><?php endif; ?>
                <?php foreach ($salesRows as $row): ?>
                  <tr><td><?= htmlspecialchars($row["sales_date"]) ?></td><td><?= (int) $row["order_count"] ?></td><td><?= number_format($row["revenue"], 2) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
              <?php if (!empty($salesRows)): ?>
                <tfoot><tr class="grand-total"><td colspan="2">Grand Total</td><td><?= number_format($salesTotalRevenue, 2) ?></td></tr></tfoot>
              <?php endif; ?>
            </table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "inventory"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Inventory Valuation <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat"><p class="label">Total Inventory Value</p><p class="value"><?= number_format($inventoryGrandTotal, 2) ?></p></div>
            <div class="stat"><p class="label">Categories</p><p class="value"><?= count($inventoryByCategory) ?></p></div>
            <div class="stat"><p class="label">Products Listed</p><p class="value"><?= count($inventoryRows) ?></p></div>
          </div>
          <div class="table-wrapper"><table>
            <thead><tr><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Total Value</th></tr></thead>
            <tbody>
              <?php if (empty($inventoryByCategory)): ?><tr><td colspan="4">No valuation data loaded yet.</td></tr><?php endif; ?>
              <?php foreach ($inventoryByCategory as $cat => $group): ?>
                <tr class="group-heading">
                  <td colspan="4"><?= htmlspecialchars($cat) ?> — <?= $group["quantity"] ?> units, worth <?= number_format($group["value"], 2) ?> (<?= $inventoryGrandTotal > 0 ? number_format($group["value"] / $inventoryGrandTotal * 100, 1) : "0.0" ?>% of total)</td>
                </tr>
                <?php foreach ($group["rows"] as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row["product_name"]) ?></td>
                    <td><?= (int) $row["quantity"] ?></td>
                    <td><?= number_format($row["selling_price"], 2) ?></td>
                    <td><?= number_format($row["quantity"] * $row["selling_price"], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
            <?php if (!empty($inventoryByCategory)): ?>
              <tfoot><tr class="grand-total"><td colspan="3">Grand Total</td><td><?= number_format($inventoryGrandTotal, 2) ?></td></tr></tfoot>
            <?php endif; ?>
          </table></div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "stock_movement"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Stock Movement <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat warning"><p class="label">Shrinkage (damaged + expired)</p><p class="value"><?= $shrinkageQuantity ?> units</p></div>
              <div class="stat warning"><p class="label">Shrinkage Value Lost</p><p class="value"><?= number_format($shrinkageValue, 2) ?></p></div>
            </div>
            <div class="table-wrapper"><table>
              <thead><tr><th>Product</th><th>Quantity</th><th>Date</th></tr></thead>
              <tbody>
                <?php if (empty($movementByType)): ?><tr><td colspan="3">No movements for this selection.</td></tr><?php endif; ?>
                <?php foreach ($movementByType as $type => $group): ?>
                  <tr class="group-heading"><td colspan="3"><?= htmlspecialchars($movementTypeOrder[$type] ?? ucfirst($type)) ?> — <?= $group["quantity"] ?> units total</td></tr>
                  <?php foreach ($group["rows"] as $row): ?>
                    <tr><td><?= htmlspecialchars($row["product_name"]) ?></td><td><?= (int) $row["quantity"] ?></td><td><?= htmlspecialchars($row["movement_date"]) ?></td></tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "delivery"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Delivery Performance <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat"><p class="label">Total Deliveries</p><p class="value"><?= count($deliveryRows) ?></p></div>
              <div class="stat warning"><p class="label">Delayed</p><p class="value"><?= $deliveryDelayedCount ?></p></div>
              <div class="stat"><p class="label">Total Transport Cost</p><p class="value"><?= number_format($deliveryTotalCost, 2) ?></p></div>
            </div>
            <div class="table-wrapper"><table>
              <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Cost</th></tr></thead>
              <tbody>
                <?php if (empty($deliveryByDriver)): ?><tr><td colspan="4">No deliveries for this selection.</td></tr><?php endif; ?>
                <?php foreach ($deliveryByDriver as $group): ?>
                  <tr class="group-heading">
                    <td colspan="4"><?= htmlspecialchars($group["name"]) ?> — <?= $group["count"] ?> deliveries (<?= $group["delivered"] ?> delivered, <?= $group["delayed"] ?> delayed<?= $group["cancelled"] > 0 ? ", " . $group["cancelled"] . " cancelled" : "" ?>), cost <?= number_format($group["cost"], 2) ?></td>
                  </tr>
                  <?php foreach ($group["rows"] as $row): ?>
                    <tr>
                      <td>#<?= $row["order_id"] ?></td><td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                      <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></span></td>
                      <td><?= number_format($row["transport_cost"], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
              <?php if (!empty($deliveryByDriver)): ?>
                <tfoot><tr class="grand-total"><td colspan="3">Grand Total</td><td><?= number_format($deliveryTotalCost, 2) ?></td></tr></tfoot>
              <?php endif; ?>
            </table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "profit_loss"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Profit and Loss <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat good"><p class="label">Total Income</p><p class="value"><?= number_format($plByType["income"]["total"], 2) ?></p></div>
              <div class="stat warning"><p class="label">Total Expense</p><p class="value"><?= number_format($plByType["expense"]["total"], 2) ?></p></div>
              <div class="stat <?= $plNet >= 0 ? "good" : "warning" ?>"><p class="label">Net Result</p><p class="value"><?= number_format($plNet, 2) ?></p></div>
            </div>
            <div class="table-wrapper"><table>
              <thead><tr><th>Description</th><th>Amount</th><th>Date</th></tr></thead>
              <tbody>
                <?php if (empty($profitLossRows)): ?><tr><td colspan="3">No financial records for this selection.</td></tr><?php endif; ?>
                <?php foreach (["income" => "Income", "expense" => "Expense"] as $typeKey => $typeLabel): ?>
                  <?php if (empty($plByType[$typeKey]["categories"])) continue; ?>
                  <tr class="group-heading"><td colspan="3"><?= $typeLabel ?> — total <?= number_format($plByType[$typeKey]["total"], 2) ?></td></tr>
                  <?php foreach ($plByType[$typeKey]["categories"] as $cat => $group): ?>
                    <tr class="subgroup-heading"><td colspan="3"><?= htmlspecialchars($cat) ?> — <?= number_format($group["amount"], 2) ?></td></tr>
                    <?php foreach ($group["rows"] as $row): ?>
                      <tr><td><?= htmlspecialchars($row["description"] ?? "") ?></td><td><?= number_format($row["amount"], 2) ?></td><td><?= htmlspecialchars($row["record_date"]) ?></td></tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
              <?php if (!empty($profitLossRows)): ?>
                <tfoot><tr class="grand-total"><td colspan="2">Net Result</td><td><?= number_format($plNet, 2) ?></td></tr></tfoot>
              <?php endif; ?>
            </table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "revenue_growth"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Revenue Growth <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <div class="stat good"><p class="label">Total Revenue (Year)</p><p class="value"><?= number_format($revenueGrowthTotal, 2) ?></p></div>
            <div class="stat"><p class="label">Best Month</p><p class="value" style="font-size: 15px;"><?= $revenueGrowthBestMonth ? htmlspecialchars($revenueGrowthBestMonth["period"]) : "—" ?></p></div>
            <div class="stat"><p class="label">Avg. Monthly Growth</p><p class="value"><?= $revenueGrowthAvgGrowth !== null ? $revenueGrowthAvgGrowth . "%" : "—" ?></p></div>
          </div>
          <div class="table-wrapper"><table>
            <thead><tr><th>Period</th><th>Revenue</th><th>Previous Revenue</th><th>Growth Amount</th><th>Growth %</th></tr></thead>
            <tbody>
              <?php if (empty($revenueGrowthRows)): ?><tr><td colspan="5">No revenue data for this year.</td></tr><?php endif; ?>
              <?php foreach ($revenueGrowthRows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row["period"]) ?></td>
                  <td><?= number_format($row["revenue"], 2) ?></td>
                  <td><?= $row["previous"] !== null ? number_format($row["previous"], 2) : "—" ?></td>
                  <td><?= $row["growth_amount"] !== null ? number_format($row["growth_amount"], 2) : "—" ?></td>
                  <td><?= $row["growth_percent"] !== null ? $row["growth_percent"] . "%" : "—" ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "employee"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Employee Performance <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <div class="report-summary">
            <?php foreach ($employeeStatusLabels as $key => $label): ?>
              <div class="stat <?= $key === "needs_improvement" ? "warning" : "" ?>"><p class="label"><?= htmlspecialchars($label) ?></p><p class="value"><?= $employeeByStatus[$key]["count"] ?? 0 ?></p></div>
            <?php endforeach; ?>
          </div>
          <div class="table-wrapper"><table>
            <thead><tr><th>Employee</th><th>Job Title</th><th>Rating</th><th>Review Date</th></tr></thead>
            <tbody>
              <?php if (empty($employeeByStatus)): ?><tr><td colspan="4">No performance data loaded yet.</td></tr><?php endif; ?>
              <?php foreach ($employeeByStatus as $status => $group): ?>
                <tr class="group-heading"><td colspan="4"><?= htmlspecialchars($employeeStatusLabels[$status] ?? ucfirst($status)) ?> — <?= $group["count"] ?> reviews</td></tr>
                <?php foreach ($group["rows"] as $row): ?>
                  <tr><td><?= htmlspecialchars($row["full_name"]) ?></td><td><?= htmlspecialchars($row["job_title"]) ?></td><td><?= (int) $row["rating"] ?>/5</td><td><?= htmlspecialchars($row["review_date"]) ?></td></tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "strategic"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>Strategic Summary (<?= htmlspecialchars($fromDate) ?> to <?= htmlspecialchars($toDate) ?>) <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <?php if ($strategicSummary === null): ?>
            <p style="padding: 14px 20px;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat good"><p class="label">Revenue</p><p class="value"><?= number_format($strategicSummary["revenue"], 2) ?></p></div>
              <div class="stat warning"><p class="label">Expenses</p><p class="value"><?= number_format($strategicSummary["expenses"], 2) ?></p></div>
              <div class="stat <?= $strategicSummary["net"] >= 0 ? "good" : "warning" ?>"><p class="label">Net Result</p><p class="value"><?= number_format($strategicSummary["net"], 2) ?></p></div>
              <div class="stat <?= $strategicSummary["margin"] >= 0 ? "good" : "warning" ?>"><p class="label">Profit Margin</p><p class="value"><?= $strategicSummary["margin"] ?>%</p></div>
              <div class="stat"><p class="label">Inventory Value (current)</p><p class="value"><?= number_format($strategicSummary["inventory_value"], 2) ?></p></div>
              <div class="stat"><p class="label">Completed Deliveries</p><p class="value"><?= $strategicSummary["completed_deliveries"] ?></p></div>
              <div class="stat"><p class="label">Avg. Employee Rating</p><p class="value"><?= $strategicSummary["avg_employee_rating"] ?>/5</p></div>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "audit"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>System Activities / Audit Log <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button></h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="table-wrapper"><table>
              <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Date</th></tr></thead>
              <tbody>
                <?php if (empty($auditByTable)): ?><tr><td colspan="4">No audit records for this selection.</td></tr><?php endif; ?>
                <?php foreach ($auditByTable as $table => $group): ?>
                  <tr class="group-heading"><td colspan="4"><?= htmlspecialchars(ucwords(str_replace("_", " ", $table))) ?> — <?= $group["count"] ?> actions</td></tr>
                  <?php foreach ($group["rows"] as $row): ?>
                    <tr><td><?= htmlspecialchars($row["username"]) ?></td><td><?= htmlspecialchars($row["role_name"]) ?></td><td><?= htmlspecialchars($row["action"]) ?></td><td><?= htmlspecialchars($row["timestamp"]) ?></td></tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
<?php if ($printView): ?>
  </main>
<?php else: ?>
    </main>
  </div>
  <script src="../js/report_tab.js"></script>
  <script src="../js/validate.js"></script>
<?php endif; ?>
</body>
</html>
