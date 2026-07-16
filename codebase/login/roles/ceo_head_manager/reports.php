<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('CEO');

$activePage = "reports";

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
    $sql .= " GROUP BY DATE(so.order_date) ORDER BY sales_date DESC";
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
    $sql .= " GROUP BY p.product_id, p.product_name, p.category, p.selling_price ORDER BY p.product_name";
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
        "SELECT sm.movement_id, p.product_name, sm.movement_type, sm.quantity, sm.movement_date
         FROM stock_movement sm JOIN product p ON p.product_id = sm.product_id
         WHERE sm.movement_date BETWEEN ? AND ?
         ORDER BY sm.movement_date DESC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate . " 23:59:59");
    mysqli_stmt_execute($statement);
    $movementRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "delivery" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT d.order_id, e.full_name AS driver_name, d.scheduled_date, d.status, d.transport_cost
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
        "SELECT type, description, amount, record_date
         FROM financial_record
         WHERE record_date BETWEEN ? AND ?
         ORDER BY record_date DESC"
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
    $sql .= " ORDER BY al.timestamp DESC";
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
$statusClasses = ["scheduled" => "progress", "dispatched" => "progress", "in_transit" => "progress", "delivered" => "resolved", "delayed" => "pending", "cancelled" => "pending"];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reports</title><link rel="stylesheet" href="css/ceo_style.css"></head>
<body>
  <header class="topbar"><h1>CEO / Head Manager</h1><p>Sales, inventory, finance, delivery, employee, and system reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Reports</h2><p>All business, financial, and system reports in one place.</p></section>

      <section class="panel">
        <h3>Generate Report</h3>
        <form method="get" action="reports.php">
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
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
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

      <?php if ($reportType === "sales"): ?>
        <section class="panel"><h3>Sales Performance</h3><div class="table-wrapper"><table>
          <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php if ($fromDate === "" || $toDate === ""): ?><tr><td colspan="3">Select a date range.</td></tr>
            <?php elseif (empty($salesRows)): ?><tr><td colspan="3">No sales data for this selection.</td></tr><?php endif; ?>
            <?php foreach ($salesRows as $row): ?>
              <tr><td><?= htmlspecialchars($row["sales_date"]) ?></td><td><?= (int) $row["order_count"] ?></td><td><?= number_format($row["revenue"], 2) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "inventory"): ?>
        <section class="panel"><h3>Inventory Valuation</h3><div class="table-wrapper"><table>
          <thead><tr><th>Product</th><th>Category</th><th>Quantity</th><th>Unit Price</th><th>Total Value</th></tr></thead>
          <tbody>
            <?php if (empty($inventoryRows)): ?><tr><td colspan="5">No valuation data loaded yet.</td></tr><?php endif; ?>
            <?php foreach ($inventoryRows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["product_name"]) ?></td>
                <td><?= htmlspecialchars($row["category"] ?? "") ?></td>
                <td><?= (int) $row["quantity"] ?></td>
                <td><?= number_format($row["selling_price"], 2) ?></td>
                <td><?= number_format($row["quantity"] * $row["selling_price"], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "stock_movement"): ?>
        <section class="panel"><h3>Stock Movement</h3><div class="table-wrapper"><table>
          <thead><tr><th>Product</th><th>Type</th><th>Quantity</th><th>Date</th></tr></thead>
          <tbody>
            <?php if ($fromDate === "" || $toDate === ""): ?><tr><td colspan="4">Select a date range.</td></tr>
            <?php elseif (empty($movementRows)): ?><tr><td colspan="4">No movements for this selection.</td></tr><?php endif; ?>
            <?php foreach ($movementRows as $row): ?>
              <tr><td><?= htmlspecialchars($row["product_name"]) ?></td><td><?= htmlspecialchars(ucfirst($row["movement_type"])) ?></td><td><?= (int) $row["quantity"] ?></td><td><?= htmlspecialchars($row["movement_date"]) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "delivery"): ?>
        <section class="panel"><h3>Delivery Performance</h3><div class="table-wrapper"><table>
          <thead><tr><th>Order</th><th>Driver</th><th>Date</th><th>Status</th><th>Cost</th></tr></thead>
          <tbody>
            <?php if ($fromDate === "" || $toDate === ""): ?><tr><td colspan="5">Select a date range.</td></tr>
            <?php elseif (empty($deliveryRows)): ?><tr><td colspan="5">No deliveries for this selection.</td></tr><?php endif; ?>
            <?php foreach ($deliveryRows as $row): ?>
              <tr>
                <td>#<?= $row["order_id"] ?></td><td><?= htmlspecialchars($row["driver_name"]) ?></td><td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></span></td>
                <td><?= number_format($row["transport_cost"], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "profit_loss"): ?>
        <?php
          $totalIncome = array_sum(array_map(fn($r) => $r["type"] === "income" ? $r["amount"] : 0, $profitLossRows));
          $totalExpense = array_sum(array_map(fn($r) => $r["type"] === "expense" ? $r["amount"] : 0, $profitLossRows));
        ?>
        <section class="panel"><h3>Profit and Loss</h3><div class="table-wrapper"><table>
          <thead><tr><th>Type</th><th>Description</th><th>Amount</th><th>Date</th></tr></thead>
          <tbody>
            <?php if ($fromDate === "" || $toDate === ""): ?><tr><td colspan="4">Select a date range.</td></tr>
            <?php elseif (empty($profitLossRows)): ?><tr><td colspan="4">No financial records for this selection.</td></tr><?php endif; ?>
            <?php foreach ($profitLossRows as $row): ?>
              <tr><td><?= htmlspecialchars(ucfirst($row["type"])) ?></td><td><?= htmlspecialchars($row["description"] ?? "") ?></td><td><?= number_format($row["amount"], 2) ?></td><td><?= htmlspecialchars($row["record_date"]) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if (!empty($profitLossRows)): ?>
          <p style="padding: 14px 20px; font-weight: bold;">Income: <?= number_format($totalIncome, 2) ?> — Expense: <?= number_format($totalExpense, 2) ?> — Net: <?= number_format($totalIncome - $totalExpense, 2) ?></p>
        <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "revenue_growth"): ?>
        <section class="panel"><h3>Revenue Growth</h3><div class="table-wrapper"><table>
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
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "employee"): ?>
        <section class="panel"><h3>Employee Performance</h3><div class="table-wrapper"><table>
          <thead><tr><th>Employee</th><th>Job Title</th><th>Rating</th><th>Status</th><th>Review Date</th></tr></thead>
          <tbody>
            <?php if (empty($employeeRows)): ?><tr><td colspan="5">No performance data loaded yet.</td></tr><?php endif; ?>
            <?php foreach ($employeeRows as $row): ?>
              <tr><td><?= htmlspecialchars($row["full_name"]) ?></td><td><?= htmlspecialchars($row["job_title"]) ?></td><td><?= (int) $row["rating"] ?>/5</td><td><?= htmlspecialchars(str_replace("_", " ", ucfirst($row["status"]))) ?></td><td><?= htmlspecialchars($row["review_date"]) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>

      <?php if ($reportType === "strategic"): ?>
        <section class="panel"><h3>Strategic Summary (<?= htmlspecialchars($fromDate) ?> to <?= htmlspecialchars($toDate) ?>)</h3>
          <?php if ($strategicSummary === null): ?>
            <p style="padding: 14px 20px;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="table-wrapper"><table>
              <thead><tr><th>Metric</th><th>Value</th></tr></thead>
              <tbody>
                <tr><td>Revenue</td><td><?= number_format($strategicSummary["revenue"], 2) ?></td></tr>
                <tr><td>Expenses</td><td><?= number_format($strategicSummary["expenses"], 2) ?></td></tr>
                <tr><td>Net Result</td><td><?= number_format($strategicSummary["net"], 2) ?></td></tr>
                <tr><td>Inventory Value (current)</td><td><?= number_format($strategicSummary["inventory_value"], 2) ?></td></tr>
                <tr><td>Completed Deliveries</td><td><?= $strategicSummary["completed_deliveries"] ?></td></tr>
                <tr><td>Average Employee Rating</td><td><?= $strategicSummary["avg_employee_rating"] ?>/5</td></tr>
              </tbody>
            </table></div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "audit"): ?>
        <section class="panel"><h3>System Activities / Audit Log</h3><div class="table-wrapper"><table>
          <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Target</th><th>Date</th></tr></thead>
          <tbody>
            <?php if ($fromDate === "" || $toDate === ""): ?><tr><td colspan="5">Select a date range.</td></tr>
            <?php elseif (empty($auditRows)): ?><tr><td colspan="5">No audit records for this selection.</td></tr><?php endif; ?>
            <?php foreach ($auditRows as $row): ?>
              <tr><td><?= htmlspecialchars($row["username"]) ?></td><td><?= htmlspecialchars($row["role_name"]) ?></td><td><?= htmlspecialchars($row["action"]) ?></td><td><?= htmlspecialchars($row["target_table"] ?? "") ?></td><td><?= htmlspecialchars($row["timestamp"]) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table></div></section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
