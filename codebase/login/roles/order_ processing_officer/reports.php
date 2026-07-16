<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Order Processing Officer');

$activePage = "reports";

$customers = getAllCustomers($connection);
$reportType = $_GET["report_type"] ?? "";
$customerId = (int) ($_GET["customer_id"] ?? 0);
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";
$salesDate = $_GET["sales_date"] ?? "";

$orderRows = [];
$dailySummary = null;
$dailyOrders = [];

if ($reportType === "orders" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT so.order_id, c.name AS customer_name, so.order_date, so.status, so.is_credit, so.total_amount
            FROM sales_order so
            JOIN customer c ON c.customer_id = so.customer_id
            WHERE DATE(so.order_date) BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";

    if ($customerId > 0) {
        $sql .= " AND so.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }

    $statusFilter = $_GET["status_filter"] ?? "all";
    if ($statusFilter === "credit") {
        $sql .= " AND so.is_credit = 1";
    } elseif (in_array($statusFilter, ["pending", "processing", "invoiced", "completed", "cancelled"], true)) {
        $sql .= " AND so.status = ?";
        $params[] = $statusFilter;
        $types .= "s";
    }

    $sql .= " ORDER BY so.order_date DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $orderRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "daily_sales" && $salesDate !== "") {
    $summaryStatement = mysqli_prepare(
        $connection,
        "SELECT COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS total_sales,
                COALESCE(SUM(CASE WHEN is_credit = 0 THEN total_amount ELSE 0 END), 0) AS cash_sales,
                COALESCE(SUM(CASE WHEN is_credit = 1 THEN total_amount ELSE 0 END), 0) AS credit_sales
         FROM sales_order
         WHERE DATE(order_date) = ? AND status != 'cancelled'"
    );
    mysqli_stmt_bind_param($summaryStatement, "s", $salesDate);
    mysqli_stmt_execute($summaryStatement);
    $dailySummary = mysqli_fetch_assoc(mysqli_stmt_get_result($summaryStatement));
    mysqli_stmt_close($summaryStatement);

    $ordersStatement = mysqli_prepare(
        $connection,
        "SELECT so.order_id, c.name AS customer_name, so.is_credit, so.total_amount
         FROM sales_order so
         JOIN customer c ON c.customer_id = so.customer_id
         WHERE DATE(so.order_date) = ? AND so.status != 'cancelled'
         ORDER BY so.order_id DESC"
    );
    mysqli_stmt_bind_param($ordersStatement, "s", $salesDate);
    mysqli_stmt_execute($ordersStatement);
    $dailyOrders = mysqli_fetch_all(mysqli_stmt_get_result($ordersStatement), MYSQLI_ASSOC);
    mysqli_stmt_close($ordersStatement);
}

$statusOptions = ["all" => "All", "pending" => "Pending", "processing" => "Processing", "invoiced" => "Invoiced", "completed" => "Completed", "cancelled" => "Cancelled", "credit" => "Credit Orders"];
$statusClasses = ["pending" => "progress", "processing" => "progress", "invoiced" => "resolved", "completed" => "resolved", "cancelled" => "pending"];
$statusFilter = $_GET["status_filter"] ?? "all";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>
  <link rel="stylesheet" href="css/opo_style.css">
</head>
<body>
  <header class="topbar"><h1>Order Processing</h1><p>Generate order reports and track daily sales totals</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Reports</h2><p>Filter sales orders or view daily sales totals.</p></section>

      <section class="panel">
        <h3>Generate Report</h3>
        <form method="get" action="reports.php">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportType">Report Type</label>
              <select id="reportType" name="report_type" required>
                <option value="">Select type</option>
                <option value="orders" <?= $reportType === "orders" ? "selected" : "" ?>>Orders</option>
                <option value="daily_sales" <?= $reportType === "daily_sales" ? "selected" : "" ?>>Daily Sales</option>
              </select>
            </div>
            <div class="form-group">
              <label for="customerId">Customer (optional, Orders report)</label>
              <select id="customerId" name="customer_id">
                <option value="0">All customers</option>
                <?php foreach ($customers as $customer): ?>
                  <option value="<?= $customer["customer_id"] ?>" <?= $customerId === (int) $customer["customer_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($customer["name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="statusFilter">Status (Orders report)</label>
              <select id="statusFilter" name="status_filter">
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $statusFilter === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="fromDate">From Date (Orders report)</label>
              <input type="date" id="fromDate" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
            </div>
            <div class="form-group">
              <label for="toDate">To Date (Orders report)</label>
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
            </div>
            <div class="form-group">
              <label for="salesDate">Sales Date (Daily Sales report)</label>
              <input type="date" id="salesDate" name="sales_date" value="<?= htmlspecialchars($salesDate) ?>">
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Generate Report</button></div>
        </form>
      </section>

      <?php if ($reportType === "orders"): ?>
        <section class="panel">
          <h3>Order Report</h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Credit</th><th>Status</th><th>Total</th></tr></thead>
              <tbody>
                <?php if ($fromDate === "" || $toDate === ""): ?>
                  <tr><td colspan="6">Select a date range to generate this report.</td></tr>
                <?php elseif (empty($orderRows)): ?>
                  <tr><td colspan="6">No orders found for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($orderRows as $row): ?>
                  <tr>
                    <td><?= $row["order_id"] ?></td>
                    <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                    <td><?= htmlspecialchars(substr($row["order_date"], 0, 10)) ?></td>
                    <td><?= $row["is_credit"] ? "Yes" : "No" ?></td>
                    <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                    <td><?= number_format($row["total_amount"], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "daily_sales"): ?>
        <?php if ($salesDate === ""): ?>
          <section class="panel"><p style="padding: 14px 20px;">Select a date to view daily sales totals.</p></section>
        <?php else: ?>
          <section class="cards">
            <div class="card">
              <h3>Total Orders</h3>
              <p class="number"><?= (int) $dailySummary["total_orders"] ?></p>
              <p>Orders for selected date</p>
            </div>
            <div class="card">
              <h3>Total Sales</h3>
              <p class="number">Rs. <?= number_format($dailySummary["total_sales"], 2) ?></p>
              <p>Sales amount</p>
            </div>
            <div class="card">
              <h3>Cash Sales</h3>
              <p class="number">Rs. <?= number_format($dailySummary["cash_sales"], 2) ?></p>
              <p>Non-credit orders</p>
            </div>
            <div class="card">
              <h3>Credit Sales</h3>
              <p class="number">Rs. <?= number_format($dailySummary["credit_sales"], 2) ?></p>
              <p>Credit orders</p>
            </div>
          </section>
          <section class="panel">
            <h3>Orders on <?= htmlspecialchars($salesDate) ?></h3>
            <div class="table-wrapper">
              <table>
                <thead><tr><th>Order ID</th><th>Customer</th><th>Type</th><th>Amount</th></tr></thead>
                <tbody>
                  <?php if (empty($dailyOrders)): ?>
                    <tr><td colspan="4">No orders on this date.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($dailyOrders as $row): ?>
                    <tr>
                      <td><?= $row["order_id"] ?></td>
                      <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                      <td><?= $row["is_credit"] ? "Credit" : "Cash" ?></td>
                      <td><?= number_format($row["total_amount"], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
