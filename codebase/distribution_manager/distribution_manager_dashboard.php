<?php
require_once __DIR__ . '/../login/auth.php';
require_login('Distribution Manager');
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
    <nav class="sidebar">
      <h2>Distribution</h2>
      <a class="active" href="distribution_manager_dashboard.php">Dashboard</a>
      <a href="confirmed_orders.html">Confirmed Orders</a>
      <a href="delivery_schedule.html">Delivery Schedules</a>
      <a href="driver_vehicle_assignment.html">Driver & Vehicle</a>
      <a href="route_optimization.html">Route Planning</a>
      <a href="dispatched_orders.html">Dispatched Orders</a>
      <a href="delivery_progress.html">Delivery Progress</a>
      <a href="transportation_costs.html">Transport Costs</a>
      <a href="delivery_reports.html">Reports</a>
      <a href="delayed_deliveries.html">Delayed Deliveries</a>
      <a href="../login/logout.php">Log out</a>
    </nav>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of distribution and delivery activities.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Confirmed Orders</h3>
          <p class="number" id="confirmedOrders">0</p>
          <p>Ready for scheduling</p>
        </div>
        <div class="card">
          <h3>Dispatched</h3>
          <p class="number" id="dispatchedOrders">0</p>
          <p>Orders sent today</p>
        </div>
        <div class="card">
          <h3>Delayed</h3>
          <p class="number" id="delayedOrders">0</p>
          <p>Need attention</p>
        </div>
        <div class="card">
          <h3>Transport Cost</h3>
          <p class="number" id="transportCost">0</p>
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
            <tbody id="recentDeliveryTable">
              <tr>
                <td colspan="5">No delivery activity loaded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
