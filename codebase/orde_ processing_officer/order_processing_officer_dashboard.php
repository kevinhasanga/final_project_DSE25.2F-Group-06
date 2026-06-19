<?php
require_once __DIR__ . '/../login/auth.php';
require_login('Order Processing Officer');
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
    <nav class="sidebar">
      <h2>Order Officer</h2>
      <a class="active" href="order_processing_officer_dashboard.php">Dashboard</a>
      <a href="sales_order_management.html">Sales Orders</a>
      <a href="stock_availability.html">Stock Availability</a>
      <a href="credit_order_approval.html">Credit Approval</a>
      <a href="invoice_generation.html">Invoices</a>
      <a href="discount_tax_calculation.html">Discounts & Taxes</a>
      <a href="order_status_update.html">Order Status</a>
      <a href="order_reports.html">Order Reports</a>
      <a href="daily_sales_totals.html">Daily Sales</a>
      <a href="../login/logout.php">Log out</a>
    </nav>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of order processing activities.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Today's Orders</h3>
          <p class="number" id="todayOrders">0</p>
          <p>Orders received today</p>
        </div>
        <div class="card">
          <h3>Pending Orders</h3>
          <p class="number" id="pendingOrders">0</p>
          <p>Need processing</p>
        </div>
        <div class="card">
          <h3>Invoices</h3>
          <p class="number" id="todayInvoices">0</p>
          <p>Generated today</p>
        </div>
        <div class="card">
          <h3>Daily Sales</h3>
          <p class="number" id="dailySales">0</p>
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
            <tbody id="recentOrdersTable">
              <tr>
                <td colspan="5">No order records loaded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
