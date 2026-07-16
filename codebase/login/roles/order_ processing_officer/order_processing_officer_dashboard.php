<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Order Processing Officer');

$activePage = "dashboard";

$todayOrders = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM sales_order WHERE DATE(order_date) = CURDATE()"
))[0];

$pendingOrders = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM sales_order WHERE status IN ('pending', 'processing')"
))[0];

$todayInvoices = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM invoice WHERE issue_date = CURDATE()"
))[0];

$dailySales = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(total_amount), 0) FROM sales_order WHERE DATE(order_date) = CURDATE() AND status != 'cancelled'"
))[0];

$recentOrders = mysqli_query(
    $connection,
    "SELECT so.order_id, c.name AS customer_name, so.order_date, so.total_amount, so.status
     FROM sales_order so
     JOIN customer c ON c.customer_id = so.customer_id
     ORDER BY so.order_date DESC, so.order_id DESC
     LIMIT 8"
);

$statusClasses = [
    "pending" => "progress",
    "processing" => "progress",
    "invoiced" => "resolved",
    "completed" => "resolved",
    "cancelled" => "pending",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Processing Officer Dashboard</title>
  <link rel="stylesheet" href="css/opo_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Order Processing</h1>
    <p>Sales orders, invoices, stock checking, and daily sales</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of order processing activities.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Today's Orders</h3>
          <p class="number"><?= $todayOrders ?></p>
          <p>Orders received today</p>
        </div>
        <div class="card">
          <h3>Pending Orders</h3>
          <p class="number"><?= $pendingOrders ?></p>
          <p>Need processing</p>
        </div>
        <div class="card">
          <h3>Invoices</h3>
          <p class="number"><?= $todayInvoices ?></p>
          <p>Generated today</p>
        </div>
        <div class="card">
          <h3>Daily Sales</h3>
          <p class="number">Rs. <?= number_format($dailySales, 2) ?></p>
          <p>Total sales amount</p>
        </div>
      </section>

      <section class="panel">
        <h3>Recent Orders</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($recentOrders) === 0): ?>
                <tr><td colspan="5">No order records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($recentOrders)): ?>
                <tr>
                  <td><?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["order_date"]) ?></td>
                  <td><?= number_format($row["total_amount"], 2) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
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
