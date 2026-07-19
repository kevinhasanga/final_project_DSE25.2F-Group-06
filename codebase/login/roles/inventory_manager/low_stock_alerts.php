<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Inventory Manager');

$activePage = "alerts";

$perPage = 5;
$totalLowStock = countRows(
    $connection,
    "SELECT COUNT(*) FROM (
        SELECT p.product_id
        FROM product p
        LEFT JOIN stock_batch sb ON sb.product_id = p.product_id AND sb.status = 'active'
        GROUP BY p.product_id, p.product_name, p.min_stock_level
        HAVING COALESCE(SUM(sb.current_quantity), 0) < p.min_stock_level
     ) AS sub"
);
$totalLowStockPages = max(1, (int) ceil($totalLowStock / $perPage));
$currentPage = min(getCurrentPage(), $totalLowStockPages);
$offset = ($currentPage - 1) * $perPage;

$lowStockProducts = mysqli_query(
    $connection,
    "SELECT p.product_id, p.product_name, p.min_stock_level,
            COALESCE(SUM(sb.current_quantity), 0) AS current_stock
     FROM product p
     LEFT JOIN stock_batch sb ON sb.product_id = p.product_id AND sb.status = 'active'
     GROUP BY p.product_id, p.product_name, p.min_stock_level
     HAVING COALESCE(SUM(sb.current_quantity), 0) < p.min_stock_level
     ORDER BY (p.min_stock_level - COALESCE(SUM(sb.current_quantity), 0)) DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Low Stock Alerts</title>
  <link rel="stylesheet" href="css/inventory_style.css">
</head>
<body>
  <header class="topbar"><h1>Inventory Manager</h1><p>Automatic low-stock alerts</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Low Stock Alerts</h2><p>Products currently below their minimum stock level, calculated automatically from active batches.</p></section>

      <section class="panel">
        <h3>Products Needing Restock</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Product</th><th>Current Stock</th><th>Minimum Stock</th><th>Shortfall</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($lowStockProducts) === 0): ?>
                <tr><td colspan="4">No products are currently below their minimum stock level.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($lowStockProducts)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["product_name"]) ?></td>
                  <td><?= (int) $row["current_stock"] ?></td>
                  <td><?= (int) $row["min_stock_level"] ?></td>
                  <td><span class="status pending"><?= (int) $row["min_stock_level"] - (int) $row["current_stock"] ?></span></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalLowStock, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
