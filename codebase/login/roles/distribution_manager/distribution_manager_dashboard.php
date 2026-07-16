<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Distribution Manager');

$activePage = "dashboard";

$confirmedOrders = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM sales_order so
     LEFT JOIN delivery d ON d.order_id = so.order_id
     WHERE so.status IN ('completed', 'invoiced') AND d.delivery_id IS NULL"
))[0];

$dispatchedToday = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM delivery WHERE status IN ('dispatched', 'in_transit') AND scheduled_date = CURDATE()"
))[0];

$delayedOrders = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM delivery WHERE status = 'delayed'"
))[0];

$transportCostToday = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(transport_cost), 0) FROM delivery WHERE scheduled_date = CURDATE()"
))[0];

$recentDeliveries = mysqli_query(
    $connection,
    "SELECT d.order_id, cu.name AS customer_name, e.full_name AS driver_name, d.scheduled_date, d.status
     FROM delivery d
     JOIN sales_order so ON so.order_id = d.order_id
     JOIN customer cu ON cu.customer_id = so.customer_id
     JOIN employee e ON e.employee_id = d.driver_id
     ORDER BY d.scheduled_date DESC, d.delivery_id DESC
     LIMIT 8"
);

$statusClasses = [
    "scheduled" => "progress",
    "dispatched" => "progress",
    "in_transit" => "progress",
    "delivered" => "resolved",
    "delayed" => "pending",
    "cancelled" => "pending",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Distribution Manager Dashboard</title>
  <link rel="stylesheet" href="css/dm_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Distribution Management</h1>
    <p>Delivery schedules, dispatch tracking, and route planning</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of distribution and delivery activities.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Confirmed Orders</h3>
          <p class="number"><?= $confirmedOrders ?></p>
          <p>Ready for scheduling</p>
        </div>
        <div class="card">
          <h3>Dispatched</h3>
          <p class="number"><?= $dispatchedToday ?></p>
          <p>Orders sent today</p>
        </div>
        <div class="card">
          <h3>Delayed</h3>
          <p class="number"><?= $delayedOrders ?></p>
          <p>Need attention</p>
        </div>
        <div class="card">
          <h3>Transport Cost</h3>
          <p class="number">Rs. <?= number_format($transportCostToday, 2) ?></p>
          <p>Cost for today</p>
        </div>
      </section>

      <section class="panel">
        <h3>Recent Delivery Activities</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Driver</th>
                <th>Delivery Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($recentDeliveries) === 0): ?>
                <tr><td colspan="5">No delivery activity loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($recentDeliveries)): ?>
                <tr>
                  <td><?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["driver_name"]) ?></td>
                  <td><?= htmlspecialchars($row["scheduled_date"]) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></span></td>
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
