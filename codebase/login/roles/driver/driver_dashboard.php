<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Driver');

$activePage = "dashboard";
$currentDriverId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);

$assignedRoutesToday = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM delivery WHERE driver_id = $currentDriverId AND scheduled_date = CURDATE()"
))[0];

$completedDeliveries = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM delivery WHERE driver_id = $currentDriverId AND status = 'delivered'"
))[0];

$reportedIssues = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM delivery_issue di
     JOIN delivery d ON d.delivery_id = di.delivery_id
     WHERE d.driver_id = $currentDriverId"
))[0];

$fuelUsed = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(liters), 0) FROM fuel_usage WHERE driver_id = $currentDriverId"
))[0];

$todayDeliveries = mysqli_query(
    $connection,
    "SELECT d.order_id, cu.name AS customer_name, d.route_details, d.scheduled_date, d.status
     FROM delivery d
     JOIN sales_order so ON so.order_id = d.order_id
     JOIN customer cu ON cu.customer_id = so.customer_id
     WHERE d.driver_id = $currentDriverId AND d.scheduled_date = CURDATE()
     ORDER BY d.delivery_id DESC"
);

$statusClasses = [
    "scheduled" => "progress", "dispatched" => "progress", "in_transit" => "progress",
    "delivered" => "resolved", "delayed" => "pending", "cancelled" => "pending",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Dashboard</title>
  <link rel="stylesheet" href="css/driver_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Driver</h1>
    <p>Assigned deliveries, route details, proof upload, and fuel usage</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of assigned delivery work.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Assigned Routes</h3>
          <p class="number"><?= $assignedRoutesToday ?></p>
          <p>Routes for today</p>
        </div>
        <div class="card">
          <h3>Completed</h3>
          <p class="number"><?= $completedDeliveries ?></p>
          <p>Delivered orders</p>
        </div>
        <div class="card">
          <h3>Issues</h3>
          <p class="number"><?= $reportedIssues ?></p>
          <p>Reported delivery issues</p>
        </div>
        <div class="card">
          <h3>Fuel Used</h3>
          <p class="number"><?= number_format($fuelUsed, 2) ?></p>
          <p>Liters recorded</p>
        </div>
      </section>

      <section class="panel">
        <h3>Today Deliveries</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Route</th>
                <th>Delivery Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($todayDeliveries) === 0): ?>
                <tr><td colspan="5">No delivery records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($todayDeliveries)): ?>
                <tr>
                  <td><?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["route_details"] ?? "") ?></td>
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
