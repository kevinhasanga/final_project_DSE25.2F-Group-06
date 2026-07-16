<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Inventory Manager');

$activePage = "dashboard";

$totalProducts = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM product"
))[0];

$lowStockCount = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM (
        SELECT p.product_id
        FROM product p
        LEFT JOIN stock_batch sb ON sb.product_id = p.product_id AND sb.status = 'active'
        GROUP BY p.product_id, p.min_stock_level
        HAVING COALESCE(SUM(sb.current_quantity), 0) < p.min_stock_level
     ) AS low_stock"
))[0];

$stockValue = (float) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(sb.current_quantity * p.selling_price), 0)
     FROM stock_batch sb
     JOIN product p ON p.product_id = sb.product_id
     WHERE sb.status = 'active'"
))[0];

$expiringSoon = (int) mysqli_fetch_row(mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM stock_batch
     WHERE status = 'active' AND expiry_date IS NOT NULL
     AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
))[0];

$recentMovements = mysqli_query(
    $connection,
    "SELECT sm.movement_id, p.product_name, sm.movement_type, sm.quantity, sm.movement_date
     FROM stock_movement sm
     JOIN product p ON p.product_id = sm.product_id
     ORDER BY sm.movement_date DESC, sm.movement_id DESC
     LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory Manager Dashboard</title>
  <link rel="stylesheet" href="css/inventory_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Inventory Manager</h1>
    <p>Products, stock, batches, expiry dates, transfers, reports, and alerts</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="content">
      <section class="page-title">
        <h2>Dashboard</h2>
        <p>Overview of current inventory status.</p>
      </section>

      <section class="cards">
        <div class="card">
          <h3>Total Products</h3>
          <p class="number"><?= $totalProducts ?></p>
          <p>Active products</p>
        </div>
        <div class="card">
          <h3>Low Stock</h3>
          <p class="number"><?= $lowStockCount ?></p>
          <p>Need restocking</p>
        </div>
        <div class="card">
          <h3>Stock Value</h3>
          <p class="number">Rs. <?= number_format($stockValue, 2) ?></p>
          <p>Total valuation</p>
        </div>
        <div class="card">
          <h3>Expiring Soon</h3>
          <p class="number"><?= $expiringSoon ?></p>
          <p>Within 7 days</p>
        </div>
      </section>

      <section class="panel">
        <h3>Recent Stock Movements</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($recentMovements) === 0): ?>
                <tr><td colspan="4">No stock movement records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($recentMovements)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["product_name"]) ?></td>
                  <td><?= htmlspecialchars(ucfirst($row["movement_type"])) ?></td>
                  <td><?= (int) $row["quantity"] ?></td>
                  <td><?= htmlspecialchars($row["movement_date"]) ?></td>
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
