<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Order Processing Officer');

$activePage = "stock";

$search = trim($_GET["q"] ?? "");
$sql = "SELECT p.product_id, p.product_name, p.min_stock_level,
               COALESCE(SUM(sb.current_quantity), 0) AS current_stock
        FROM product p
        LEFT JOIN stock_batch sb ON sb.product_id = p.product_id AND sb.status = 'active'";
$params = [];
$types = "";

if ($search !== "") {
    $sql .= " WHERE p.product_name LIKE ? OR p.product_id = ?";
    $params[] = "%$search%";
    $params[] = (int) $search;
    $types = "si";
}

$sql .= " GROUP BY p.product_id, p.product_name, p.min_stock_level ORDER BY p.product_name";

$perPage = 10;
$countSql = "SELECT COUNT(*) FROM product p";
if ($search !== "") {
    $countSql .= " WHERE p.product_name LIKE ? OR p.product_id = ?";
}
$totalProducts = countRows($connection, $countSql, $types, $params);
$totalProductPages = max(1, (int) ceil($totalProducts / $perPage));
$currentPage = min(getCurrentPage(), $totalProductPages);
$offset = ($currentPage - 1) * $perPage;

$sql .= " LIMIT $perPage OFFSET $offset";
$statement = mysqli_prepare($connection, $sql);
if ($types !== "") {
    mysqli_stmt_bind_param($statement, $types, ...$params);
}
mysqli_stmt_execute($statement);
$products = mysqli_stmt_get_result($statement);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock Availability</title>
  <link rel="stylesheet" href="css/opo_style.css">
</head>
<body>
  <header class="topbar"><h1>Order Processing</h1><p>Verify stock availability before confirming orders</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Stock Availability</h2><p>Check product stock before creating or approving a sales order.</p></section>

      <section class="panel">
        <h3>Check Stock</h3>
        <form method="get" action="stock_availability.php">
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="q">Product name or ID</label>
              <input type="text" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or ID">
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Check Stock</button>
            <?php if ($search !== ""): ?>
              <a class="btn secondary" href="stock_availability.php">Clear</a>
            <?php endif; ?>
          </div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Product ID</th><th>Product Name</th><th>Available Qty</th><th>Reorder Level</th><th>Stock Status</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($products) === 0): ?>
                <tr><td colspan="5">No products found.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($products)): ?>
                <tr>
                  <td><?= $row["product_id"] ?></td>
                  <td><?= htmlspecialchars($row["product_name"]) ?></td>
                  <td><?= (int) $row["current_stock"] ?></td>
                  <td><?= (int) $row["min_stock_level"] ?></td>
                  <td>
                    <span class="status <?= $row["current_stock"] < $row["min_stock_level"] ? "pending" : "resolved" ?>">
                      <?= $row["current_stock"] < $row["min_stock_level"] ? "Low Stock" : "Available" ?>
                    </span>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalProducts, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
