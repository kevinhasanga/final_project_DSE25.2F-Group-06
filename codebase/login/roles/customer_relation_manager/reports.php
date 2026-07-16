<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Customer Relationship Officer');

$activePage = "reports";

$customers = getAllCustomers($connection);
$reportType = $_GET["report_type"] ?? "";
$customerId = (int) ($_GET["customer_id"] ?? 0);
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";

$purchaseRows = [];
$complaintRows = [];
$loyaltyRows = [];
$promotionRows = [];

if ($reportType === "purchases" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT so.order_id, cu.name AS customer_name, so.order_date, p.product_name, oi.quantity, oi.line_total
            FROM sales_order so
            JOIN customer cu ON cu.customer_id = so.customer_id
            JOIN order_item oi ON oi.order_id = so.order_id
            JOIN product p ON p.product_id = oi.product_id
            WHERE DATE(so.order_date) BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";
    if ($customerId > 0) {
        $sql .= " AND so.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    $sql .= " ORDER BY so.order_date DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $purchaseRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "complaints" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT c.complaint_id, cu.name AS customer_name, c.description, c.status, c.created_date
            FROM complaint c
            JOIN customer cu ON cu.customer_id = c.customer_id
            WHERE DATE(c.created_date) BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";
    if ($customerId > 0) {
        $sql .= " AND c.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    $sql .= " ORDER BY c.created_date DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $complaintRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "loyalty") {
    $sql = "SELECT customer_id, name, loyalty_points FROM customer";
    $params = [];
    $types = "";
    if ($customerId > 0) {
        $sql .= " WHERE customer_id = ?";
        $params[] = $customerId;
        $types = "i";
    }
    $sql .= " ORDER BY loyalty_points DESC";
    $statement = mysqli_prepare($connection, $sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }
    mysqli_stmt_execute($statement);
    $loyaltyRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "promotions" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT title, customer_group, message, sent_at
         FROM promotional_notification
         WHERE DATE(sent_at) BETWEEN ? AND ?
         ORDER BY sent_at DESC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($statement);
    $promotionRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reports</title><link rel="stylesheet" href="css/cro_style.css">
</head>
<body>
  <header class="topbar"><h1>Customer Relationship Officer</h1><p>Generate customer activity reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Reports</h2><p>Filter and generate customer activity reports.</p></section>

      <section class="panel">
        <h3>Report Filters</h3>
        <form method="get" action="reports.php">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportType">Report Type</label>
              <select id="reportType" name="report_type" required>
                <option value="">Select type</option>
                <option value="purchases" <?= $reportType === "purchases" ? "selected" : "" ?>>Purchases</option>
                <option value="complaints" <?= $reportType === "complaints" ? "selected" : "" ?>>Complaints</option>
                <option value="loyalty" <?= $reportType === "loyalty" ? "selected" : "" ?>>Loyalty</option>
                <option value="promotions" <?= $reportType === "promotions" ? "selected" : "" ?>>Promotions</option>
              </select>
            </div>
            <div class="form-group">
              <label for="customerId">Customer (optional)</label>
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
              <label for="fromDate">From Date</label>
              <input type="date" id="fromDate" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
            </div>
            <div class="form-group">
              <label for="toDate">To Date</label>
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Date range is not required for the Loyalty report.</p>
          <div class="button-row"><button class="btn" type="submit">Generate Report</button></div>
        </form>
      </section>

      <?php if ($reportType === "purchases"): ?>
        <section class="panel">
          <h3>Purchases</h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Item</th><th>Quantity</th><th>Line Total</th></tr></thead>
              <tbody>
                <?php if ($fromDate === "" || $toDate === ""): ?>
                  <tr><td colspan="6">Select a date range to generate this report.</td></tr>
                <?php elseif (empty($purchaseRows)): ?>
                  <tr><td colspan="6">No purchases found for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($purchaseRows as $row): ?>
                  <tr>
                    <td><?= $row["order_id"] ?></td>
                    <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                    <td><?= htmlspecialchars(substr($row["order_date"], 0, 10)) ?></td>
                    <td><?= htmlspecialchars($row["product_name"]) ?></td>
                    <td><?= (int) $row["quantity"] ?></td>
                    <td><?= number_format($row["line_total"], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "complaints"): ?>
        <section class="panel">
          <h3>Complaints</h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>ID</th><th>Customer</th><th>Description</th><th>Status</th><th>Created</th></tr></thead>
              <tbody>
                <?php if ($fromDate === "" || $toDate === ""): ?>
                  <tr><td colspan="5">Select a date range to generate this report.</td></tr>
                <?php elseif (empty($complaintRows)): ?>
                  <tr><td colspan="5">No complaints found for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($complaintRows as $row): ?>
                  <tr>
                    <td><?= $row["complaint_id"] ?></td>
                    <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                    <td><?= htmlspecialchars($row["description"]) ?></td>
                    <td><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></td>
                    <td><?= htmlspecialchars($row["created_date"]) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "loyalty"): ?>
        <section class="panel">
          <h3>Loyalty Points</h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Customer ID</th><th>Name</th><th>Loyalty Points</th></tr></thead>
              <tbody>
                <?php if (empty($loyaltyRows)): ?>
                  <tr><td colspan="3">No customers found.</td></tr>
                <?php endif; ?>
                <?php foreach ($loyaltyRows as $row): ?>
                  <tr>
                    <td><?= $row["customer_id"] ?></td>
                    <td><?= htmlspecialchars($row["name"]) ?></td>
                    <td><?= (int) $row["loyalty_points"] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "promotions"): ?>
        <section class="panel">
          <h3>Promotions</h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Title</th><th>Group</th><th>Message</th><th>Sent At</th></tr></thead>
              <tbody>
                <?php if ($fromDate === "" || $toDate === ""): ?>
                  <tr><td colspan="4">Select a date range to generate this report.</td></tr>
                <?php elseif (empty($promotionRows)): ?>
                  <tr><td colspan="4">No promotions found for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($promotionRows as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row["title"]) ?></td>
                    <td><?= htmlspecialchars(ucfirst($row["customer_group"])) ?></td>
                    <td><?= htmlspecialchars($row["message"]) ?></td>
                    <td><?= htmlspecialchars($row["sent_at"]) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
