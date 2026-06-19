<?php
require_once __DIR__ . '/../login/auth.php';
require_login('Driver');
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
    <nav class="sidebar">
      <h2>Driver</h2>
      <a class="active" href="driver_dashboard.php">Dashboard</a>
      <a href="assigned_routes.html">Assigned Routes</a>
      <a href="delivery_status.html">Delivery Status</a>
      <a href="delivery_issues.html">Delivery Issues</a>
      <a href="proof_of_delivery.html">Proof of Delivery</a>
      <a href="fuel_usage.html">Fuel Usage</a>
      <a href="../login/logout.php">Log out</a>
    </nav>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of assigned delivery work.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Assigned Routes</h3>
          <p class="number" id="assignedRoutes">0</p>
          <p>Routes for today</p>
        </div>
        <div class="card">
          <h3>Completed</h3>
          <p class="number" id="completedDeliveries">0</p>
          <p>Delivered orders</p>
        </div>
        <div class="card">
          <h3>Issues</h3>
          <p class="number" id="reportedIssues">0</p>
          <p>Reported delivery issues</p>
        </div>
        <div class="card">
          <h3>Fuel Used</h3>
          <p class="number" id="fuelUsed">0</p>
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
                <th>Delivery Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="todayDeliveriesTable">
              <tr>
                <td colspan="5">No delivery records loaded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
